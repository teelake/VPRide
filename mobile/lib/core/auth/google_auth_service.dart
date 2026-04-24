import 'package:google_sign_in/google_sign_in.dart';

import '../client/client_config_repository.dart';
import '../config/app_config.dart';
import 'google_auth_result.dart';

/// Google OAuth for a **custom backend** (PHP): exchange [GoogleAuthResult.idToken] server-side.
class GoogleAuthService {
  GoogleAuthService({
    ClientConfigRepository? clientConfig,
    GoogleSignIn? googleSignIn,
  }) : _clientConfig = clientConfig,
       _inject = googleSignIn;

  final ClientConfigRepository? _clientConfig;
  final GoogleSignIn? _inject;

  GoogleSignIn? _cached;
  String? _cacheKey;

  GoogleSignIn _signIn() {
    final inject = _inject;
    if (inject != null) return inject;
    final id = (_clientConfig?.effectiveGoogleServerClientId ??
            AppConfig.googleOAuthServerClientId)
        .trim();
    final key = id.isEmpty ? '_empty_' : id;
    final cached = _cached;
    if (cached != null && _cacheKey == key) return cached;
    _cacheKey = key;
    final created = GoogleSignIn(
      scopes: const <String>['email', 'profile'],
      serverClientId: id.isEmpty ? null : id,
    );
    _cached = created;
    return created;
  }

  /// Silent restore of a previous session (optional — call on startup).
  Future<GoogleAuthResult?> signInSilently() async {
    final account = await _signIn().signInSilently();
    if (account == null) return null;
    return _toResult(account);
  }

  /// Interactive sign-in (shows Google account picker when needed).
  Future<GoogleAuthResult?> signIn() async {
    final account = await _signIn().signIn();
    if (account == null) return null;
    return _toResult(account);
  }

  Future<void> signOut() => _signIn().signOut();

  Future<void> disconnect() => _signIn().disconnect();

  Future<GoogleAuthResult> _toResult(GoogleSignInAccount account) async {
    final auth = await account.authentication;
    return GoogleAuthResult(
      idToken: auth.idToken,
      email: account.email,
      displayName: account.displayName,
      photoUrl: account.photoUrl,
      userId: account.id,
    );
  }
}
