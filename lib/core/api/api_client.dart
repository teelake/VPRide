import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import 'api_exception.dart';

/// Mobile JSON API client — short timeouts, no cookies.
final class ApiClient {
  ApiClient({http.Client? httpClient, Duration? timeout})
    : _client = httpClient ?? http.Client(),
      _timeout = timeout ?? const Duration(seconds: 20);

  final http.Client _client;
  final Duration _timeout;

  void close() => _client.close();

  Uri _uri(String path) {
    final base = AppConfig.apiBaseUrl.trim();
    if (base.isEmpty) {
      throw ApiException(0, 'API_BASE_URL is not configured');
    }
    final p = path.startsWith('/') ? path : '/$path';
    return Uri.parse('$base$p');
  }

  Future<Map<String, dynamic>> postAuthRegister({
    required String email,
    required String password,
    String? displayName,
  }) async {
    final body = <String, dynamic>{
      'email': email,
      'password': password,
      if (displayName != null && displayName.trim().isNotEmpty)
        'displayName': displayName.trim(),
    };
    final res = await _client
        .post(
          _uri('/api/v1/auth/register'),
          headers: const {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: jsonEncode(body),
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postAuthLogin({
    required String email,
    required String password,
  }) async {
    final res = await _client
        .post(
          _uri('/api/v1/auth/login'),
          headers: const {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: jsonEncode({
            'email': email,
            'password': password,
          }),
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postAuthGoogle(String idToken) async {
    final res = await _client
        .post(
          _uri('/api/v1/auth/google'),
          headers: const {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: jsonEncode({'idToken': idToken}),
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> getMe(String bearerToken) async {
    final res = await _client
        .get(
          _uri('/api/v1/me'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<void> postLogout(String bearerToken) async {
    try {
      await _client
          .post(
            _uri('/api/v1/auth/logout'),
            headers: {
              'Accept': 'application/json',
              'Authorization': 'Bearer $bearerToken',
            },
          )
          .timeout(_timeout);
    } catch (_) {
      // Best-effort revoke
    }
  }

  Future<Map<String, dynamic>> postRide({
    required String bearerToken,
    required double pickupLat,
    required double pickupLng,
    String? pickupAddress,
  }) async {
    final body = <String, dynamic>{
      'pickup': <String, dynamic>{
        'latitude': pickupLat,
        'longitude': pickupLng,
        if (pickupAddress != null && pickupAddress.trim().isNotEmpty)
          'address': pickupAddress.trim(),
      },
    };
    final res = await _client
        .post(
          _uri('/api/v1/rides'),
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
          body: jsonEncode(body),
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Map<String, dynamic> _decode(http.Response res) {
    final raw = res.body;
    if (raw.isEmpty) {
      if (res.statusCode >= 400) {
        throw ApiException(res.statusCode, 'Request failed');
      }
      return {};
    }
    final decoded = jsonDecode(raw);
    if (decoded is! Map<String, dynamic>) {
      throw ApiException(res.statusCode, 'Invalid response');
    }
    if (res.statusCode >= 400) {
      final err = decoded['error']?.toString() ?? 'request_failed';
      final msg = decoded['message']?.toString();
      throw ApiException(res.statusCode, msg ?? err);
    }
    return decoded;
  }
}
