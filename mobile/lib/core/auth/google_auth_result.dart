/// Successful Google account sign-in, ready to send to your API.
class GoogleAuthResult {
  const GoogleAuthResult({
    required this.idToken,
    required this.email,
    required this.displayName,
    required this.photoUrl,
    required this.userId,
  });

  /// OpenID Connect ID token — verify on PHP (Google certs) before issuing your session/JWT.
  final String? idToken;

  final String? email;
  final String? displayName;
  final String? photoUrl;
  final String userId;
}
