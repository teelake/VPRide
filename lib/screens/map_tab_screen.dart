import 'dart:async';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:permission_handler/permission_handler.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_scope.dart';
import '../core/auth/auth_scope.dart';
import '../core/brand/brand_assets.dart';
import '../core/client/client_config_scope.dart';
import '../core/config/app_config.dart';
import '../core/maps/geocode_service.dart';
import '../core/region/region_config_scope.dart';
import '../core/region/resolved_region_config.dart';
import '../core/ride/ride_pickup_controller.dart';
import '../core/ride/ride_pickup_scope.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/app_buttons.dart';

/// Live map + center pickup pin; requests ride against [POST /api/v1/rides].
class MapTabScreen extends StatefulWidget {
  const MapTabScreen({super.key});

  @override
  State<MapTabScreen> createState() => _MapTabScreenState();
}

class _MapTabScreenState extends State<MapTabScreen>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  final Completer<GoogleMapController> _mapController = Completer();
  final GeocodeService _geocoder = GeocodeService();
  Timer? _geoDebounce;
  LatLng? _cameraTarget;
  bool _rideBusy = false;
  bool _didSeedPickup = false;
  bool _didAutoRefreshClientConfig = false;
  bool _configRetryBusy = false;
  final TextEditingController _promoCodeCtrl = TextEditingController();
  int? _activeRideId;
  bool _sosBusy = false;
  bool _ridePollBusy = false;

  static const LatLng _fallbackToronto = LatLng(43.6532, -79.3832);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_didSeedPickup) {
      _didSeedPickup = true;
      final pickupCtrl = RidePickupScope.of(context);
      pickupCtrl.setFromCamera(_initialTarget(context));
    }
    if (!_didAutoRefreshClientConfig &&
        AppConfig.apiBaseUrl.trim().isNotEmpty) {
      final cfg = ClientConfigScope.of(context);
      if (cfg.effectiveMapsApiKey.trim().isEmpty) {
        _didAutoRefreshClientConfig = true;
        cfg.refresh().then((_) {
          if (mounted) setState(() {});
        });
      }
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _refreshActiveRide(context);
    });
  }

  @override
  void dispose() {
    _geoDebounce?.cancel();
    _geocoder.close();
    _promoCodeCtrl.dispose();
    super.dispose();
  }

  String _uuidV4() {
    final r = Random.secure();
    String h(int n) => n.toRadixString(16).padLeft(2, '0');
    final b = List<int>.generate(16, (_) => r.nextInt(256));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    final s = b.map(h).join();
    return '${s.substring(0, 8)}-${s.substring(8, 12)}-${s.substring(12, 16)}-${s.substring(16, 20)}-${s.substring(20)}';
  }

  Future<void> _refreshActiveRide(BuildContext context) async {
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null || !auth.isSignedIn) {
      if (mounted) setState(() => _activeRideId = null);
      return;
    }
    if (_ridePollBusy) return;
    setState(() => _ridePollBusy = true);
    try {
      final api = ApiScope.of(context);
      final res = await api.getCurrentRide(token);
      final ride = res['ride'];
      final id = ride is Map<String, dynamic> ? ride['id'] : null;
      if (mounted) {
        setState(() => _activeRideId = id is int ? id : int.tryParse('$id'));
      }
    } catch (_) {
      if (mounted) setState(() => _activeRideId = null);
    } finally {
      if (mounted) setState(() => _ridePollBusy = false);
    }
  }

  Future<void> _sendSos(BuildContext context) async {
    final rideId = _activeRideId;
    if (rideId == null) return;
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    final messenger = ScaffoldMessenger.of(context);
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Send SOS?'),
        content: const Text(
          'This notifies your operations team about a serious issue on this trip.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(backgroundColor: Colors.red.shade700),
            child: const Text('Send alert'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;

    setState(() => _sosBusy = true);
    try {
      double lat = _cameraTarget?.latitude ?? _fallbackToronto.latitude;
      double lng = _cameraTarget?.longitude ?? _fallbackToronto.longitude;
      try {
        final pos = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.high,
            timeLimit: Duration(seconds: 10),
          ),
        );
        lat = pos.latitude;
        lng = pos.longitude;
      } catch (_) {}

      await ApiScope.of(context).postSos(
        bearerToken: token,
        rideId: rideId,
        latitude: lat,
        longitude: lng,
        clientRequestId: _uuidV4(),
      );
      if (!mounted) return;
      messenger.showSnackBar(
        const SnackBar(content: Text('SOS sent. Help is being notified.')),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    } catch (e) {
      if (!mounted) return;
      messenger.showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _sosBusy = false);
    }
  }

  LatLng _initialTarget(BuildContext context) {
    final c = RegionConfigScope.resolvedOf(context).defaultMapCenter;
    if (c != null) return LatLng(c.latitude, c.longitude);
    return _fallbackToronto;
  }

  Future<void> _centerOnUser() async {
    final status = await Permission.locationWhenInUse.request();
    if (!status.isGranted || !mounted) return;
    final allowed = await Geolocator.isLocationServiceEnabled();
    if (!allowed || !mounted) return;
    try {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
          timeLimit: Duration(seconds: 12),
        ),
      );
      if (!mounted) return;
      final target = LatLng(pos.latitude, pos.longitude);
      final ctrl = await _mapController.future;
      await ctrl.animateCamera(CameraUpdate.newLatLngZoom(target, 16));
    } catch (_) {
      /* ignore — stay on default */
    }
  }

  void _scheduleGeocode(
    RidePickupController pickupCtrl,
    LatLng p,
    String mapsApiKey,
  ) {
    _geoDebounce?.cancel();
    if (mapsApiKey.trim().isEmpty) {
      pickupCtrl.setAddressLabel(
        '${p.latitude.toStringAsFixed(5)}, ${p.longitude.toStringAsFixed(5)}',
      );
      return;
    }
    pickupCtrl.setAddressLabel('Finding address…', geocoding: true);
    _geoDebounce = Timer(const Duration(milliseconds: 480), () async {
      final label = await _geocoder.reverseFormattedAddress(
        p.latitude,
        p.longitude,
        apiKey: mapsApiKey,
      );
      if (!mounted) return;
      pickupCtrl.setAddressLabel(
        label ??
            '${p.latitude.toStringAsFixed(5)}, ${p.longitude.toStringAsFixed(5)}',
        geocoding: false,
      );
    });
  }

  Future<void> _requestRide(
    BuildContext context,
    RidePickupController pickupCtrl,
  ) async {
    final auth = AuthScope.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final router = GoRouter.of(context);
    final cfg = ClientConfigScope.of(context).features;
    if (cfg.maintenanceMode) {
      final msg = cfg.maintenanceMessage.trim().isNotEmpty
          ? cfg.maintenanceMessage.trim()
          : 'Ride requests are paused (maintenance).';
      messenger.showSnackBar(SnackBar(content: Text(msg)));
      return;
    }
    if (!cfg.rideBookingEnabled) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Ride booking is turned off in the app settings.'),
        ),
      );
      return;
    }
    if (!auth.isSignedIn) {
      messenger.showSnackBar(
        SnackBar(
          content: const Text('Sign in to request a ride.'),
          action: SnackBarAction(
            label: 'Sign in',
            onPressed: () => router.push('/welcome'),
          ),
        ),
      );
      return;
    }
    final p = pickupCtrl.pickup;
    if (p == null) return;
    final token = auth.sessionToken;
    if (token == null) return;

    final api = ApiScope.of(context);

    setState(() => _rideBusy = true);
    try {
      final promo = cfg.promoCodeEntryEnabled ? _promoCodeCtrl.text.trim() : '';
      final res = await api.postRide(
        bearerToken: token,
        pickupLat: p.latitude,
        pickupLng: p.longitude,
        pickupAddress: pickupCtrl.addressLabel,
        promoCode: promo.isNotEmpty ? promo : null,
      );
      if (!mounted) return;
      final id = res['id'];
      final pricing = res['pricing'];
      var sub = 'Ride requested${id != null ? ' · #$id' : ''}';
      if (pricing is Map && pricing['finalFare'] != null) {
        final cur = '${pricing['currency'] ?? ''}'.trim();
        sub += ' · $cur ${pricing['finalFare']}';
      }
      messenger.showSnackBar(SnackBar(content: Text(sub)));
      await _refreshActiveRide(context);
    } on ApiException catch (e) {
      if (!mounted) return;
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    } catch (e) {
      if (!mounted) return;
      messenger.showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _rideBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final region = RegionConfigScope.resolvedOf(context);
    final pickupCtrl = RidePickupScope.of(context);
    final textTheme = Theme.of(context).textTheme;
    final clientCfg = ClientConfigScope.of(context);
    final mapsKey = clientCfg.effectiveMapsApiKey.trim();
    final hasMapsKey = mapsKey.isNotEmpty;
    final features = clientCfg.features;
    final rideDisabled =
        features.maintenanceMode || !features.rideBookingEnabled;

    if (!hasMapsKey) {
      return _MapPlaceholder(
        region: region,
        textTheme: textTheme,
        canRetryFromServer: AppConfig.apiBaseUrl.trim().isNotEmpty,
        retryBusy: _configRetryBusy,
        onRetryFromServer: AppConfig.apiBaseUrl.trim().isEmpty
            ? null
            : () async {
                setState(() => _configRetryBusy = true);
                try {
                  await clientCfg.refresh();
                } finally {
                  if (mounted) {
                    setState(() => _configRetryBusy = false);
                  }
                }
              },
      );
    }

    final initial = _initialTarget(context);

    return ColoredBox(
      color: AppColors.surfaceMuted,
      child: Stack(
        fit: StackFit.expand,
        children: [
          GoogleMap(
            initialCameraPosition: CameraPosition(
              target: initial,
              zoom: 14.5,
            ),
            myLocationEnabled: true,
            myLocationButtonEnabled: false,
            compassEnabled: true,
            mapToolbarEnabled: false,
            padding: const EdgeInsets.only(bottom: 200),
            onMapCreated: (c) {
              _mapController.complete(c);
              _centerOnUser();
            },
            onCameraMove: (pos) => _cameraTarget = pos.target,
            onCameraIdle: () {
              final t = _cameraTarget;
              if (t != null) {
                pickupCtrl.setFromCamera(t);
                _scheduleGeocode(pickupCtrl, t, mapsKey);
              }
            },
          ),
          Center(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 52),
              child: Icon(
                Icons.location_pin,
                size: 52,
                color: AppColors.secondary,
                shadows: [
                  Shadow(
                    color: Colors.black.withValues(alpha: 0.2),
                    blurRadius: 6,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
            ),
          ),
          Positioned(
            right: 16,
            top: 16,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                if (features.sosEnabled &&
                    AuthScope.of(context).isSignedIn &&
                    _activeRideId != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Material(
                      color: Colors.red.shade700,
                      elevation: 3,
                      shape: const CircleBorder(),
                      child: IconButton(
                        tooltip: 'SOS — alert operations',
                        onPressed: _sosBusy ? null : () => _sendSos(context),
                        icon: _sosBusy
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Icon(Icons.priority_high, color: Colors.white),
                      ),
                    ),
                  ),
                Material(
                  color: Colors.white,
                  elevation: 2,
                  shape: const CircleBorder(),
                  child: IconButton(
                    tooltip: 'My location',
                    onPressed: _centerOnUser,
                    icon: const Icon(Icons.my_location_rounded),
                    color: AppColors.secondary,
                  ),
                ),
              ],
            ),
          ),
          Positioned(
            left: 16,
            right: 16,
            bottom: 16,
            child: Material(
              elevation: 8,
              shadowColor: Colors.black26,
              borderRadius: BorderRadius.circular(20),
              color: Colors.white,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(18, 14, 18, 16),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Pickup',
                      style: textTheme.labelSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.08,
                        color: AppColors.secondary.withValues(alpha: 0.45),
                      ),
                    ),
                    const SizedBox(height: 6),
                    ListenableBuilder(
                      listenable: pickupCtrl,
                      builder: (context, _) {
                        final label = pickupCtrl.addressLabel ??
                            'Move map to adjust pin';
                        return Text(
                          pickupCtrl.isGeocoding ? 'Finding address…' : label,
                          style: textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w600,
                            height: 1.3,
                          ),
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                        );
                      },
                    ),
                    if (features.promoCodeEntryEnabled) ...[
                      const SizedBox(height: 12),
                      TextField(
                        controller: _promoCodeCtrl,
                        textCapitalization: TextCapitalization.characters,
                        decoration: const InputDecoration(
                          labelText: 'Promo code (optional)',
                          isDense: true,
                          border: OutlineInputBorder(),
                        ),
                      ),
                    ],
                    const SizedBox(height: 14),
                    AppPrimaryButton(
                      label: rideDisabled
                          ? (features.maintenanceMode ? 'Unavailable (maintenance)'
                                : 'Booking disabled')
                          : 'Request ride',
                      isLoading: _rideBusy,
                      onPressed: _rideBusy || rideDisabled
                          ? null
                          : () => _requestRide(context, pickupCtrl),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MapPlaceholder extends StatelessWidget {
  const _MapPlaceholder({
    required this.region,
    required this.textTheme,
    required this.canRetryFromServer,
    required this.retryBusy,
    required this.onRetryFromServer,
  });

  final ResolvedRegionConfig region;
  final TextTheme textTheme;
  final bool canRetryFromServer;
  final bool retryBusy;
  final Future<void> Function()? onRetryFromServer;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: AppColors.surfaceMuted,
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(28),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 400),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Image.asset(
                  BrandAssets.logoMonogramPin,
                  height: 56,
                  filterQuality: FilterQuality.high,
                ),
                const SizedBox(height: 24),
                Text(
                  'Maps API key needed',
                  style: textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    letterSpacing: -0.4,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'The app loads your key from GET ${AppConfig.publicConfigPath.startsWith('/') ? AppConfig.publicConfigPath : '/${AppConfig.publicConfigPath}'} '
                  'on your API (field mapsApiKey). Set it under Admin → Settings, or add MAPS_API_KEY to the server .env. '
                  'Native maps also need the same key in Android local.properties (maps.api.key) and iOS GMSApiKey. '
                  'Region: ${region.serviceAreaLabel}.',
                  textAlign: TextAlign.center,
                  style: textTheme.bodyMedium?.copyWith(
                    color: AppColors.secondary.withValues(alpha: 0.65),
                    height: 1.45,
                  ),
                ),
                if (canRetryFromServer && onRetryFromServer != null) ...[
                  const SizedBox(height: 24),
                  AppPrimaryButton(
                    label: retryBusy ? 'Loading…' : 'Retry loading config',
                    isLoading: retryBusy,
                    onPressed: retryBusy ? null : () => onRetryFromServer!(),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
