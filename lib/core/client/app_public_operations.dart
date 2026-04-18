/// Operations policy from [GET /api/v1/config/public] under `operations`.
class AppPublicOperations {
  const AppPublicOperations({required this.riderCancellationFeeAmount});

  /// Fixed cancellation fee (same currency as platform ride pricing).
  final double riderCancellationFeeAmount;

  static const AppPublicOperations fallback = AppPublicOperations(
    riderCancellationFeeAmount: 0,
  );

  factory AppPublicOperations.fromJson(Object? raw) {
    if (raw is! Map) {
      return fallback;
    }
    final m = Map<String, dynamic>.from(raw);
    final f = m['riderCancellationFeeAmount'];
    var v = 0.0;
    if (f is num) {
      v = f.toDouble();
    } else if (f != null) {
      v = double.tryParse(f.toString()) ?? 0.0;
    }
    if (v.isNaN || v < 0) {
      v = 0;
    }
    return AppPublicOperations(riderCancellationFeeAmount: v);
  }
}
