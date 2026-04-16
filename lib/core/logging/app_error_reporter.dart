import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';

/// Best-effort remote logging to PHP `error_log` via [POST /api/v1/log/client].
/// Payload is capped so requests stay under the server body limit (8 KiB).
final class AppErrorReporter {
  AppErrorReporter._();

  static final http.Client _client = http.Client();

  static const int _maxMessage = 3200;
  static const int _maxContextValue = 900;
  static const int _maxBody = 7200;

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

    var msg = message.trim();
    if (msg.length > _maxMessage) {
      msg = '${msg.substring(0, _maxMessage)}…';
    }

    Map<String, Object?>? safeContext;
    if (context != null && context.isNotEmpty) {
      safeContext = {};
      for (final e in context.entries) {
        final v = e.value;
        var s = v == null ? '' : v.toString();
        if (s.length > _maxContextValue) {
          s = '${s.substring(0, _maxContextValue)}…';
        }
        safeContext[e.key] = s;
      }
    }

    var payload = <String, Object?>{
      'level': level,
      'message': msg,
      if (safeContext != null && safeContext.isNotEmpty) 'context': safeContext,
    };

    var body = jsonEncode(payload);
    while (body.length > _maxBody && msg.length > 200) {
      msg = '${msg.substring(0, msg.length - 200)}…';
      payload = {
        'level': level,
        'message': msg,
        if (safeContext != null && safeContext.isNotEmpty) 'context': safeContext,
      };
      body = jsonEncode(payload);
    }
    if (body.length > _maxBody) {
      payload = {'level': level, 'message': msg};
      body = jsonEncode(payload);
    }
    if (body.length > _maxBody && msg.length > 120) {
      payload = {
        'level': level,
        'message': '${msg.substring(0, 120)}…',
      };
      body = jsonEncode(payload);
    }

    _client
        .post(
          uri,
          headers: const {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: body,
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
