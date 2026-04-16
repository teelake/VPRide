/// Remote feature flags from [GET /api/v1/config/public] under `features`.
///
/// Defaults match a fully-enabled app when the server omits `features`.
class AppPublicFeatures {
  const AppPublicFeatures({
    required this.rideBookingEnabled,
    required this.promoBannerEnabled,
    required this.maintenanceMode,
    required this.maintenanceMessage,
    required this.helpCenterUrl,
    required this.requireSignInForHome,
  });

  final bool rideBookingEnabled;
  final bool promoBannerEnabled;
  final bool maintenanceMode;
  final String maintenanceMessage;
  final String helpCenterUrl;
  final bool requireSignInForHome;

  static const AppPublicFeatures fallback = AppPublicFeatures(
    rideBookingEnabled: true,
    promoBannerEnabled: false,
    maintenanceMode: false,
    maintenanceMessage: '',
    helpCenterUrl: '',
    requireSignInForHome: true,
  );

  factory AppPublicFeatures.fromJson(Object? raw) {
    if (raw is! Map) {
      return fallback;
    }
    final m = Map<String, dynamic>.from(raw);
    return AppPublicFeatures(
      rideBookingEnabled: _bool(m['rideBookingEnabled'], fallback: true),
      promoBannerEnabled: _bool(m['promoBannerEnabled'], fallback: false),
      maintenanceMode: _bool(m['maintenanceMode'], fallback: false),
      maintenanceMessage: '${m['maintenanceMessage'] ?? ''}'.trim(),
      helpCenterUrl: '${m['helpCenterUrl'] ?? ''}'.trim(),
      requireSignInForHome: _bool(m['requireSignInForHome'], fallback: true),
    );
  }

  static bool _bool(Object? v, {required bool fallback}) {
    if (v == null) return fallback;
    if (v is bool) return v;
    final s = '$v'.toLowerCase().trim();
    if (s == 'true' || s == '1' || s == 'yes') return true;
    if (s == 'false' || s == '0' || s == 'no') return false;
    return fallback;
  }
}
