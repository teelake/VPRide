import 'dart:async';

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

  static const LatLng _fallbackToronto = LatLng(43.6532, -79.3832);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_didSeedPickup) return;
    _didSeedPickup = true;
    final pickupCtrl = RidePickupScope.of(context);
    pickupCtrl.setFromCamera(_initialTarget(context));
  }

  @override
  void dispose() {
    _geoDebounce?.cancel();
    _geocoder.close();
    super.dispose();
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
      final res = await api.postRide(
        bearerToken: token,
        pickupLat: p.latitude,
        pickupLng: p.longitude,
        pickupAddress: pickupCtrl.addressLabel,
      );
      if (!mounted) return;
      final id = res['id'];
      messenger.showSnackBar(
        SnackBar(content: Text('Ride requested${id != null ? ' · #$id' : ''}')),
      );
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
    final mapsKey = ClientConfigScope.of(context).effectiveMapsApiKey.trim();
    final hasMapsKey = mapsKey.isNotEmpty;

    if (!hasMapsKey) {
      return _MapPlaceholder(region: region, textTheme: textTheme);
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
            child: Material(
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
                    const SizedBox(height: 14),
                    AppPrimaryButton(
                      label: 'Request ride',
                      isLoading: _rideBusy,
                      onPressed: _rideBusy
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
  });

  final ResolvedRegionConfig region;
  final TextTheme textTheme;

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
                  'Add a Maps API key: set it in the admin console (public config), '
                  'or pass MAPS_API_KEY / maps.api.key (Android) and GMSApiKey (iOS) for native SDK. '
                  'Region: ${region.serviceAreaLabel}.',
                  textAlign: TextAlign.center,
                  style: textTheme.bodyMedium?.copyWith(
                    color: AppColors.secondary.withValues(alpha: 0.65),
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
