import 'dart:convert';
import 'dart:io';

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
    required String displayName,
  }) async {
    final body = <String, dynamic>{
      'email': email,
      'password': password,
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

  Future<Map<String, dynamic>> postAuthForgotPassword({
    required String email,
  }) async {
    final res = await _client
        .post(
          _uri('/api/v1/auth/forgot-password'),
          headers: const {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: jsonEncode({'email': email.trim()}),
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postAuthResetPassword({
    required String token,
    required String password,
    required String passwordConfirm,
  }) async {
    final res = await _client
        .post(
          _uri('/api/v1/auth/reset-password'),
          headers: const {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: jsonEncode({
            'token': token.trim(),
            'password': password,
            'passwordConfirm': passwordConfirm,
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

  Future<Map<String, dynamic>> patchMe({
    required String bearerToken,
    required String displayName,
  }) async {
    final res = await _client
        .patch(
          _uri('/api/v1/me'),
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
          body: jsonEncode({'displayName': displayName.trim()}),
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postAuthChangePassword({
    required String bearerToken,
    required String currentPassword,
    required String newPassword,
    required String newPasswordConfirm,
  }) async {
    final res = await _client
        .post(
          _uri('/api/v1/auth/change-password'),
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
          body: jsonEncode({
            'currentPassword': currentPassword,
            'newPassword': newPassword,
            'newPasswordConfirm': newPasswordConfirm,
          }),
        )
        .timeout(_timeout);
    return _decode(res);
  }

  /// Multipart upload: field name `photo` (JPEG, PNG, WebP; max 2 MB on server).
  Future<Map<String, dynamic>> postMePhoto({
    required String bearerToken,
    required String filePath,
  }) async {
    final file = File(filePath);
    if (!await file.exists()) {
      throw ApiException(0, 'Photo file not found');
    }
    final uri = _uri('/api/v1/me/photo');
    final req = http.MultipartRequest('POST', uri);
    req.headers['Authorization'] = 'Bearer $bearerToken';
    req.headers['Accept'] = 'application/json';
    req.files.add(
      await http.MultipartFile.fromPath(
        'photo',
        filePath,
        filename: file.path.split(Platform.pathSeparator).last,
      ),
    );
    final streamed = await req.send().timeout(_timeout);
    final res = await http.Response.fromStream(streamed);
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

  Future<Map<String, dynamic>> getCurrentRide(String bearerToken) async {
    final res = await _client
        .get(
          _uri('/api/v1/rides/current'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postSos({
    required String bearerToken,
    required int rideId,
    required double latitude,
    required double longitude,
    double? accuracyM,
    String? message,
    String? clientRequestId,
  }) async {
    final body = <String, dynamic>{
      'rideId': rideId,
      'latitude': latitude,
      'longitude': longitude,
      if (accuracyM != null) 'accuracyM': accuracyM,
      if (message != null && message.trim().isNotEmpty) 'message': message.trim(),
      if (clientRequestId != null && clientRequestId.trim().isNotEmpty)
        'clientRequestId': clientRequestId.trim(),
    };
    final res = await _client
        .post(
          _uri('/api/v1/sos'),
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

  Future<Map<String, dynamic>> postRideEstimate({
    required String bearerToken,
    required double pickupLat,
    required double pickupLng,
    String? pickupAddress,
    double? destLat,
    double? destLng,
    String? destAddress,
    bool roundTrip = false,
    String? promoCode,
  }) async {
    final body = <String, dynamic>{
      'pickup': <String, dynamic>{
        'latitude': pickupLat,
        'longitude': pickupLng,
        if (pickupAddress != null && pickupAddress.trim().isNotEmpty)
          'address': pickupAddress.trim(),
      },
      if (destLat != null &&
          destLng != null &&
          !(destLat == 0 && destLng == 0))
        'destination': <String, dynamic>{
          'latitude': destLat,
          'longitude': destLng,
          if (destAddress != null && destAddress.trim().isNotEmpty)
            'address': destAddress.trim(),
        },
      'roundTrip': roundTrip,
      if (promoCode != null && promoCode.trim().isNotEmpty)
        'promoCode': promoCode.trim(),
    };
    final res = await _client
        .post(
          _uri('/api/v1/rides/estimate'),
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

  Future<Map<String, dynamic>> postRide({
    required String bearerToken,
    required double pickupLat,
    required double pickupLng,
    String? pickupAddress,
    double? destLat,
    double? destLng,
    String? destAddress,
    bool roundTrip = false,
    String? scheduledPickupAtIso,
    String? promoCode,
  }) async {
    final body = <String, dynamic>{
      'pickup': <String, dynamic>{
        'latitude': pickupLat,
        'longitude': pickupLng,
        if (pickupAddress != null && pickupAddress.trim().isNotEmpty)
          'address': pickupAddress.trim(),
      },
      if (destLat != null &&
          destLng != null &&
          !(destLat == 0 && destLng == 0))
        'destination': <String, dynamic>{
          'latitude': destLat,
          'longitude': destLng,
          if (destAddress != null && destAddress.trim().isNotEmpty)
            'address': destAddress.trim(),
        },
      'roundTrip': roundTrip,
      if (scheduledPickupAtIso != null &&
          scheduledPickupAtIso.trim().isNotEmpty)
        'scheduledPickupAt': scheduledPickupAtIso.trim(),
      if (promoCode != null && promoCode.trim().isNotEmpty)
        'promoCode': promoCode.trim(),
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

  Future<Map<String, dynamic>> getRidesMine(
    String bearerToken, {
    int? limit,
    int? beforeId,
  }) async {
    final q = <String, String>{
      if (limit != null) 'limit': '${limit.clamp(1, 100)}',
      if (beforeId != null && beforeId > 0) 'before_id': '$beforeId',
    };
    var uri = _uri('/api/v1/rides/mine');
    if (q.isNotEmpty) {
      uri = uri.replace(queryParameters: q);
    }
    final res = await _client
        .get(
          uri,
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> getRide(
    String bearerToken,
    int rideId,
  ) async {
    final res = await _client
        .get(
          _uri('/api/v1/rides/$rideId'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postRideCancel({
    required String bearerToken,
    required int rideId,
  }) async {
    final res = await _client
        .post(
          _uri('/api/v1/rides/$rideId/cancel'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postDriverLocation({
    required String bearerToken,
    required double latitude,
    required double longitude,
  }) async {
    final res = await _client
        .post(
          _uri('/api/v1/driver/location'),
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
          body: jsonEncode({
            'latitude': latitude,
            'longitude': longitude,
          }),
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postDriverAvailability({
    required String bearerToken,
    required String status,
  }) async {
    final res = await _client
        .post(
          _uri('/api/v1/driver/availability'),
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
          body: jsonEncode({'status': status}),
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> getDriverRidesIncoming(String bearerToken) async {
    final res = await _client
        .get(
          _uri('/api/v1/driver/rides/incoming'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> getDriverRidesActive(String bearerToken) async {
    final res = await _client
        .get(
          _uri('/api/v1/driver/rides/active'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> getDriverRidesHistory(
    String bearerToken, {
    int? limit,
    int? beforeId,
  }) async {
    final q = <String, String>{
      if (limit != null) 'limit': '${limit.clamp(1, 100)}',
      if (beforeId != null && beforeId > 0) 'before_id': '$beforeId',
    };
    var uri = _uri('/api/v1/driver/rides/history');
    if (q.isNotEmpty) {
      uri = uri.replace(queryParameters: q);
    }
    final res = await _client
        .get(
          uri,
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> getDriverEarningsSummary(
    String bearerToken,
  ) async {
    final res = await _client
        .get(
          _uri('/api/v1/driver/earnings/summary'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postDriverRideAccept(
    String bearerToken,
    int rideId,
  ) async {
    final res = await _client
        .post(
          _uri('/api/v1/driver/rides/$rideId/accept'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postDriverRideReject(
    String bearerToken,
    int rideId,
  ) async {
    final res = await _client
        .post(
          _uri('/api/v1/driver/rides/$rideId/reject'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postDriverRideStart(
    String bearerToken,
    int rideId,
  ) async {
    final res = await _client
        .post(
          _uri('/api/v1/driver/rides/$rideId/start'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
        )
        .timeout(_timeout);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postDriverRideComplete(
    String bearerToken,
    int rideId, {
    double? finalFare,
  }) async {
    final body = <String, dynamic>{
      if (finalFare != null) 'finalFare': finalFare,
    };
    final res = await _client
        .post(
          _uri('/api/v1/driver/rides/$rideId/complete'),
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

  Future<Map<String, dynamic>> postRideRating({
    required String bearerToken,
    required int rideId,
    required int stars,
    String? feedback,
  }) async {
    final body = <String, dynamic>{
      'stars': stars,
      if (feedback != null && feedback.trim().isNotEmpty)
        'feedback': feedback.trim(),
    };
    final res = await _client
        .post(
          _uri('/api/v1/rides/$rideId/rating'),
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

  Future<Map<String, dynamic>> postRidePaymentProof({
    required String bearerToken,
    required int rideId,
    required List<int> fileBytes,
    required String filename,
  }) async {
    final uri = _uri('/api/v1/rides/$rideId/payment-proof');
    final request = http.MultipartRequest('POST', uri)
      ..headers['Accept'] = 'application/json'
      ..headers['Authorization'] = 'Bearer $bearerToken'
      ..files.add(
        http.MultipartFile.fromBytes(
          'proof',
          fileBytes,
          filename: filename,
        ),
      );
    final streamed = await request.send().timeout(const Duration(seconds: 90));
    final res = await http.Response.fromStream(streamed);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postRidePaymentOffline({
    required String bearerToken,
    required int rideId,
    required String method,
    String? proofUrl,
    String? referenceNote,
  }) async {
    final body = <String, dynamic>{
      'method': method,
      if (proofUrl != null && proofUrl.trim().isNotEmpty) 'proofUrl': proofUrl.trim(),
      if (referenceNote != null && referenceNote.trim().isNotEmpty)
        'referenceNote': referenceNote.trim(),
    };
    final res = await _client
        .post(
          _uri('/api/v1/rides/$rideId/payment-offline'),
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

  Future<Map<String, dynamic>> postDriverConfirmPayment(
    String bearerToken,
    int rideId,
  ) async {
    final res = await _client
        .post(
          _uri('/api/v1/driver/rides/$rideId/confirm-payment'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $bearerToken',
          },
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
    final Object? decoded;
    try {
      decoded = jsonDecode(raw);
    } on FormatException {
      throw ApiException(res.statusCode, 'Invalid response from server');
    }
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
