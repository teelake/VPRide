/// Fleet driver context returned on [GET /api/v1/me] when the rider is linked to an active fleet driver.
class DriverProfile {
  const DriverProfile({
    required this.fleetDriverId,
    required this.fullName,
    required this.availability,
  });

  final int fleetDriverId;
  final String fullName;

  /// One of: `offline`, `online`, `busy`
  final String availability;

  factory DriverProfile.fromJson(Map<String, dynamic> json) {
    final idRaw = json['fleetDriverId'];
    final id = idRaw is int
        ? idRaw
        : idRaw is num
        ? idRaw.toInt()
        : int.parse('$idRaw');
    return DriverProfile(
      fleetDriverId: id,
      fullName: '${json['fullName'] ?? ''}'.trim(),
      availability: '${json['availability'] ?? 'offline'}'.trim(),
    );
  }
}
