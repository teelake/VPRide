/// Copy and short summaries for rider trip tracking (matches backend [lifecyclePhase] / [eta]).
String riderTripPhaseTitle(String? phase) {
  switch (phase) {
    case 'booking':
      return 'Finding a driver';
    case 'assignment':
      return 'Driver assigned';
    case 'pickup':
      return 'Driver heading to pickup';
    case 'trip':
      return 'On trip';
    case 'completed':
      return 'Completed';
    case 'cancelled':
      return 'Cancelled';
    default:
      return 'Trip update';
  }
}

/// One-line ETA for the rider; may be null if the backend has no estimate yet.
String? riderTripEtaSummary(Map<String, dynamic>? eta) {
  if (eta == null) return null;
  final pick = eta['toPickupMinutes'];
  final drop = eta['toDropoffMinutes'];
  final route = eta['routeDurationMinutesEstimate'];
  final fresh = eta['driverLocationFresh'] == true;

  if (pick != null && pick is num) {
    final n = pick.round();
    return fresh
        ? 'About $n min to pickup (live)'
        : 'About $n min to pickup';
  }
  if (drop != null && drop is num) {
    final n = drop.round();
    return fresh
        ? 'About $n min to destination (live)'
        : 'About $n min to destination';
  }
  if (route != null && route is num) {
    return 'Typical trip ~${route.round()} min';
  }
  return null;
}

String riderTripDriverLine(Map<String, dynamic>? driver) {
  if (driver == null) return '';
  final name = '${driver['displayName'] ?? ''}'.trim();
  final vehicle = driver['vehicle'];
  String v = '';
  if (vehicle is Map && vehicle['summary'] != null) {
    v = '${vehicle['summary']}'.trim();
  }
  final rating = driver['averageRatingStars'];
  var r = '';
  if (rating != null && rating is num) {
    r = ' · ${rating.toStringAsFixed(1)} ★ avg';
  }
  if (name.isEmpty && v.isEmpty) return '';
  if (v.isEmpty) return '$name$r';
  if (name.isEmpty) return '$v$r';
  return '$name · $v$r';
}
