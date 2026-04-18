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

  bool _loading = false;
  String? _error;
  List<Map<String, dynamic>> _rides = [];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _load();
    });
  }

  Future<void> _load() async {
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null || !auth.isSignedIn) {
      if (mounted) {
        setState(() {
          _rides = [];
          _error = null;
          _loading = false;
        });
      }
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiScope.of(context).getRidesMine(token);
      final raw = res['rides'];
      final list = <Map<String, dynamic>>[];
      if (raw is List) {
        for (final e in raw) {
          if (e is Map<String, dynamic>) list.add(e);
        }
      }
      if (mounted) setState(() => _rides = list);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
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
                  childCount: _rides.length,
                ),
              ),
          ],
        ),
      ),
    );
  }
}
