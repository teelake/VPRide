/// Non-secret build-time configuration placeholders.
///
/// **Google Sign-In (PHP/MySQL backend)**
/// 1. Create an OAuth **Web application** client in Google Cloud Console.
/// 2. Set [googleOAuthServerClientId] to that client’s ID so the plugin can return an
///    **ID token** your PHP API verifies (JWT signature + `aud`/`iss`/`exp`).
/// 3. Add Android/iOS OAuth clients for the same project and complete platform setup
///    (SHA-1 for Android, `REVERSED_CLIENT_ID` / URL scheme for iOS).
///
/// Never commit real client IDs to a public repo if this becomes sensitive; use `--dart-define`
/// or a secrets file ignored by git.
abstract final class AppConfig {
  /// Base URL for the PHP API (no trailing slash), e.g. `https://api.example.com`.
  /// If empty, [RegionConfigRepository] skips the network and uses [AppRegion] fallbacks only.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: '',
  );

  /// GET path appended to [apiBaseUrl] for region/city/country config JSON.
  static const String regionConfigPath = String.fromEnvironment(
    'REGION_CONFIG_PATH',
    defaultValue: '/api/v1/config/regions',
  );

  /// Web client ID (ends with `.apps.googleusercontent.com`).
  /// Required on Android for a non-null [GoogleSignInAuthentication.idToken] in many setups.
  static const String googleOAuthServerClientId = String.fromEnvironment(
    'GOOGLE_SERVER_CLIENT_ID',
    defaultValue: '',
  );

  /// Maps SDK + Geocoding REST (restrict key in Google Cloud Console by app/API).
  static const String mapsApiKey = String.fromEnvironment(
    'MAPS_API_KEY',
    defaultValue: '',
  );
}
