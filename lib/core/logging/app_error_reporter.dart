import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';

/// Best-effort remote logging to PHP `error_log` via [POST /api/v1/log/client].
final class AppErrorReporter {
  AppErrorReporter._();

  static final http.Client _client = http.Client();

  static void report(
    String level,
    String message, {
    Map<String, Object?>? context,
  }) {
    final base = AppConfig.apiBaseUrl.trim();
    if (base.isEmpty) {
      return;
    }
    final root = base.replaceAll(RegExp(r'/+$'), '');
    final uri = Uri.parse('$root/api/v1/log/client');
    final payload = <String, Object?>{
      'level': level,
      'message': message,
      if (context != null && context.isNotEmpty) 'context': context,
    };
    _client
        .post(
          uri,
          headers: const {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: jsonEncode(payload),
        )
        .timeout(const Duration(seconds: 8))
        .catchError((_) => http.Response('', 500));
  }

  static String trimStack(String? s, [int max = 3500]) {
    if (s == null || s.isEmpty) {
      return '';
    }
    if (s.length <= max) {
      return s;
    }
    return s.substring(0, max);
  }
}
