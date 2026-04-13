import 'package:google_sign_in/google_sign_in.dart';

import '../config/app_config.dart';
import 'google_auth_result.dart';

/// Google OAuth for a **custom backend** (PHP): exchange [GoogleAuthResult.idToken] server-side.
class GoogleAuthService {
  GoogleAuthService({GoogleSignIn? googleSignIn})
    : _googleSignIn =
          googleSignIn ??
          GoogleSignIn(
            scopes: const <String>['email', 'profile'],
            serverClientId: _serverClientIdOrNull,
          );

  final GoogleSignIn _googleSignIn;

  static String? get _serverClientIdOrNull {
    final v = AppConfig.googleOAuthServerClientId.trim();
    return v.isEmpty ? null : v;
  }

  /// Silent restore of a previous session (optional — call on startup).
  Future<GoogleAuthResult?> signInSilently() async {
    final account = await _googleSignIn.signInSilently();
    if (account == null) return null;
    return _toResult(account);
  }

  /// Interactive sign-in (shows Google account picker when needed).
  Future<GoogleAuthResult?> signIn() async {
    final account = await _googleSignIn.signIn();
    if (account == null) return null;
    return _toResult(account);
  }

  Future<void> signOut() => _googleSignIn.signOut();

  Future<void> disconnect() => _googleSignIn.disconnect();

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
