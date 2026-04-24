/// HTTP / API failure with a short message safe to show or log.
class ApiException implements Exception {
  ApiException(
    this.statusCode,
    this.message, {
    this.errorCode,
  });

  final int statusCode;
  final String message;

  /// When the API returns JSON `{ "error": "snake_code", "message": "…" }`, this is the `error` field.
  final String? errorCode;

  @override
  String toString() => 'ApiException($statusCode): $message';
}
