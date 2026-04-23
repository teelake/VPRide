/// Dispatch / matching policy from [GET /api/v1/config/public] under `dispatch`.
class AppPublicDispatch {
  const AppPublicDispatch({
    required this.maxAutoDriverAttempts,
    required this.maxRiderDriverRejects,
    required this.tripConfirmedWhen,
  });

  final int maxAutoDriverAttempts;
  final int maxRiderDriverRejects;

  /// `driver_assigned` or `driver_accepted` — see server [RiderRideViewPresenter::tripConfirmed].
  final String tripConfirmedWhen;

  static const AppPublicDispatch fallback = AppPublicDispatch(
    maxAutoDriverAttempts: 8,
    maxRiderDriverRejects: 2,
    tripConfirmedWhen: 'driver_accepted',
  );

  factory AppPublicDispatch.fromJson(Object? raw) {
    if (raw is! Map) {
      return fallback;
    }
    final m = Map<String, dynamic>.from(raw);
    int cap(int? v, int def, {required int min, required int max}) {
      if (v == null) return def;
      if (v < min) return min;
      if (v > max) return max;
      return v;
    }

    var when = '${m['tripConfirmedWhen'] ?? 'driver_accepted'}'.trim();
    if (when != 'driver_assigned' && when != 'driver_accepted') {
      when = 'driver_accepted';
    }
    return AppPublicDispatch(
      maxAutoDriverAttempts: cap(
        _int(m['maxAutoDriverAttempts']),
        fallback.maxAutoDriverAttempts,
        min: 1,
        max: 50,
      ),
      maxRiderDriverRejects: cap(
        _int(m['maxRiderDriverRejects']),
        fallback.maxRiderDriverRejects,
        min: 0,
        max: 20,
      ),
      tripConfirmedWhen: when,
    );
  }

  static int? _int(Object? o) {
    if (o is int) {
      return o;
    }
    if (o is num) {
      return o.toInt();
    }
    if (o != null) {
      return int.tryParse(o.toString());
    }
    return null;
  }
}
