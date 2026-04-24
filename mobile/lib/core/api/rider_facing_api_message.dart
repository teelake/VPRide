import 'api_exception.dart';

/// SnackBar-friendly text: prefers the server [ApiException.message], and adds
/// known copy when the server only sent a machine [ApiException.errorCode].
String riderFacingApiMessage(ApiException e) {
  final code = e.errorCode;
  final m = e.message.trim();
  if (m.isNotEmpty && m != code) {
    return m;
  }
  switch (code) {
    case 'pickup_outside_service_area':
      return 'That pickup is outside our licensed service area. Try a different location or call us.';
    case 'dropoff_outside_service_area':
      return 'That drop-off is outside our licensed service area. Adjust the destination or call us.';
    case 'region_config_unavailable':
      return 'Ride area is not set up on the server yet. Please try again later or contact support.';
    case 'no_service_area_cities':
      return 'Ride area is incomplete on the server (no city centers). Please contact support.';
    case 'ride_booking_disabled':
      return 'Ride booking is turned off right now.';
    case 'maintenance':
      return m.isNotEmpty ? m : 'Service is temporarily unavailable.';
    case 'rate_limited':
      return 'Too many requests. Wait a moment and try again.';
    default:
      return m.isNotEmpty ? m : 'Something went wrong. Please try again.';
  }
}
