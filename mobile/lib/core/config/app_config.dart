import 'package:flutter/foundation.dart';

/// Non-secret build-time configuration placeholders.
///
/// **Production layout on `vpride.ca`:** the marketing site is at the domain root; the PHP API is
/// served under **`/backend/`** (e.g. `https://vpride.ca/backend` + `/api/v1/...`). That path is the
/// same “directory” as `APP_BASE_PATH=backend` on the server — it is **not** part of the Dart
/// defines; it is included in [apiBaseUrl] as the URL origin.
///
/// **Google Sign-In (PHP/MySQL backend)**
/// 1. In Google Cloud Console, create an OAuth client of type **Web application** — that
///    name only describes the credential type; the mobile app is still Android/iOS only.
/// 2. Set [googleOAuthServerClientId] to that client’s ID (`serverClientId`) so the plugin
///    returns an **ID token** your PHP API verifies (JWT signature + `aud`/`iss`/`exp`).
/// 3. Add **Android** and **iOS** OAuth clients in the same project (SHA-1, bundle ID, etc.).
///
/// Never commit real client IDs to a public repo if this becomes sensitive; use `--dart-define`
/// or a secrets file ignored by git.
abstract final class AppConfig {
  /// Canonical production API origin (**no trailing slash**): static site at `/`, API under `/backend/`.
  static const String defaultProductionApiBaseUrl = 'https://vpride.ca/backend';

  static const String _apiBaseUrlFromEnv = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: '',
  );

  /// Base URL for the PHP API (**scheme + host + `/backend` segment**, no trailing slash).
  ///
  /// - **Release** builds: if `API_BASE_URL` was not passed at compile time, this defaults to
  ///   [defaultProductionApiBaseUrl] so App Store / Play builds target production without an easy-to-miss flag.
  /// - **Debug / profile:** defaults to empty so local runs can use offline region fallbacks unless you
  ///   pass `--dart-define=API_BASE_URL=...` (e.g. `http://localhost:8080` or [defaultProductionApiBaseUrl]).
  /// - **Override:** `--dart-define=API_BASE_URL=https://staging.example.com/backend` always wins.
  static String get apiBaseUrl {
    final raw = _apiBaseUrlFromEnv.trim();
    if (raw.isNotEmpty) {
      return _trimTrailingSlashes(raw);
    }
    if (kReleaseMode) {
      return defaultProductionApiBaseUrl;
    }
    return '';
  }

  static String _trimTrailingSlashes(String s) {
    var t = s;
    while (t.endsWith('/')) {
      t = t.substring(0, t.length - 1);
    }
    return t;
  }

  /// GET path appended to [apiBaseUrl] for region/city/country config JSON.
  static const String regionConfigPath = String.fromEnvironment(
    'REGION_CONFIG_PATH',
    defaultValue: '/api/v1/config/regions',
  );

  /// Public client keys (Google server client ID, Maps key, min version).
  static const String publicConfigPath = String.fromEnvironment(
    'PUBLIC_CONFIG_PATH',
    defaultValue: '/api/v1/config/public',
  );

  /// Server / ID-token audience client ID (ends with `.apps.googleusercontent.com`;
  /// OAuth type "Web application" in Cloud Console; not a website app).
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
