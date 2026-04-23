import 'package:flutter/foundation.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

/// Shared pickup point while the map is moved (center-pin pattern).
final class RidePickupController extends ChangeNotifier {
  LatLng? pickup;
  String? addressLabel;
  bool isGeocoding = false;

  void setFromCamera(LatLng target, {bool notify = true}) {
    pickup = target;
    if (notify) notifyListeners();
  }

  void setAddressLabel(String? label, {bool geocoding = false}) {
    addressLabel = label;
    isGeocoding = geocoding;
    notifyListeners();
  }

  void clearAddress() {
    addressLabel = null;
    notifyListeners();
  }
}
