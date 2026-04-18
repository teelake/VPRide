class RiderProfile {
  const RiderProfile({
    required this.id,
    required this.email,
    this.displayName,
    this.photoUrl,
    this.hasPassword = true,
    this.mustChangePassword = false,
    this.driverAccountOnly = false,
  });

  final int id;
  final String email;
  final String? displayName;
  final String? photoUrl;

  /// Whether this account can use email/password (false for Google-only).
  /// When absent in older API responses, defaults to true.
  final bool hasPassword;

  /// Fleet invite: must set a new password in the app before full access.
  final bool mustChangePassword;

  /// Fleet-provisioned driver login: app shows driver UI only (no ride booking shell).
  final bool driverAccountOnly;

  factory RiderProfile.fromJson(Map<String, dynamic> json) {
    final idRaw = json['id'];
    final id = idRaw is int
        ? idRaw
        : idRaw is num
        ? idRaw.toInt()
        : int.parse('$idRaw');
    final hp = json['hasPassword'];
    final mc = json['mustChangePassword'];
    final dao = json['driverAccountOnly'];
    return RiderProfile(
      id: id,
      email: json['email'] as String,
      displayName: json['displayName'] as String?,
      photoUrl: json['photoUrl'] as String?,
      hasPassword: hp is bool ? hp : true,
      mustChangePassword: mc is bool ? mc : false,
      driverAccountOnly: dao is bool ? dao : false,
    );
  }
}
