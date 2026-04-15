import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';

/// Google Geocoding API (same key as Maps — enable Geocoding API in Cloud Console).
final class GeocodeService {
  GeocodeService({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;

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
