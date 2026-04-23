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
import '../core/trip/rider_trip_copy.dart';
import '../core/widgets/app_buttons.dart';
import 'trip_detail_screen.dart';

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
  Map<String, dynamic>? _activeRide;
  Timer? _currentRidePoll;
  bool _sosBusy = false;
  bool _ridePollBusy = false;
  bool _cancelRideBusy = false;

  /// When true, the map pin adjusts pickup; when false, destination.
  bool _pinPickup = true;
  LatLng? _destPoint;
  String? _destLabel;
  bool _destGeocoding = false;
  Timer? _destGeoDebounce;
  bool _roundTrip = false;
  DateTime? _scheduledPickupUtc;
  bool _estimateBusy = false;
  Map<String, dynamic>? _lastEstimate;
  final TextEditingController _destSearchCtrl = TextEditingController();
  final TextEditingController _pickupSearchCtrl = TextEditingController();
  final FocusNode _pickupSearchFocusNode = FocusNode();
  RidePickupController? _pickupListenTarget;

  /// When region config is missing; matches default seed (Winkler, MB).
  static const LatLng _fallbackMapCenter = LatLng(49.1817, -97.9411);

  void _onPickupControllerTick() {
    if (mounted) setState(() {});
  }

  void _syncPickupListener(RidePickupController pickupCtrl) {
    if (identical(_pickupListenTarget, pickupCtrl)) {
      return;
    }
    _pickupListenTarget?.removeListener(_onPickupControllerTick);
    _pickupListenTarget = pickupCtrl;
    _pickupListenTarget!.addListener(_onPickupControllerTick);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _syncPickupListener(RidePickupScope.of(context));
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
      if (mounted) _refreshActiveRide();
    });
  }

  @override
  void dispose() {
    _currentRidePoll?.cancel();
    _pickupListenTarget?.removeListener(_onPickupControllerTick);
    _geoDebounce?.cancel();
    _destGeoDebounce?.cancel();
    _geocoder.close();
    _promoCodeCtrl.dispose();
    _destSearchCtrl.dispose();
    _pickupSearchCtrl.dispose();
    _pickupSearchFocusNode.dispose();
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

  void _syncCurrentRidePoll() {
    if (!mounted) return;
    final auth = AuthScope.of(context);
    if (!auth.isSignedIn || auth.sessionToken == null || _activeRide == null) {
      _currentRidePoll?.cancel();
      _currentRidePoll = null;
      return;
    }
    if (_currentRidePoll != null) return;
    _currentRidePoll = Timer.periodic(const Duration(seconds: 14), (_) {
      unawaited(_pollCurrentRideQuiet());
    });
  }

  Future<void> _pollCurrentRideQuiet() async {
    if (!mounted) return;
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null || !auth.isSignedIn) return;
    final api = ApiScope.of(context);
    try {
      final res = await api.getCurrentRide(token);
      if (!mounted) return;
      final ride = res['ride'];
      if (ride is Map<String, dynamic>) {
        final id = ride['id'];
        final parsedId = id is int ? id : int.tryParse('$id');
        setState(() {
          _activeRide = ride;
          _activeRideId = parsedId;
        });
      } else {
        setState(() {
          _activeRide = null;
          _activeRideId = null;
        });
        _currentRidePoll?.cancel();
        _currentRidePoll = null;
      }
    } catch (_) {}
  }

  Future<void> _refreshActiveRide() async {
    if (!mounted) return;
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null || !auth.isSignedIn) {
      if (mounted) {
        setState(() {
          _activeRideId = null;
          _activeRide = null;
        });
      }
      _currentRidePoll?.cancel();
      _currentRidePoll = null;
      return;
    }
    if (_ridePollBusy) return;
    final api = ApiScope.of(context);
    setState(() => _ridePollBusy = true);
    try {
      final res = await api.getCurrentRide(token);
      final ride = res['ride'];
      if (!mounted) return;
      if (ride is Map<String, dynamic>) {
        final id = ride['id'];
        final parsedId = id is int ? id : int.tryParse('$id');
        setState(() {
          _activeRide = ride;
          _activeRideId = parsedId;
        });
        _syncCurrentRidePoll();
      } else {
        setState(() {
          _activeRide = null;
          _activeRideId = null;
        });
        _currentRidePoll?.cancel();
        _currentRidePoll = null;
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _activeRideId = null;
          _activeRide = null;
        });
      }
      _currentRidePoll?.cancel();
      _currentRidePoll = null;
    } finally {
      if (mounted) setState(() => _ridePollBusy = false);
    }
  }

  Widget? _activeTripBanner(BuildContext context) {
    final ride = _activeRide;
    final id = _activeRideId;
    if (ride == null || id == null) return null;
    final theme = Theme.of(context);
    final phase = ride['lifecyclePhase']?.toString();
    final driver = ride['driver'];
    final driverLine = riderTripDriverLine(
      driver is Map<String, dynamic> ? driver : null,
    );
    final eta = riderTripEtaSummary(
      ride['eta'] is Map<String, dynamic> ? ride['eta'] as Map<String, dynamic> : null,
    );
    final statusRaw = '${ride['status'] ?? ''}';

    return Material(
      elevation: 6,
      shadowColor: Colors.black26,
      borderRadius: BorderRadius.circular(16),
      color: Colors.white,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () async {
          await Navigator.of(context).push<void>(
            MaterialPageRoute<void>(
              builder: (ctx) => TripDetailScreen(rideId: id),
            ),
          );
          if (mounted) _refreshActiveRide();
        },
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Icon(Icons.directions_car_filled_rounded, color: AppColors.secondary),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      riderTripPhaseTitle(phase),
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  Text(
                    '#$id',
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: AppColors.secondary.withValues(alpha: 0.5),
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
              if (statusRaw.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    'Status: $statusRaw',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.secondary.withValues(alpha: 0.55),
                    ),
                  ),
                ),
              if (driverLine.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    driverLine,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                      height: 1.25,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              if (eta != null)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Row(
                    children: [
                      Icon(
                        Icons.schedule_rounded,
                        size: 18,
                        color: AppColors.secondary.withValues(alpha: 0.65),
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          eta,
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontWeight: FontWeight.w600,
                            color: AppColors.secondary.withValues(alpha: 0.75),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  'Tap for details',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: AppColors.secondary.withValues(alpha: 0.45),
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Builder(
                builder: (ctx) {
                  final auth = AuthScope.of(ctx);
                  final profileId = auth.profile?.id;
                  final ru = ride['riderUserId'];
                  int? riderUid;
                  if (ru is int) {
                    riderUid = ru;
                  } else if (ru is num) {
                    riderUid = ru.toInt();
                  }
                  final canCancel = profileId != null &&
                      riderUid != null &&
                      profileId == riderUid &&
                      (statusRaw == 'requested' ||
                          statusRaw == 'accepted' ||
                          statusRaw == 'in_progress');
                  if (!canCancel) return const SizedBox.shrink();
                  return Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: SizedBox(
                      width: double.infinity,
                      child: OutlinedButton(
                        onPressed: _cancelRideBusy
                            ? null
                            : () => _offerCancelRide(ctx, id, ride),
                        child: const Text('Cancel ride'),
                      ),
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _offerCancelRide(
    BuildContext context,
    int rideId,
    Map<String, dynamic> ride,
  ) async {
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    final fee = ClientConfigScope.of(context).operations.riderCancellationFeeAmount;
    var currency = '';
    final p = ride['pricing'];
    if (p is Map) {
      currency = '${p['currency'] ?? ''}'.trim();
    }
    final feeLine = fee > 0
        ? 'A cancellation fee of $currency ${fee.toStringAsFixed(2)} applies and will be recorded on this ride.'
        : 'No cancellation fee is configured for your area.';
    final api = ApiScope.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel this ride?'),
        content: Text(
          '$feeLine You can request another ride anytime.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Keep ride')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Cancel ride'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;

    setState(() => _cancelRideBusy = true);
    try {
      await api.postRideCancel(bearerToken: token, rideId: rideId);
      if (!mounted) return;
      messenger.showSnackBar(const SnackBar(content: Text('Ride cancelled.')));
      await _refreshActiveRide();
    } on ApiException catch (e) {
      if (mounted) {
        messenger.showSnackBar(SnackBar(content: Text(e.message)));
      }
    } catch (e) {
      if (mounted) {
        messenger.showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _cancelRideBusy = false);
    }
  }

  Future<void> _sendSos(BuildContext context) async {
    final rideId = _activeRideId;
    if (rideId == null) return;
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    final api = ApiScope.of(context);
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
      double lat = _cameraTarget?.latitude ?? _fallbackMapCenter.latitude;
      double lng = _cameraTarget?.longitude ?? _fallbackMapCenter.longitude;
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

      await api.postSos(
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
    return _fallbackMapCenter;
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

  /// Same key as destination: [ClientConfigRepository.effectiveMapsApiKey]
  /// (public `mapsApiKey` with `MAPS_API_KEY` / dart-define fallback).
  static String _normalizeMapsGeocodingKey(String mapsApiKey) =>
      mapsApiKey.trim();

  /// Forward geocode for pickup/destination search — always pass
  /// [_normalizeMapsGeocodingKey] of [ClientConfigScope.of].effectiveMapsApiKey.
  Future<({double lat, double lng, String address})?> _geocodeQuery(
    String rawQuery,
    String mapsApiKey,
  ) {
    final q = rawQuery.trim();
    final key = _normalizeMapsGeocodingKey(mapsApiKey);
    if (q.isEmpty || key.isEmpty) return Future.value(null);
    return _geocoder.geocodeAddress(q, apiKey: key);
  }

  void _scheduleGeocode(
    RidePickupController pickupCtrl,
    LatLng p,
    String mapsApiKey,
  ) {
    _geoDebounce?.cancel();
    final key = _normalizeMapsGeocodingKey(mapsApiKey);
    if (key.isEmpty) {
      final coords =
          '${p.latitude.toStringAsFixed(5)}, ${p.longitude.toStringAsFixed(5)}';
      pickupCtrl.setAddressLabel(coords);
      if (!_pickupSearchFocusNode.hasFocus) {
        _pickupSearchCtrl.text = coords;
      }
      return;
    }
    pickupCtrl.setAddressLabel('Finding address…', geocoding: true);
    _geoDebounce = Timer(const Duration(milliseconds: 480), () async {
      final label = await _geocoder.reverseFormattedAddress(
        p.latitude,
        p.longitude,
        apiKey: key,
      );
      if (!mounted) return;
      final resolved = label ??
          '${p.latitude.toStringAsFixed(5)}, ${p.longitude.toStringAsFixed(5)}';
      pickupCtrl.setAddressLabel(
        resolved,
        geocoding: false,
      );
      if (!_pickupSearchFocusNode.hasFocus) {
        _pickupSearchCtrl.text = resolved;
      }
    });
  }

  void _scheduleDestGeocode(LatLng p, String mapsApiKey) {
    _destGeoDebounce?.cancel();
    final key = _normalizeMapsGeocodingKey(mapsApiKey);
    if (key.isEmpty) {
      setState(() {
        _destLabel =
            '${p.latitude.toStringAsFixed(5)}, ${p.longitude.toStringAsFixed(5)}';
        _destGeocoding = false;
      });
      return;
    }
    setState(() => _destGeocoding = true);
    _destGeoDebounce = Timer(const Duration(milliseconds: 480), () async {
      final label = await _geocoder.reverseFormattedAddress(
        p.latitude,
        p.longitude,
        apiKey: key,
      );
      if (!mounted) return;
      setState(() {
        _destGeocoding = false;
        _destLabel = label ??
            '${p.latitude.toStringAsFixed(5)}, ${p.longitude.toStringAsFixed(5)}';
      });
    });
  }

  Future<void> _searchDestination(String mapsApiKey) async {
    final hit = await _geocodeQuery(_destSearchCtrl.text, mapsApiKey);
    if (!mounted) return;
    if (hit == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No results for that destination')),
      );
      return;
    }
    setState(() {
      _destPoint = LatLng(hit.lat, hit.lng);
      _destLabel = hit.address;
      _pinPickup = false;
    });
    try {
      final c = await _mapController.future;
      await c.animateCamera(
        CameraUpdate.newLatLngZoom(LatLng(hit.lat, hit.lng), 14.5),
      );
    } catch (_) {}
  }

  Future<void> _searchPickup(
    RidePickupController pickupCtrl,
    String mapsApiKey,
  ) async {
    final hit = await _geocodeQuery(_pickupSearchCtrl.text, mapsApiKey);
    if (!mounted) return;
    if (hit == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No results for that pickup address')),
      );
      return;
    }
    setState(() => _pinPickup = true);
    pickupCtrl.setFromCamera(LatLng(hit.lat, hit.lng));
    pickupCtrl.setAddressLabel(hit.address);
    _pickupSearchCtrl.value = TextEditingValue(
      text: hit.address,
      selection: TextSelection.collapsed(offset: hit.address.length),
    );
    try {
      final c = await _mapController.future;
      await c.animateCamera(
        CameraUpdate.newLatLngZoom(LatLng(hit.lat, hit.lng), 14.5),
      );
    } catch (_) {}
  }

  Future<void> _runEstimate(
    BuildContext context,
    RidePickupController pickupCtrl,
  ) async {
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    final p = pickupCtrl.pickup;
    if (token == null || p == null || !auth.isSignedIn) return;
    final cfg = ClientConfigScope.of(context).features;
    final messenger = ScaffoldMessenger.of(context);
    final api = ApiScope.of(context);
    setState(() => _estimateBusy = true);
    try {
      final promo = cfg.promoCodeEntryEnabled ? _promoCodeCtrl.text.trim() : '';
      final res = await api.postRideEstimate(
        bearerToken: token,
        pickupLat: p.latitude,
        pickupLng: p.longitude,
        pickupAddress: pickupCtrl.addressLabel,
        destLat: _destPoint?.latitude,
        destLng: _destPoint?.longitude,
        destAddress: _destLabel,
        roundTrip: _roundTrip,
        promoCode: promo.isNotEmpty ? promo : null,
      );
      if (!mounted) return;
      setState(() => _lastEstimate = res);
      final total = res['totalFinalFare'];
      final dist = res['distanceKm'];
      var msg = 'Estimate updated';
      if (dist != null) msg += ' · $dist km';
      if (total != null) msg += ' · total $total';
      messenger.showSnackBar(SnackBar(content: Text(msg)));
    } on ApiException catch (e) {
      if (mounted) messenger.showSnackBar(SnackBar(content: Text(e.message)));
    } catch (e) {
      if (mounted) messenger.showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _estimateBusy = false);
    }
  }

  String _estimateSummaryLine(Map<String, dynamic> e) {
    final dist = e['distanceKm'];
    final total = e['totalFinalFare'];
    final p = e['pricing'];
    var s = '';
    if (dist != null) s += '$dist km · ';
    if (p is Map && p['finalFare'] != null) {
      final cur = '${p['currency'] ?? ''}'.trim();
      s += 'Leg $cur ${p['finalFare']}';
    }
    if (e['returnPricing'] is Map && e['roundTrip'] == true) {
      final r = e['returnPricing'] as Map;
      if (r['finalFare'] != null) {
        final cur = '${r['currency'] ?? ''}'.trim();
        s += ' · return $cur ${r['finalFare']}';
      }
    }
    if (total != null) s += ' · total $total';
    return s.isEmpty ? 'Estimate ready' : s;
  }

  Future<void> _pickSchedule(BuildContext context) async {
    final now = DateTime.now();
    final d = await showDatePicker(
      context: context,
      initialDate: now.add(const Duration(days: 1)),
      firstDate: now,
      lastDate: now.add(const Duration(days: 365)),
    );
    if (d == null) return;
    if (!context.mounted) return;
    final t = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(now.add(const Duration(hours: 2))),
    );
    if (t == null) return;
    if (!context.mounted) return;
    final local = DateTime(d.year, d.month, d.day, t.hour, t.minute);
    setState(() => _scheduledPickupUtc = local.toUtc());
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
        destLat: _destPoint?.latitude,
        destLng: _destPoint?.longitude,
        destAddress: _destLabel,
        roundTrip: _roundTrip,
        scheduledPickupAtIso: _scheduledPickupUtc?.toIso8601String(),
        promoCode: promo.isNotEmpty ? promo : null,
      );
      if (!mounted) return;
      final id = res['id'];
      final pricing = res['pricing'];
      final total = res['totalFinalFare'];
      var sub = 'Ride requested${id != null ? ' · #$id' : ''}';
      if (pricing is Map && pricing['finalFare'] != null) {
        final cur = '${pricing['currency'] ?? ''}'.trim();
        sub += ' · $cur ${pricing['finalFare']}';
      }
      if (total != null) {
        final cur = pricing is Map ? '${pricing['currency'] ?? ''}'.trim() : '';
        sub += ' · total $cur $total'.trim();
      }
      messenger.showSnackBar(SnackBar(content: Text(sub)));
      await _refreshActiveRide();
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
    // Pickup + destination search and reverse geocode all use this key.
    final mapsApiKeyForGeocoding =
        _normalizeMapsGeocodingKey(clientCfg.effectiveMapsApiKey);
    final hasMapsKey = mapsApiKeyForGeocoding.isNotEmpty;
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
    final pickupPt = pickupCtrl.pickup;
    final polys = <Polyline>{};
    if (pickupPt != null && _destPoint != null) {
      polys.add(
        Polyline(
          polylineId: const PolylineId('legroute'),
          color: AppColors.secondary.withValues(alpha: 0.88),
          width: 5,
          points: [pickupPt, _destPoint!],
        ),
      );
    }

    final activeTripBanner = _activeTripBanner(context);

    return ColoredBox(
      color: AppColors.surfaceMuted,
      child: Stack(
        fit: StackFit.expand,
        children: [
          GoogleMap(
            key: const ValueKey<String>('ride_map_singleton'),
            initialCameraPosition: CameraPosition(
              target: initial,
              zoom: 14.5,
            ),
            myLocationEnabled: true,
            myLocationButtonEnabled: false,
            compassEnabled: true,
            mapToolbarEnabled: false,
            polylines: polys,
            padding: const EdgeInsets.only(bottom: 200),
            onMapCreated: (c) {
              if (!_mapController.isCompleted) {
                _mapController.complete(c);
              }
              _centerOnUser();
            },
            onCameraMove: (pos) => _cameraTarget = pos.target,
            onCameraIdle: () {
              final t = _cameraTarget;
              if (t == null) return;
              if (_pinPickup) {
                pickupCtrl.setFromCamera(t);
                _scheduleGeocode(pickupCtrl, t, mapsApiKeyForGeocoding);
              } else {
                setState(() => _destPoint = t);
                _scheduleDestGeocode(t, mapsApiKeyForGeocoding);
              }
            },
          ),
          Center(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 52),
              child: Icon(
                Icons.location_pin,
                size: 52,
                color: _pinPickup ? AppColors.secondary : Colors.deepOrange.shade700,
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
          if (activeTripBanner != null)
            Positioned(
              left: 14,
              right: 14,
              bottom: 228,
              child: activeTripBanner,
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
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 400),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(18, 14, 18, 16),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          ChoiceChip(
                            label: const Text('Pickup pin'),
                            selected: _pinPickup,
                            onSelected: (_) {
                              setState(() {
                                _pinPickup = true;
                                final cur =
                                    pickupCtrl.addressLabel?.trim() ?? '';
                                if (_pickupSearchCtrl.text.trim().isEmpty &&
                                    cur.isNotEmpty) {
                                  _pickupSearchCtrl.text = cur;
                                }
                              });
                            },
                          ),
                          const SizedBox(width: 8),
                          ChoiceChip(
                            label: const Text('Destination pin'),
                            selected: !_pinPickup,
                            onSelected: (_) {
                              setState(() {
                                _pinPickup = false;
                                final cur = pickupCtrl.pickup;
                                if (_destPoint == null && cur != null) {
                                  _destPoint = cur;
                                  _destLabel = 'Move map or search';
                                }
                              });
                            },
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Text(
                        _pinPickup ? 'Pickup' : 'Destination',
                        style: textTheme.labelSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.08,
                          color: AppColors.secondary.withValues(alpha: 0.45),
                        ),
                      ),
                      const SizedBox(height: 6),
                      if (_pinPickup) ...[
                        TextField(
                          controller: _pickupSearchCtrl,
                          focusNode: _pickupSearchFocusNode,
                          textCapitalization: TextCapitalization.sentences,
                          decoration: InputDecoration(
                            labelText: 'Search pickup',
                            isDense: true,
                            border: const OutlineInputBorder(),
                            suffixIcon: IconButton(
                              tooltip: 'Search',
                              icon: const Icon(Icons.search_rounded),
                              onPressed: mapsApiKeyForGeocoding.isEmpty
                                  ? null
                                  : () => unawaited(
                                        _searchPickup(
                                          pickupCtrl,
                                          mapsApiKeyForGeocoding,
                                        ),
                                      ),
                            ),
                          ),
                          onSubmitted: (_) {
                            if (mapsApiKeyForGeocoding.isNotEmpty) {
                              unawaited(
                                _searchPickup(
                                  pickupCtrl,
                                  mapsApiKeyForGeocoding,
                                ),
                              );
                            }
                          },
                        ),
                        const SizedBox(height: 8),
                        ListenableBuilder(
                          listenable: pickupCtrl,
                          builder: (context, _) {
                            return Text(
                              pickupCtrl.isGeocoding
                                  ? 'Finding address…'
                                  : (pickupCtrl.addressLabel ??
                                        'Move map to set pickup pin'),
                              style: textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w600,
                                height: 1.3,
                              ),
                              maxLines: 3,
                              overflow: TextOverflow.ellipsis,
                            );
                          },
                        ),
                      ] else ...[
                        TextField(
                          controller: _destSearchCtrl,
                          textCapitalization: TextCapitalization.sentences,
                          decoration: InputDecoration(
                            labelText: 'Search destination',
                            isDense: true,
                            border: const OutlineInputBorder(),
                            suffixIcon: IconButton(
                              tooltip: 'Search',
                              icon: const Icon(Icons.search_rounded),
                              onPressed: mapsApiKeyForGeocoding.isEmpty
                                  ? null
                                  : () => _searchDestination(
                                        mapsApiKeyForGeocoding,
                                      ),
                            ),
                          ),
                          onSubmitted: (_) =>
                              _searchDestination(mapsApiKeyForGeocoding),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          _destGeocoding
                              ? 'Finding address…'
                              : (_destLabel ??
                                    'Move map to set destination pin'),
                          style: textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w600,
                            height: 1.3,
                          ),
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                      const SizedBox(height: 10),
                      SwitchListTile.adaptive(
                        contentPadding: EdgeInsets.zero,
                        title: const Text('Round trip'),
                        subtitle: const Text(
                          'Books a return from destination back to pickup',
                          style: TextStyle(fontSize: 12),
                        ),
                        value: _roundTrip,
                        onChanged: rideDisabled
                            ? null
                            : (v) => setState(() {
                                  _roundTrip = v;
                                  _lastEstimate = null;
                                }),
                      ),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: rideDisabled
                                  ? null
                                  : () => _pickSchedule(context),
                              icon: const Icon(Icons.schedule_rounded),
                              label: Text(
                                _scheduledPickupUtc == null
                                    ? 'Schedule pickup'
                                    : 'Scheduled (UTC): ${_scheduledPickupUtc!.toIso8601String()}',
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ),
                          if (_scheduledPickupUtc != null)
                            IconButton(
                              tooltip: 'Clear schedule',
                              onPressed: () =>
                                  setState(() => _scheduledPickupUtc = null),
                              icon: const Icon(Icons.clear_rounded),
                            ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      OutlinedButton.icon(
                        onPressed: _estimateBusy || rideDisabled
                            ? null
                            : () => _runEstimate(context, pickupCtrl),
                        icon: _estimateBusy
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              )
                            : const Icon(Icons.payments_outlined),
                        label: const Text('Refresh fare estimate'),
                      ),
                      if (_lastEstimate != null) ...[
                        const SizedBox(height: 8),
                        Text(
                          _estimateSummaryLine(_lastEstimate!),
                          style: textTheme.bodySmall?.copyWith(
                            color: AppColors.secondary.withValues(alpha: 0.75),
                          ),
                        ),
                      ],
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
                            ? (features.maintenanceMode
                                  ? 'Unavailable (maintenance)'
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
