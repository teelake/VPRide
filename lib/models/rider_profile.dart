class RiderProfile {
  const RiderProfile({
    required this.id,
    required this.email,
    this.displayName,
    this.photoUrl,
  });

  final int id;
  final String email;
  final String? displayName;
  final String? photoUrl;

  factory RiderProfile.fromJson(Map<String, dynamic> json) {
    final idRaw = json['id'];
    final id = idRaw is int
        ? idRaw
        : idRaw is num
        ? idRaw.toInt()
        : int.parse('$idRaw');
    return RiderProfile(
      id: id,
      email: json['email'] as String,
      displayName: json['displayName'] as String?,
      photoUrl: json['photoUrl'] as String?,
    );
  }
}
