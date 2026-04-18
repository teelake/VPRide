import 'dart:async';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

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
  final TextEditingController _paymentNote = TextEditingController();
  final ImagePicker _imagePicker = ImagePicker();
  String? _payMethod;
  String? _uploadedProofUrl;
  bool _payBusy = false;
  bool _proofBusy = false;

  @override
  void dispose() {
    _poll?.cancel();
    _feedback.dispose();
    _paymentNote.dispose();
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
          final pay = ride['payment'];
          if (pay is Map) {
            final url = pay['proofUrl']?.toString();
            if (url != null && url.trim().isNotEmpty) {
              _uploadedProofUrl = url.trim();
            }
          }
        });
      }
      final st = '${_ride?['status'] ?? ''}';
      final payment = _ride?['payment'];
      final paySt = payment is Map ? '${payment['status'] ?? ''}' : '';
      if (st == 'cancelled') {
        _poll?.cancel();
        _poll = null;
      } else if (st == 'completed') {
        if (payment is! Map || paySt == 'paid') {
          _poll?.cancel();
          _poll = null;
        }
      }
    } on ApiException catch (e) {
      if (mounted && !silent) setState(() => _error = e.message);
    } catch (e) {
      if (mounted && !silent) setState(() => _error = e.toString());
    } finally {
      if (mounted && !silent) setState(() => _loading = false);
    }
  }

  Future<void> _pickPaymentProof() async {
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    final api = ApiScope.of(context);
    final x = await _imagePicker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 2048,
      maxHeight: 2048,
      imageQuality: 85,
    );
    if (x == null || !mounted) return;
    setState(() => _proofBusy = true);
    try {
      final bytes = await x.readAsBytes();
      if (!mounted) return;
      final name = x.name.trim().isNotEmpty ? x.name : 'proof.jpg';
      final res = await api.postRidePaymentProof(
        bearerToken: token,
        rideId: widget.rideId,
        fileBytes: bytes,
        filename: name,
      );
      if (!mounted) return;
      final url = res['proofUrl']?.toString();
      if (url != null && url.isNotEmpty) {
        setState(() => _uploadedProofUrl = url);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Proof uploaded.')),
        );
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message)),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _proofBusy = false);
    }
  }

  Future<void> _submitOfflinePayment() async {
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null) return;
    final messenger = ScaffoldMessenger.of(context);
    final method = _payMethod;
    if (method == null) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Choose how you paid.')),
      );
      return;
    }
    if (method == 'bank_transfer' &&
        (_uploadedProofUrl == null || _uploadedProofUrl!.trim().isEmpty)) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Upload a screenshot or photo of your transfer.'),
        ),
      );
      return;
    }
    setState(() => _payBusy = true);
    try {
      final res = await ApiScope.of(context).postRidePaymentOffline(
        bearerToken: token,
        rideId: widget.rideId,
        method: method,
        proofUrl: _uploadedProofUrl,
        referenceNote: _paymentNote.text.trim().isEmpty
            ? null
            : _paymentNote.text.trim(),
      );
      if (!mounted) return;
      final updated = res['ride'];
      if (updated is Map<String, dynamic>) {
        setState(() => _ride = updated);
      } else {
        await _fetch(silent: true);
      }
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Payment details sent. Your driver will confirm when received.'),
        ),
      );
    } on ApiException catch (e) {
      if (mounted) messenger.showSnackBar(SnackBar(content: Text(e.message)));
    } catch (e) {
      if (mounted) messenger.showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _payBusy = false);
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
                            ride['payment'] is Map) ...[
                          const SizedBox(height: 24),
                          Text(
                            'Pay driver (offline)',
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Builder(
                            builder: (context) {
                              final p = ride['payment'] as Map<String, dynamic>;
                              final ps = '${p['status'] ?? 'pending'}';
                              if (ps == 'paid') {
                                return Card(
                                  color: Colors.green.shade50,
                                  child: Padding(
                                    padding: const EdgeInsets.all(14),
                                    child: Row(
                                      children: [
                                        Icon(
                                          Icons.check_circle_rounded,
                                          color: Colors.green.shade700,
                                        ),
                                        const SizedBox(width: 10),
                                        Expanded(
                                          child: Text(
                                            'Payment recorded as received.',
                                            style: theme.textTheme.bodyMedium
                                                ?.copyWith(
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                );
                              }
                              if (ps == 'submitted') {
                                return Card(
                                  color: Colors.amber.shade50,
                                  child: Padding(
                                    padding: const EdgeInsets.all(14),
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          'Waiting for driver to confirm payment',
                                          style: theme.textTheme.titleSmall
                                              ?.copyWith(
                                            fontWeight: FontWeight.w800,
                                          ),
                                        ),
                                        const SizedBox(height: 6),
                                        Text(
                                          'Method: ${_methodLabel(p['method']?.toString())}',
                                          style: theme.textTheme.bodySmall,
                                        ),
                                        if (p['proofUrl'] != null &&
                                            '${p['proofUrl']}'.isNotEmpty)
                                          TextButton.icon(
                                            onPressed: () async {
                                              final u = Uri.tryParse(
                                                '${p['proofUrl']}',
                                              );
                                              if (u != null &&
                                                  await canLaunchUrl(u)) {
                                                await launchUrl(
                                                  u,
                                                  mode: LaunchMode
                                                      .externalApplication,
                                                );
                                              }
                                            },
                                            icon: const Icon(Icons.open_in_new),
                                            label: const Text('Open proof'),
                                          ),
                                      ],
                                    ),
                                  ),
                                );
                              }
                              return Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Text(
                                    'How did you pay?',
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  Wrap(
                                    spacing: 8,
                                    runSpacing: 8,
                                    children: [
                                      ChoiceChip(
                                        label: const Text('Cash'),
                                        selected: _payMethod == 'cash',
                                        onSelected: (_) => setState(
                                          () => _payMethod = 'cash',
                                        ),
                                      ),
                                      ChoiceChip(
                                        label: const Text('POS / card'),
                                        selected: _payMethod == 'pos',
                                        onSelected: (_) => setState(
                                          () => _payMethod = 'pos',
                                        ),
                                      ),
                                      ChoiceChip(
                                        label: const Text('Bank transfer'),
                                        selected:
                                            _payMethod == 'bank_transfer',
                                        onSelected: (_) => setState(
                                          () => _payMethod = 'bank_transfer',
                                        ),
                                      ),
                                    ],
                                  ),
                                  if (_payMethod == 'bank_transfer') ...[
                                    const SizedBox(height: 12),
                                    OutlinedButton.icon(
                                      onPressed: _proofBusy
                                          ? null
                                          : _pickPaymentProof,
                                      icon: _proofBusy
                                          ? const SizedBox(
                                              width: 18,
                                              height: 18,
                                              child: CircularProgressIndicator(
                                                strokeWidth: 2,
                                              ),
                                            )
                                          : const Icon(Icons.upload_rounded),
                                      label: Text(
                                        _uploadedProofUrl != null
                                            ? 'Proof attached ✓'
                                            : 'Upload transfer proof',
                                      ),
                                    ),
                                  ],
                                  const SizedBox(height: 12),
                                  TextField(
                                    controller: _paymentNote,
                                    decoration: const InputDecoration(
                                      labelText:
                                          'Reference / note (optional)',
                                      border: OutlineInputBorder(),
                                      isDense: true,
                                    ),
                                    textCapitalization:
                                        TextCapitalization.sentences,
                                  ),
                                  const SizedBox(height: 12),
                                  FilledButton(
                                    onPressed:
                                        _payBusy ? null : _submitOfflinePayment,
                                    child: _payBusy
                                        ? const SizedBox(
                                            width: 22,
                                            height: 22,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                              color: Colors.white,
                                            ),
                                          )
                                        : const Text('Submit payment details'),
                                  ),
                                ],
                              );
                            },
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

  String _methodLabel(String? m) {
    switch (m) {
      case 'cash':
        return 'Cash';
      case 'pos':
        return 'POS / card';
      case 'bank_transfer':
        return 'Bank transfer';
      default:
        return m ?? '—';
    }
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
