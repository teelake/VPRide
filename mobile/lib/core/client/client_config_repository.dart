import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import '../logging/app_error_reporter.dart';
import 'app_public_features.dart';
import 'app_public_operations.dart';
import 'welcome_ui_config.dart';

/// Public keys from [GET /api/v1/config/public] with `--dart-define` fallback.
class ClientConfigRepository extends ChangeNotifier {
  ClientConfigRepository({http.Client? httpClient})
    : _client = httpClient ?? http.Client();

  final http.Client _client;

  String _remoteGoogleWebClientId = '';
  String _remoteMapsApiKey = '';
  String _remoteMinimumAppVersion = '';
  AppPublicFeatures _features = AppPublicFeatures.fallback;
  AppPublicOperations _operations = AppPublicOperations.fallback;
  WelcomeUiConfig _welcomeUi = WelcomeUiConfig.fallback;

  /// Feature flags from the last successful config fetch (or [AppPublicFeatures.fallback]).
  AppPublicFeatures get features => _features;

  AppPublicOperations get operations => _operations;

  WelcomeUiConfig get welcomeUi => _welcomeUi;

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
        final Object? data;
        try {
          data = jsonDecode(response.body);
        } on FormatException {
          AppErrorReporter.report(
            'warning',
            'Public config: invalid JSON',
            context: {'uri': uri.toString()},
          );
          notifyListeners();
          return;
        }
        if (data is Map<String, dynamic>) {
          _remoteGoogleWebClientId =
              '${data['googleWebClientId'] ?? ''}'.trim();
          _remoteMapsApiKey = '${data['mapsApiKey'] ?? ''}'.trim();
          _remoteMinimumAppVersion =
              '${data['minimumAppVersion'] ?? ''}'.trim();
          _features = AppPublicFeatures.fromJson(data['features']);
          _operations = AppPublicOperations.fromJson(data['operations']);
          _welcomeUi = WelcomeUiConfig.fromJson(data['welcome']);
          if (_remoteMapsApiKey.isEmpty &&
              AppConfig.mapsApiKey.trim().isEmpty) {
            AppErrorReporter.report(
              'warning',
              'Public config: mapsApiKey empty after fetch',
              context: {
                'uri': uri.toString(),
                'defineMapsKeyEmpty': true,
              },
            );
          }
        }
      } else {
        final snippet = response.body.length > 400
            ? '${response.body.substring(0, 400)}…'
            : response.body;
        AppErrorReporter.report(
          'warning',
          'Public config fetch failed',
          context: {
            'uri': uri.toString(),
            'statusCode': response.statusCode,
            if (snippet.isNotEmpty) 'bodySnippet': snippet,
          },
        );
        if (kDebugMode) {
          debugPrint(
            'ClientConfigRepository: HTTP ${response.statusCode} for $uri',
          );
        }
      }
    } catch (e, st) {
      AppErrorReporter.report(
        'error',
        'Public config load exception: $e',
        context: {'uri': uri.toString(), 'stack': '$st'},
      );
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
