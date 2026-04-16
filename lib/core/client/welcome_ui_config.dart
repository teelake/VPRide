/// Welcome screen presentation from [GET /api/v1/config/public] → `welcome`.
class WelcomeUiConfig {
  const WelcomeUiConfig({
    required this.backgroundImageUrl,
    required this.overlayColorHex,
    required this.overlayOpacity,
    required this.brandWordmark,
    required this.headline,
    required this.subhead,
    required this.featureLeftTitle,
    required this.featureRightTitle,
    required this.footerTagline,
    required this.showFeatureRow,
    required this.showPagerDots,
    required this.ctaRegister,
    required this.ctaEmailLogin,
    required this.ctaGoogle,
  });

  final String backgroundImageUrl;
  final String overlayColorHex;
  final double overlayOpacity;
  final String brandWordmark;
  final String headline;
  final String subhead;
  final String featureLeftTitle;
  final String featureRightTitle;
  final String footerTagline;
  final bool showFeatureRow;
  final bool showPagerDots;
  final String ctaRegister;
  final String ctaEmailLogin;
  final String ctaGoogle;

  static const WelcomeUiConfig fallback = WelcomeUiConfig(
    backgroundImageUrl: '',
    overlayColorHex: '#F0F0F0',
    overlayOpacity: 0.78,
    brandWordmark: 'VP RIDE',
    headline: 'Move with intention',
    subhead:
        'Book a ride in a few taps, or open the map to choose your pickup. We are built for {{region}}.',
    featureLeftTitle: 'Elite Safety',
    featureRightTitle: 'On Demand',
    footerTagline: 'NAVIGATE THE CITY',
    showFeatureRow: true,
    showPagerDots: true,
    ctaRegister: 'Create account',
    ctaEmailLogin: 'Sign in',
    ctaGoogle: 'Continue with Google',
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

    bool b(Object? v, {required bool d}) {
      if (v == null) {
        return d;
      }
      if (v is bool) {
        return v;
      }
      final s = '$v'.toLowerCase();
      if (s == 'false' || s == '0' || s == 'no') {
        return false;
      }
      if (s == 'true' || s == '1' || s == 'yes') {
        return true;
      }
      return d;
    }

    String t(String k, String def, int max) {
      var s = '${m[k] ?? def}'.trim();
      if (s.length > max) {
        s = s.substring(0, max);
      }
      return s.isEmpty ? def : s;
    }

    return WelcomeUiConfig(
      backgroundImageUrl: url,
      overlayColorHex: hex,
      overlayOpacity: op,
      brandWordmark: t('brandWordmark', fallback.brandWordmark, 48),
      headline: t('headline', fallback.headline, 120),
      subhead: t('subhead', fallback.subhead, 600),
      featureLeftTitle: t('featureLeftTitle', fallback.featureLeftTitle, 64),
      featureRightTitle: t('featureRightTitle', fallback.featureRightTitle, 64),
      footerTagline: t('footerTagline', fallback.footerTagline, 80),
      showFeatureRow: b(m['showFeatureRow'], d: fallback.showFeatureRow),
      showPagerDots: b(m['showPagerDots'], d: fallback.showPagerDots),
      ctaRegister: t('ctaRegister', fallback.ctaRegister, 48),
      ctaEmailLogin: t('ctaEmailLogin', fallback.ctaEmailLogin, 48),
      ctaGoogle: t('ctaGoogle', fallback.ctaGoogle, 64),
    );
  }
}
