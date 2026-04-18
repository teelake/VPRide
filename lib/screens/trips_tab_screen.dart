import 'package:flutter/material.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_scope.dart';
import '../core/auth/auth_scope.dart';
import '../core/theme/app_colors.dart';
import 'trip_detail_screen.dart';

/// Lists past and upcoming rides for the signed-in rider.
class TripsTabScreen extends StatefulWidget {
  const TripsTabScreen({super.key});

  @override
  State<TripsTabScreen> createState() => _TripsTabScreenState();
}

class _TripsTabScreenState extends State<TripsTabScreen>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  static const int _pageSize = 30;

  bool _loading = false;
  bool _loadingMore = false;
  String? _error;
  List<Map<String, dynamic>> _rides = [];
  int? _nextBeforeId;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _load();
    });
  }

  Future<void> _load({bool append = false}) async {
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null || !auth.isSignedIn) {
      if (mounted) {
        setState(() {
          _rides = [];
          _nextBeforeId = null;
          _error = null;
          _loading = false;
          _loadingMore = false;
        });
      }
      return;
    }
    if (append) {
      if (_nextBeforeId == null || _loadingMore) return;
      setState(() => _loadingMore = true);
    } else {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final res = await ApiScope.of(context).getRidesMine(
        token,
        limit: _pageSize,
        beforeId: append ? _nextBeforeId : null,
      );
      final raw = res['rides'];
      final list = <Map<String, dynamic>>[];
      if (raw is List) {
        for (final e in raw) {
          if (e is Map<String, dynamic>) list.add(e);
        }
      }
      final next = res['nextBeforeId'];
      int? nextId;
      if (next is int) {
        nextId = next;
      } else if (next is num) {
        nextId = next.toInt();
      }
      if (mounted) {
        setState(() {
          if (append) {
            _rides = [..._rides, ...list];
          } else {
            _rides = list;
          }
          _nextBeforeId = nextId;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
          _loadingMore = false;
        });
      }
    }
  }

  String _legLabel(String? leg) {
    switch (leg) {
      case 'outbound':
        return 'Outbound';
      case 'return':
        return 'Return';
      default:
        return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final auth = AuthScope.of(context);
    final theme = Theme.of(context);

    if (!auth.isSignedIn) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(
            'Sign in to see your trips.',
            style: theme.textTheme.bodyLarge?.copyWith(
              color: AppColors.secondary.withValues(alpha: 0.7),
            ),
            textAlign: TextAlign.center,
          ),
        ),
      );
    }

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
                  'Trips',
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
            if (_loading && _rides.isEmpty)
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
                        Text(
                          _error!,
                          textAlign: TextAlign.center,
                        ),
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
            else if (_rides.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: Center(
                  child: Text(
                    'No trips yet. Book a ride from the Map tab.',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: AppColors.secondary.withValues(alpha: 0.65),
                    ),
                    textAlign: TextAlign.center,
                  ),
                ),
              )
            else
              SliverList(
                delegate: SliverChildBuilderDelegate(
                  (context, i) {
                    final r = _rides[i];
                    final id = r['id'];
                    final status = '${r['status'] ?? ''}';
                    final leg = _legLabel(r['tripLeg']?.toString());
                    final sched = r['scheduledPickupAt']?.toString();
                    final pricing = r['pricing'];
                    var fare = '';
                    if (pricing is Map && pricing['finalFare'] != null) {
                      final cur = '${pricing['currency'] ?? ''}'.trim();
                      fare = ' · $cur ${pricing['finalFare']}';
                    }
                    final subtitle = StringBuffer()
                      ..write(status)
                      ..write(leg.isNotEmpty ? ' · $leg' : '')
                      ..write(fare);
                    if (sched != null && sched.isNotEmpty) {
                      subtitle.write(' · $sched');
                    }
                    return ListTile(
                      tileColor: Colors.white,
                      title: Text('Ride #$id'),
                      subtitle: Text(subtitle.toString()),
                      trailing: const Icon(Icons.chevron_right_rounded),
                      onTap: () async {
                        final rideId = id is int ? id : int.tryParse('$id');
                        if (rideId == null) return;
                        await Navigator.of(context).push<void>(
                          MaterialPageRoute<void>(
                            builder: (ctx) => TripDetailScreen(rideId: rideId),
                          ),
                        );
                        if (mounted) _load();
                      },
                    );
                  },
                  childCount:
                      _rides.length + (_nextBeforeId != null ? 1 : 0),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
