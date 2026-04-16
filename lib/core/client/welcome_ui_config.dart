/// Welcome screen presentation from [GET /api/v1/config/public] → `welcome`.
class WelcomeUiConfig {
  const WelcomeUiConfig({
    required this.backgroundImageUrl,
    required this.overlayColorHex,
    required this.overlayOpacity,
  });

  final String backgroundImageUrl;
  final String overlayColorHex;
  final double overlayOpacity;

  static const WelcomeUiConfig fallback = WelcomeUiConfig(
    backgroundImageUrl: '',
    overlayColorHex: '#F0F0F0',
    overlayOpacity: 0.78,
  );

  factory WelcomeUiConfig.fromJson(Object? raw) {
    if (raw is! Map) {
      return fallback;
    }
    final m = Map<String, dynamic>.from(raw);
    var url = '${m['backgroundImageUrl'] ?? ''}'.trim();
    if (url.length > 2048) {
      url = url.substring(0, 2048);
    }
    var hex = '${m['overlayColor'] ?? fallback.overlayColorHex}'.trim();
    if (!RegExp(r'^#[0-9A-Fa-f]{6}$').hasMatch(hex)) {
      hex = fallback.overlayColorHex;
    }
    var op = (m['overlayOpacity'] is num)
        ? (m['overlayOpacity'] as num).toDouble()
        : fallback.overlayOpacity;
    if (op < 0) {
      op = 0;
    }
    if (op > 1) {
      op = 1;
    }
    return WelcomeUiConfig(
      backgroundImageUrl: url,
      overlayColorHex: hex,
      overlayOpacity: op,
    );
  }
}
