import 'dart:async';

import 'package:flutter/material.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_scope.dart';
import '../core/auth/auth_scope.dart';
import '../core/theme/app_colors.dart';
import '../core/trip/rider_trip_copy.dart';

/// Live status for a single ride + rating after completion.
class TripDetailScreen extends StatefulWidget {
  const TripDetailScreen({super.key, required this.rideId});

  final int rideId;

  @override
  State<TripDetailScreen> createState() => _TripDetailScreenState();
}

class _TripDetailScreenState extends State<TripDetailScreen> {
  Map<String, dynamic>? _ride;
  bool _loading = true;
  String? _error;
  Timer? _poll;
  bool _ratingBusy = false;
  int _stars = 5;
  final TextEditingController _feedback = TextEditingController();

  @override
  void dispose() {
    _poll?.cancel();
    _feedback.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _fetch();
      _poll = Timer.periodic(const Duration(seconds: 8), (_) => _fetch(silent: true));
    });
  }

  Future<void> _fetch({bool silent = false}) async {
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    if (!silent && mounted) setState(() => _loading = true);
    try {
      final res = await ApiScope.of(context).getRide(token, widget.rideId);
      final ride = res['ride'];
      if (ride is Map<String, dynamic> && mounted) {
        setState(() {
          _ride = ride;
          _error = null;
        });
      }
      final st = '${_ride?['status'] ?? ''}';
      if (st == 'completed' || st == 'cancelled') {
        _poll?.cancel();
        _poll = null;
      }
    } on ApiException catch (e) {
      if (mounted && !silent) setState(() => _error = e.message);
    } catch (e) {
      if (mounted && !silent) setState(() => _error = e.toString());
    } finally {
      if (mounted && !silent) setState(() => _loading = false);
    }
  }

  Future<void> _submitRating() async {
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _ratingBusy = true);
    try {
      await ApiScope.of(context).postRideRating(
        bearerToken: token,
        rideId: widget.rideId,
        stars: _stars,
        feedback: _feedback.text.trim().isEmpty ? null : _feedback.text.trim(),
      );
      if (!mounted) return;
      messenger.showSnackBar(
        const SnackBar(content: Text('Thanks for your feedback.')),
      );
      await _fetch();
    } on ApiException catch (e) {
      if (mounted) messenger.showSnackBar(SnackBar(content: Text(e.message)));
    } catch (e) {
      if (mounted) messenger.showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _ratingBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final ride = _ride;

    return Scaffold(
      appBar: AppBar(
        title: Text('Ride #${widget.rideId}'),
      ),
      body: ColoredBox(
        color: AppColors.surfaceMuted,
        child: _loading && ride == null
            ? const Center(child: CircularProgressIndicator())
            : _error != null && ride == null
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(_error!, textAlign: TextAlign.center),
                          const SizedBox(height: 16),
                          FilledButton(
                            onPressed: () => _fetch(),
                            child: const Text('Retry'),
                          ),
                        ],
                      ),
                    ),
                  )
                : ListView(
                    padding: const EdgeInsets.all(20),
                    children: [
                      if (ride != null) ...[
                        Text(
                          '${ride['status'] ?? ''}',
                          style: theme.textTheme.headlineSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        if (ride['lifecyclePhase'] != null) ...[
                          const SizedBox(height: 8),
                          Text(
                            riderTripPhaseTitle(
                              ride['lifecyclePhase']?.toString(),
                            ),
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: AppColors.secondary.withValues(alpha: 0.85),
                            ),
                          ),
                        ],
                        Builder(
                          builder: (context) {
                            final d = ride['driver'];
                            final line = riderTripDriverLine(
                              d is Map<String, dynamic> ? d : null,
                            );
                            if (line.isEmpty) return const SizedBox.shrink();
                            return Padding(
                              padding: const EdgeInsets.only(top: 12),
                              child: Card(
                                child: Padding(
                                  padding: const EdgeInsets.all(14),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'Your driver',
                                        style: theme.textTheme.labelSmall?.copyWith(
                                          fontWeight: FontWeight.w800,
                                          color: AppColors.secondary
                                              .withValues(alpha: 0.5),
                                        ),
                                      ),
                                      const SizedBox(height: 6),
                                      Text(
                                        line,
                                        style: theme.textTheme.bodyLarge?.copyWith(
                                          fontWeight: FontWeight.w600,
                                          height: 1.3,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                        Builder(
                          builder: (context) {
                            final e = ride['eta'];
                            final summary = riderTripEtaSummary(
                              e is Map<String, dynamic> ? e : null,
                            );
                            if (summary == null) return const SizedBox.shrink();
                            return Padding(
                              padding: const EdgeInsets.only(top: 12),
                              child: Card(
                                color: AppColors.primary.withValues(alpha: 0.12),
                                child: Padding(
                                  padding: const EdgeInsets.all(14),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Icon(
                                        Icons.schedule_rounded,
                                        color: AppColors.secondary
                                            .withValues(alpha: 0.75),
                                      ),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: Text(
                                          summary,
                                          style: theme.textTheme.bodyMedium
                                              ?.copyWith(
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                        if (ride['tripLeg'] != null &&
                            '${ride['tripLeg']}' != 'single')
                          Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Text(
                              'Leg: ${ride['tripLeg']}',
                              style: theme.textTheme.bodyMedium,
                            ),
                          ),
                        if (ride['scheduledPickupAt'] != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(
                              'Scheduled pickup (UTC): ${ride['scheduledPickupAt']}',
                              style: theme.textTheme.bodySmall,
                            ),
                          ),
                        if (ride['distanceKm'] != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Text(
                              'Distance: ${ride['distanceKm']} km',
                              style: theme.textTheme.bodySmall,
                            ),
                          ),
                        const SizedBox(height: 16),
                        _locCard(
                          theme,
                          'Pickup',
                          ride['pickup'] as Map<String, dynamic>?,
                        ),
                        const SizedBox(height: 12),
                        _locCard(
                          theme,
                          'Destination',
                          ride['dropoff'] as Map<String, dynamic>?,
                        ),
                        const SizedBox(height: 16),
                        if (ride['pricing'] is Map) ...[
                          Text(
                            'Fare',
                            style: theme.textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            _pricingLine(ride['pricing'] as Map),
                            style: theme.textTheme.bodyMedium,
                          ),
                        ],
                        if (ride['status'] == 'completed' &&
                            ride['ratingStars'] == null) ...[
                          const SizedBox(height: 28),
                          Text(
                            'Rate this trip',
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Row(
                            children: List.generate(5, (i) {
                              final n = i + 1;
                              return IconButton(
                                onPressed: () => setState(() => _stars = n),
                                icon: Icon(
                                  n <= _stars
                                      ? Icons.star_rounded
                                      : Icons.star_outline_rounded,
                                  color: Colors.amber.shade700,
                                  size: 32,
                                ),
                              );
                            }),
                          ),
                          TextField(
                            controller: _feedback,
                            maxLines: 3,
                            decoration: const InputDecoration(
                              labelText: 'Feedback (optional)',
                              border: OutlineInputBorder(),
                              alignLabelWithHint: true,
                            ),
                          ),
                          const SizedBox(height: 12),
                          FilledButton(
                            onPressed: _ratingBusy ? null : _submitRating,
                            child: _ratingBusy
                                ? const SizedBox(
                                    width: 22,
                                    height: 22,
                                    child: CircularProgressIndicator(strokeWidth: 2),
                                  )
                                : const Text('Submit rating'),
                          ),
                        ],
                        if (ride['ratingStars'] != null) ...[
                          const SizedBox(height: 24),
                          Text(
                            'Your rating: ${ride['ratingStars']} ★',
                            style: theme.textTheme.titleSmall,
                          ),
                          if (ride['feedbackText'] != null &&
                              '${ride['feedbackText']}'.trim().isNotEmpty)
                            Padding(
                              padding: const EdgeInsets.only(top: 8),
                              child: Text('${ride['feedbackText']}'),
                            ),
                        ],
                      ],
                    ],
                  ),
      ),
    );
  }

  String _pricingLine(Map p) {
    final cur = '${p['currency'] ?? ''}'.trim();
    final est = p['estimatedFare'];
    final fin = p['finalFare'];
    final disc = p['promoDiscount'];
    return 'Estimate $cur $est · Discount $cur $disc · You pay $cur $fin';
  }

  Widget _locCard(ThemeData theme, String title, Map<String, dynamic>? m) {
    final addr = m == null ? null : m['address']?.toString();
    final lat = m == null ? null : m['latitude'];
    final lng = m == null ? null : m['longitude'];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: theme.textTheme.labelSmall?.copyWith(
                fontWeight: FontWeight.w800,
                color: AppColors.secondary.withValues(alpha: 0.5),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              (addr != null && addr.isNotEmpty)
                  ? addr
                  : (lat != null && lng != null)
                      ? '$lat, $lng'
                      : '—',
              style: theme.textTheme.bodyMedium,
            ),
          ],
        ),
      ),
    );
  }
}
