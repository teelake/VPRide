import 'dart:async';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_scope.dart';
import '../core/auth/auth_scope.dart';
import '../core/theme/app_colors.dart';

String _mapDriverApiMessage(String code) {
  switch (code) {
    case 'not_a_driver':
      return 'This account is not linked to an active driver in the fleet console, '
          'or the link was removed. Ask an administrator to fix the link, sign out '
          'and back in, then pull to refresh.';
    case 'cannot_accept':
      return 'This ride can no longer be accepted. It may have been reassigned or cancelled.';
    case 'cannot_reject':
      return 'Could not decline this ride. Try again or contact support.';
    case 'cannot_start':
      return 'You cannot start this trip in its current state.';
    case 'cannot_complete':
      return 'You cannot complete this trip in its current state.';
    case 'invalid_status':
      return 'That status change is not allowed right now.';
    case 'invalid_ride':
      return 'This ride is no longer valid. Refresh the list.';
    case 'cannot_confirm_payment':
      return 'Payment cannot be confirmed yet. The rider may still be entering details, '
          'or this trip is not in the right state.';
    default:
      return code;
  }
}

/// Driver operations: availability, incoming offers, active trip, history, earnings.
class DriverTabScreen extends StatefulWidget {
  const DriverTabScreen({super.key, this.isActive = false});

  /// Whether the Drive tab is the selected bottom-nav destination.
  final bool isActive;

  @override
  State<DriverTabScreen> createState() => _DriverTabScreenState();
}

class _DriverTabScreenState extends State<DriverTabScreen>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  bool _loading = false;
  String? _error;
  Map<String, dynamic>? _active;
  List<Map<String, dynamic>> _incoming = [];
  List<Map<String, dynamic>> _history = [];
  Map<String, dynamic>? _earnings;
  int? _busyRideId;
  Timer? _pollTimer;
  Timer? _locationTimer;

  @override
  void initState() {
    super.initState();
    if (widget.isActive) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _load();
      });
    }
    _syncPollTimer();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _syncLocationBroadcastTimer();
    });
  }

  @override
  void didUpdateWidget(DriverTabScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!oldWidget.isActive && widget.isActive) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _load();
      });
    }
    if (oldWidget.isActive != widget.isActive) {
      _syncPollTimer();
      _syncLocationBroadcastTimer();
    }
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _locationTimer?.cancel();
    super.dispose();
  }

  void _syncPollTimer() {
    _pollTimer?.cancel();
    _pollTimer = null;
    if (!widget.isActive) return;
    _pollTimer = Timer.periodic(const Duration(seconds: 25), (_) {
      if (mounted) _load(silent: true);
    });
  }

  void _syncLocationBroadcastTimer() {
    _locationTimer?.cancel();
    _locationTimer = null;
    if (!widget.isActive || !mounted) return;
    final auth = AuthScope.of(context);
    final d = auth.driverProfile;
    if (d == null || auth.sessionToken == null) return;
    if (d.availability != 'online' && d.availability != 'busy') return;
    _locationTimer = Timer.periodic(const Duration(seconds: 32), (_) {
      unawaited(_pushDriverLocation());
    });
    unawaited(_pushDriverLocation());
  }

  Future<void> _pushDriverLocation() async {
    if (!mounted) return;
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    final d = auth.driverProfile;
    if (token == null || d == null) return;
    if (d.availability != 'online' && d.availability != 'busy') return;
    final api = ApiScope.of(context);
    try {
      var perm = await Permission.locationWhenInUse.status;
      if (!perm.isGranted) {
        perm = await Permission.locationWhenInUse.request();
      }
      if (!perm.isGranted || !mounted) return;
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
          timeLimit: Duration(seconds: 12),
        ),
      );
      if (!mounted) return;
      await api.postDriverLocation(
        bearerToken: token,
        latitude: pos.latitude,
        longitude: pos.longitude,
      );
    } catch (_) {}
  }

  Future<void> _load({bool silent = false}) async {
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null || auth.driverProfile == null) {
      if (mounted) {
        setState(() {
          _incoming = [];
          _history = [];
          _active = null;
          _earnings = null;
          _error = null;
          _loading = false;
        });
      }
      return;
    }

    if (silent) {
      if (mounted) setState(() => _error = null);
    } else {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    final api = ApiScope.of(context);
    try {
      final results = await Future.wait([
        api.getDriverRidesIncoming(token),
        api.getDriverRidesActive(token),
        api.getDriverRidesHistory(token),
        api.getDriverEarningsSummary(token),
      ]);

      final incRaw = results[0]['rides'];
      final actRaw = results[1]['ride'];
      final histRaw = results[2]['rides'];

      final incoming = <Map<String, dynamic>>[];
      if (incRaw is List) {
        for (final e in incRaw) {
          if (e is Map<String, dynamic>) incoming.add(e);
        }
      }
      Map<String, dynamic>? active;
      if (actRaw is Map<String, dynamic>) {
        active = actRaw;
      }
      final history = <Map<String, dynamic>>[];
      if (histRaw is List) {
        for (final e in histRaw) {
          if (e is Map<String, dynamic>) history.add(e);
        }
      }

      if (mounted) {
        setState(() {
          _incoming = incoming;
          _active = active;
          _history = history;
          _earnings = results[3];
        });
      }
      await auth.refreshProfile();
      if (mounted) _syncLocationBroadcastTimer();
    } on ApiException catch (e) {
      if (!mounted) return;
      if (silent) {
        if (e.statusCode == 403 && e.message == 'not_a_driver') {
          await auth.refreshProfile();
        }
      } else {
        setState(() => _error = _mapDriverApiMessage(e.message));
      }
    } catch (e) {
      if (mounted && !silent) setState(() => _error = e.toString());
    } finally {
      if (mounted && !silent) setState(() => _loading = false);
    }
  }

  int? _rideId(Map<String, dynamic> r) {
    final id = r['id'];
    if (id is int) return id;
    if (id is num) return id.toInt();
    return int.tryParse('$id');
  }

  String _addressLine(Map<String, dynamic> ride, String key) {
    final block = ride[key];
    if (block is Map) {
      final a = block['address']?.toString().trim();
      if (a != null && a.isNotEmpty) return a;
    }
    return '—';
  }

  Future<void> _setAvailability(String status) async {
    final auth = AuthScope.of(context);
    final api = ApiScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    try {
      await api.postDriverAvailability(
        bearerToken: token,
        status: status,
      );
      await auth.refreshProfile();
      if (mounted) {
        _syncLocationBroadcastTimer();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Status: $status')),
        );
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_mapDriverApiMessage(e.message))),
        );
      }
    }
  }

  Future<void> _accept(int rideId) async {
    final auth = AuthScope.of(context);
    final api = ApiScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    setState(() => _busyRideId = rideId);
    try {
      await api.postDriverRideAccept(token, rideId);
      await auth.refreshProfile();
      if (mounted) await _load();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_mapDriverApiMessage(e.message))),
        );
      }
    } finally {
      if (mounted) setState(() => _busyRideId = null);
    }
  }

  Future<void> _reject(int rideId) async {
    final auth = AuthScope.of(context);
    final api = ApiScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    setState(() => _busyRideId = rideId);
    try {
      await api.postDriverRideReject(token, rideId);
      await auth.refreshProfile();
      if (mounted) await _load();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_mapDriverApiMessage(e.message))),
        );
      }
    } finally {
      if (mounted) setState(() => _busyRideId = null);
    }
  }

  Future<void> _start(int rideId) async {
    final auth = AuthScope.of(context);
    final api = ApiScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    setState(() => _busyRideId = rideId);
    try {
      await api.postDriverRideStart(token, rideId);
      if (mounted) await _load();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_mapDriverApiMessage(e.message))),
        );
      }
    } finally {
      if (mounted) setState(() => _busyRideId = null);
    }
  }

  Future<void> _complete(int rideId) async {
    final auth = AuthScope.of(context);
    final api = ApiScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    setState(() => _busyRideId = rideId);
    try {
      await api.postDriverRideComplete(token, rideId);
      await auth.refreshProfile();
      if (mounted) await _load();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_mapDriverApiMessage(e.message))),
        );
      }
    } finally {
      if (mounted) setState(() => _busyRideId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final theme = Theme.of(context);
    final auth = AuthScope.of(context);
    final driver = auth.driverProfile;

    if (!auth.isSignedIn || driver == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(
            'Driver tools appear when your account is linked to an active fleet driver.',
            style: theme.textTheme.bodyLarge?.copyWith(
              color: AppColors.secondary.withValues(alpha: 0.7),
            ),
            textAlign: TextAlign.center,
          ),
        ),
      );
    }

    final availability = driver.availability;

    return ColoredBox(
      color: AppColors.surfaceMuted,
      child: RefreshIndicator(
        onRefresh: _load,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
                child: Text(
                  'Drive',
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
            if (_loading && _incoming.isEmpty && _active == null)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null)
              SliverFillRemaining(
                hasScrollBody: false,
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: _load,
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 32),
                sliver: SliverList(
                  delegate: SliverChildListDelegate([
                    _card(
                      context,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Signed in as driver',
                            style: theme.textTheme.labelSmall?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: AppColors.secondary.withValues(alpha: 0.45),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            driver.fullName.isNotEmpty
                                ? driver.fullName
                                : 'Driver #${driver.fleetDriverId}',
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Icon(
                                Icons.circle,
                                size: 12,
                                color: switch (availability) {
                                  'online' => Colors.green.shade600,
                                  'busy' => Colors.orange.shade700,
                                  _ => AppColors.secondary.withValues(
                                    alpha: 0.35,
                                  ),
                                },
                              ),
                              const SizedBox(width: 8),
                              Text(
                                'Availability: $availability',
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                          if (availability == 'busy') ...[
                            const SizedBox(height: 8),
                            Text(
                              'You are on a trip or have an accepted ride. Go online again after you complete the ride.',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: AppColors.secondary.withValues(
                                  alpha: 0.6,
                                ),
                              ),
                            ),
                          ],
                          const SizedBox(height: 16),
                          Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              FilledButton.tonal(
                                onPressed: availability == 'online'
                                    ? null
                                    : () => _setAvailability('online'),
                                child: const Text('Go online'),
                              ),
                              OutlinedButton(
                                onPressed: availability == 'offline'
                                    ? null
                                    : () => _setAvailability('offline'),
                                child: const Text('Go offline'),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    if (_earnings != null) ...[
                      const SizedBox(height: 16),
                      _card(
                        context,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Earnings (completed trips)',
                              style: theme.textTheme.labelSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                                color: AppColors.secondary.withValues(
                                  alpha: 0.45,
                                ),
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              '${_earnings!['currency'] ?? ''} ${_earnings!['grossFareTotal'] ?? '—'}',
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${_earnings!['completedTrips'] ?? 0} trips',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: AppColors.secondary.withValues(
                                  alpha: 0.55,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    if (_active != null) ...[
                      const SizedBox(height: 16),
                      _rideCard(
                        context,
                        title: 'Active trip',
                        ride: _active!,
                        trailing: _buildActiveActions(_active!),
                      ),
                    ],
                    const SizedBox(height: 20),
                    Text(
                      'Incoming requests',
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    if (_incoming.isEmpty)
                      Text(
                        'No pending requests. Stay online to receive offers.',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: AppColors.secondary.withValues(alpha: 0.6),
                        ),
                      )
                    else
                      ..._incoming.map((r) {
                        final id = _rideId(r);
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _rideCard(
                            context,
                            title: id != null ? 'Ride #$id' : 'Ride',
                            ride: r,
                            trailing: id == null
                                ? null
                                : Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      TextButton(
                                        onPressed: _busyRideId != null
                                            ? null
                                            : () => _reject(id),
                                        child: const Text('Decline'),
                                      ),
                                      const SizedBox(width: 4),
                                      FilledButton(
                                        onPressed: _busyRideId != null
                                            ? null
                                            : () => _accept(id),
                                        child: _busyRideId == id
                                            ? const SizedBox(
                                                width: 18,
                                                height: 18,
                                                child:
                                                    CircularProgressIndicator(
                                                  strokeWidth: 2,
                                                ),
                                              )
                                            : const Text('Accept'),
                                      ),
                                    ],
                                  ),
                          ),
                        );
                      }),
                    const SizedBox(height: 20),
                    Text(
                      'Trip history',
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    if (_history.isEmpty)
                      Text(
                        'Completed and cancelled trips you drove will appear here.',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: AppColors.secondary.withValues(alpha: 0.6),
                        ),
                      )
                    else
                      ..._history.take(15).map((r) {
                        final id = _rideId(r);
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _rideCard(
                            context,
                            title: id != null
                                ? 'Ride #$id · ${r['status'] ?? ''}'
                                : '${r['status'] ?? ''}',
                            ride: r,
                            footer: _paymentConfirmFooter(r),
                          ),
                        );
                      }),
                  ]),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget? _paymentConfirmFooter(Map<String, dynamic> r) {
    final id = _rideId(r);
    if (id == null) return null;
    if ('${r['status']}' != 'completed') return null;
    final pay = r['payment'];
    if (pay is! Map || '${pay['status']}' != 'submitted') return null;
    return SizedBox(
      width: double.infinity,
      child: FilledButton(
        onPressed: _busyRideId != null
            ? null
            : () => _confirmPaymentReceived(id),
        child: _busyRideId == id
            ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Text('Confirm payment received'),
      ),
    );
  }

  Future<void> _confirmPaymentReceived(int rideId) async {
    if (!mounted) return;
    final auth = AuthScope.of(context);
    final api = ApiScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    setState(() => _busyRideId = rideId);
    try {
      await api.postDriverConfirmPayment(token, rideId);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Payment marked as received.')),
        );
      }
      await _load();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(_mapDriverApiMessage(e.message)),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _busyRideId = null);
    }
  }

  Widget? _buildActiveActions(Map<String, dynamic> ride) {
    final id = _rideId(ride);
    if (id == null) return null;
    final st = '${ride['status'] ?? ''}';
    if (st == 'accepted') {
      return FilledButton(
        onPressed: _busyRideId != null ? null : () => _start(id),
        child: _busyRideId == id
            ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Text('Start trip'),
      );
    }
    if (st == 'in_progress') {
      return FilledButton(
        onPressed: _busyRideId != null ? null : () => _complete(id),
        child: _busyRideId == id
            ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Text('Complete trip'),
      );
    }
    return null;
  }

  Widget _card(BuildContext context, {required Widget child}) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: AppColors.secondary.withValues(alpha: 0.04),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: child,
      ),
    );
  }

  Widget _rideCard(
    BuildContext context, {
    required String title,
    required Map<String, dynamic> ride,
    Widget? trailing,
    Widget? footer,
  }) {
    final theme = Theme.of(context);
    final pickup = _addressLine(ride, 'pickup');
    final drop = _addressLine(ride, 'dropoff');
    final pricing = ride['pricing'];
    var fare = '';
    if (pricing is Map && pricing['finalFare'] != null) {
      fare =
          '${pricing['currency'] ?? ''} ${pricing['finalFare']}'.trim();
    } else if (pricing is Map && pricing['estimatedFare'] != null) {
      fare =
          'Est. ${pricing['currency'] ?? ''} ${pricing['estimatedFare']}'
              .trim();
    }

    return _card(
      context,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  title,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              if (trailing != null) trailing,
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'Pickup: $pickup',
            style: theme.textTheme.bodySmall,
          ),
          const SizedBox(height: 4),
          Text(
            'Drop-off: $drop',
            style: theme.textTheme.bodySmall,
          ),
          if (fare.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              fare,
              style: theme.textTheme.bodySmall?.copyWith(
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          if (footer != null) ...[
            const SizedBox(height: 12),
            footer,
          ],
        ],
      ),
    );
  }
}
