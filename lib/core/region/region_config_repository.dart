import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import 'region_config_dto.dart';
import 'resolved_region_config.dart';

/// Loads [RegionConfigDto] from the PHP API and exposes [ResolvedRegionConfig].
class RegionConfigRepository extends ChangeNotifier {
  RegionConfigRepository({http.Client? httpClient})
    : _client = httpClient ?? http.Client();

  final http.Client _client;

  RegionConfigDto? _dto;
  RegionConfigDto? get raw => _dto;

  ResolvedRegionConfig get resolved => ResolvedRegionConfig(remote: _dto);

  /// Call on startup. Safe offline: no base URL or HTTP errors leave fallbacks in place.
  Future<void> loadInitial() async {
    final base = AppConfig.apiBaseUrl.trim();
    if (base.isEmpty) {
      if (kDebugMode) {
        debugPrint(
          'RegionConfigRepository: API_BASE_URL empty — using AppRegion fallbacks.',
        );
      }
      notifyListeners();
      return;
    }

    final path = AppConfig.regionConfigPath.startsWith('/')
        ? AppConfig.regionConfigPath
        : '/${AppConfig.regionConfigPath}';
    final uri = Uri.parse('$base$path');

    try {
      final response = await _client
          .get(uri)
          .timeout(const Duration(seconds: 15));
      if (response.statusCode == 200 && response.body.isNotEmpty) {
        final json = jsonDecode(response.body);
        if (json is Map<String, dynamic>) {
          _dto = RegionConfigDto.fromJson(json);
        }
      } else if (kDebugMode) {
        debugPrint(
          'RegionConfigRepository: HTTP ${response.statusCode} for $uri',
        );
      }
    } catch (e, st) {
      if (kDebugMode) {
        debugPrint('RegionConfigRepository: load failed: $e\n$st');
      }
    }
    notifyListeners();
  }

  /// Pull latest config after admin changes countries/cities (optional).
  Future<void> refresh() => loadInitial();

  @override
  void dispose() {
    _client.close();
    super.dispose();
  }
}
