import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';

/// Google Geocoding API (same key as Maps SDK — enable Geocoding API in Cloud Console).
///
/// Rider map passes [ClientConfigRepository.effectiveMapsApiKey] for both pickup and
/// destination so REST geocoding matches the app’s configured `mapsApiKey` / `MAPS_API_KEY`.
final class GeocodeService {
  GeocodeService({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;

  /// Returns `lat,lng` and formatted address for the best match, or null.
  Future<({double lat, double lng, String address})?> geocodeAddress(
    String query, {
    String? apiKey,
  }) async {
    final q = query.trim();
    if (q.isEmpty) return null;
    final key = (apiKey ?? AppConfig.mapsApiKey).trim();
    if (key.isEmpty) return null;

    final uri = Uri.https('maps.googleapis.com', '/maps/api/geocode/json', {
      'address': q,
      'key': key,
    });

    try {
      final res = await _client.get(uri).timeout(const Duration(seconds: 10));
      if (res.statusCode != 200) return null;
      final data = jsonDecode(res.body);
      if (data is! Map<String, dynamic>) return null;
      final results = data['results'];
      if (results is! List<dynamic> || results.isEmpty) return null;
      final first = results.first;
      if (first is! Map<String, dynamic>) return null;
      final addr = first['formatted_address'];
      if (addr is! String) return null;
      final geo = first['geometry'];
      if (geo is! Map<String, dynamic>) return null;
      final loc = geo['location'];
      if (loc is! Map<String, dynamic>) return null;
      final lat = loc['lat'];
      final lng = loc['lng'];
      if (lat is! num || lng is! num) return null;

      return (lat: lat.toDouble(), lng: lng.toDouble(), address: addr);
    } catch (_) {
      return null;
    }
  }

  Future<String?> reverseFormattedAddress(
    double lat,
    double lng, {
    String? apiKey,
  }) async {
    final key = (apiKey ?? AppConfig.mapsApiKey).trim();
    if (key.isEmpty) return null;

    final uri = Uri.https('maps.googleapis.com', '/maps/api/geocode/json', {
      'latlng': '$lat,$lng',
      'key': key,
    });

    try {
      final res = await _client.get(uri).timeout(const Duration(seconds: 10));
      if (res.statusCode != 200) return null;
      final data = jsonDecode(res.body);
      if (data is! Map<String, dynamic>) return null;
      final results = data['results'];
      if (results is! List<dynamic> || results.isEmpty) return null;
      final first = results.first;
      if (first is! Map<String, dynamic>) return null;
      final addr = first['formatted_address'];
      return addr is String ? addr : null;
    } catch (_) {
      return null;
    }
  }

  void close() => _client.close();
}
