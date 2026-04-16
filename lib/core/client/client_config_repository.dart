import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import 'app_public_features.dart';

/// Public keys from [GET /api/v1/config/public] with `--dart-define` fallback.
class ClientConfigRepository extends ChangeNotifier {
  ClientConfigRepository({http.Client? httpClient})
    : _client = httpClient ?? http.Client();

  final http.Client _client;

  String _remoteGoogleWebClientId = '';
  String _remoteMapsApiKey = '';
  String _remoteMinimumAppVersion = '';
  AppPublicFeatures _features = AppPublicFeatures.fallback;

  /// Feature flags from the last successful config fetch (or [AppPublicFeatures.fallback]).
  AppPublicFeatures get features => _features;

  /// Non-empty when the last [loadInitial] returned values from the server.
  String get remoteGoogleWebClientId => _remoteGoogleWebClientId;

  String get remoteMapsApiKey => _remoteMapsApiKey;

  String get remoteMinimumAppVersion => _remoteMinimumAppVersion;

  String get effectiveGoogleWebClientId {
    final r = _remoteGoogleWebClientId.trim();
    if (r.isNotEmpty) return r;
    return AppConfig.googleOAuthServerClientId.trim();
  }

  String get effectiveMapsApiKey {
    final r = _remoteMapsApiKey.trim();
    if (r.isNotEmpty) return r;
    return AppConfig.mapsApiKey.trim();
  }

  Future<void> loadInitial() async {
    final base = AppConfig.apiBaseUrl.trim();
    if (base.isEmpty) {
      notifyListeners();
      return;
    }
    final path = AppConfig.publicConfigPath.startsWith('/')
        ? AppConfig.publicConfigPath
        : '/${AppConfig.publicConfigPath}';
    final uri = Uri.parse('$base$path');
    try {
      final response = await _client
          .get(
            uri,
            headers: const {'Accept': 'application/json'},
          )
          .timeout(const Duration(seconds: 12));
      if (response.statusCode == 200 && response.body.isNotEmpty) {
        final data = jsonDecode(response.body);
        if (data is Map<String, dynamic>) {
          _remoteGoogleWebClientId =
              '${data['googleWebClientId'] ?? ''}'.trim();
          _remoteMapsApiKey = '${data['mapsApiKey'] ?? ''}'.trim();
          _remoteMinimumAppVersion =
              '${data['minimumAppVersion'] ?? ''}'.trim();
          _features = AppPublicFeatures.fromJson(data['features']);
        }
      } else if (kDebugMode) {
        debugPrint(
          'ClientConfigRepository: HTTP ${response.statusCode} for $uri',
        );
      }
    } catch (e, st) {
      if (kDebugMode) {
        debugPrint('ClientConfigRepository: load failed: $e\n$st');
      }
    }
    notifyListeners();
  }

  Future<void> refresh() => loadInitial();

  @override
  void dispose() {
    _client.close();
    super.dispose();
  }
}
