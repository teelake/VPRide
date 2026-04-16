import 'dart:math' as math;

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_scope.dart';
import '../core/brand/brand_assets.dart';
import '../core/client/client_config_scope.dart';
import '../core/client/welcome_ui_config.dart';
import '../core/config/app_config.dart';
import '../core/region/region_config_scope.dart';
import '../core/region/resolved_region_config.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/app_buttons.dart';

/// VP Ride welcome — CMS copy + optional upload/URL background; email + Google paths.
class WelcomeScreen extends StatefulWidget {
  const WelcomeScreen({super.key});

  static const Color _goldDeep = Color(0xFFE8AC00);
  static const Color _cream = Color(0xFFFFF6D6);

  @override
  State<WelcomeScreen> createState() => _WelcomeScreenState();
}

class _WelcomeScreenState extends State<WelcomeScreen> {
  bool _didRefreshConfig = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_didRefreshConfig) {
      return;
    }
    if (AppConfig.apiBaseUrl.trim().isEmpty) {
      return;
    }
    _didRefreshConfig = true;
    ClientConfigScope.of(context).refresh();
  }

  Future<void> _googleSignIn(BuildContext context) async {
    final auth = AuthScope.of(context);
    final err = await auth.signInWithGoogle();
    if (!context.mounted) {
      return;
    }
    if (err != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(err)));
      return;
    }
    if (auth.isSignedIn) {
      context.go('/home');
    }
  }

  Color _parseHex(String hex, Color fallback) {
    var s = hex.trim();
    if (s.startsWith('#')) {
      s = s.substring(1);
    }
    if (s.length == 6) {
      final v = int.tryParse(s, radix: 16);
      if (v != null) {
        return Color(0xFF000000 | v);
      }
    }
    return fallback;
  }

  String _subheadFor(WelcomeUiConfig w, ResolvedRegionConfig region) {
    return w.subhead.replaceAll('{{region}}', region.serviceAreaLabel);
  }

  @override
  Widget build(BuildContext context) {
    final region = RegionConfigScope.resolvedOf(context);
    final textTheme = Theme.of(context).textTheme;
    final auth = AuthScope.of(context);
    final canPop = GoRouter.of(context).canPop();
    final clientCfg = ClientConfigScope.of(context);
    final welcome = clientCfg.welcomeUi;
    final requireSignIn = clientCfg.features.requireSignInForHome;

    return ListenableBuilder(
      listenable: Listenable.merge([auth, clientCfg]),
      builder: (context, _) {
        return Scaffold(
          body: Stack(
            fit: StackFit.expand,
            children: [
              _WelcomeBackdrop(welcome: welcome),
              if (welcome.backgroundImageUrl.trim().isEmpty)
                const _AmbientGlows(),
              if (welcome.backgroundImageUrl.trim().isEmpty)
                DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        WelcomeScreen._cream,
                        AppColors.surfaceMuted.withValues(alpha: 0.92),
                        AppColors.surfaceMuted,
                      ],
                      stops: const [0.0, 0.45, 1.0],
                    ),
                  ),
                  child: const SizedBox.expand(),
                ),
              Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        Colors.white.withValues(alpha: 0.02),
                        Colors.white.withValues(alpha: 0.82),
                        Colors.white.withValues(alpha: 0.92),
                      ],
                      stops: const [0.0, 0.5, 1.0],
                    ),
                  ),
                ),
              ),
              Positioned.fill(
                child: ColoredBox(
                  color: _parseHex(
                    welcome.overlayColorHex,
                    const Color(0xFFF0F0F0),
                  ).withValues(alpha: welcome.overlayOpacity),
                ),
              ),
              SafeArea(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (canPop)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(4, 4, 8, 0),
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: IconButton(
                            tooltip: 'Back',
                            onPressed: () => context.pop(),
                            icon: const Icon(Icons.arrow_back_rounded),
                            color: AppColors.secondary,
                          ),
                        ),
                      ),
                    Expanded(
                      child: LayoutBuilder(
                        builder: (context, constraints) {
                          final maxW = math.max(
                            0.0,
                            math.min(400.0, constraints.maxWidth - 48),
                          );
                          return Padding(
                            padding: const EdgeInsets.fromLTRB(24, 8, 24, 20),
                            child: Center(
                              child: FittedBox(
                                fit: BoxFit.scaleDown,
                                alignment: Alignment.center,
                                child: SizedBox(
                                  width: maxW,
                                  child: Column(
                                    mainAxisSize: MainAxisSize.min,
                                    crossAxisAlignment:
                                        CrossAxisAlignment.stretch,
                                    children: [
                                      Text(
                                        welcome.brandWordmark,
                                        textAlign: TextAlign.center,
                                        style: textTheme.labelSmall?.copyWith(
                                          fontWeight: FontWeight.w800,
                                          letterSpacing: 2.4,
                                          color: AppColors.secondary.withValues(
                                            alpha: 0.38,
                                          ),
                                        ),
                                      ),
                                      const SizedBox(height: 18),
                                      const Center(child: _FramedMark()),
                                      const SizedBox(height: 22),
                                      Text(
                                        welcome.headline,
                                        textAlign: TextAlign.center,
                                        style: textTheme.headlineMedium?.copyWith(
                                          fontWeight: FontWeight.w800,
                                          letterSpacing: -0.8,
                                          height: 1.08,
                                          color: AppColors.secondary,
                                        ),
                                      ),
                                      const SizedBox(height: 12),
                                      Text(
                                        _subheadFor(welcome, region),
                                        textAlign: TextAlign.center,
                                        style: textTheme.bodyLarge?.copyWith(
                                          color: AppColors.secondary.withValues(
                                            alpha: 0.58,
                                          ),
                                          height: 1.45,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                      const SizedBox(height: 18),
                                      _RegionChips(region: region),
                                      if (welcome.showFeatureRow) ...[
                                        const SizedBox(height: 18),
                                        Row(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Expanded(
                                              child: _FeatureTile(
                                                icon: Icons.verified_user_rounded,
                                                iconBg: AppColors.primary
                                                    .withValues(alpha: 0.22),
                                                title: welcome.featureLeftTitle,
                                                emphasized: true,
                                              ),
                                            ),
                                            const SizedBox(width: 12),
                                            Expanded(
                                              child: _FeatureTile(
                                                icon: Icons.schedule_rounded,
                                                iconBg: AppColors.secondary
                                                    .withValues(alpha: 0.06),
                                                title: welcome.featureRightTitle,
                                                emphasized: false,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ],
                                      const SizedBox(height: 22),
                                      AppPrimaryButton(
                                        label: welcome.ctaRegister,
                                        icon: Icons.person_add_alt_1_rounded,
                                        onPressed: auth.isBusy
                                            ? null
                                            : () => context.push(
                                                  '/welcome/register',
                                                ),
                                      ),
                                      const SizedBox(height: 12),
                                      SizedBox(
                                        height: 52,
                                        child: FilledButton(
                                          onPressed: auth.isBusy
                                              ? null
                                              : () => context.push(
                                                    '/welcome/login',
                                                  ),
                                          style: FilledButton.styleFrom(
                                            backgroundColor: const Color(
                                              0xFFE6E6E6,
                                            ),
                                            foregroundColor:
                                                AppColors.secondary,
                                            shape: RoundedRectangleBorder(
                                              borderRadius:
                                                  BorderRadius.circular(14),
                                            ),
                                          ),
                                          child: Text(
                                            welcome.ctaEmailLogin,
                                            style: textTheme.titleSmall
                                                ?.copyWith(
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                        ),
                                      ),
                                      const SizedBox(height: 12),
                                      AppSecondaryButton(
                                        label: welcome.ctaGoogle,
                                        onPressed: auth.isBusy
                                            ? null
                                            : () => _googleSignIn(context),
                                        isLoading: false,
                                      ),
                                      if (!requireSignIn) ...[
                                        const SizedBox(height: 10),
                                        TextButton(
                                          onPressed: auth.isBusy
                                              ? null
                                              : () => context.go(
                                                    '/home?tab=0',
                                                  ),
                                          child: Text(
                                            'Browse map without signing in',
                                            style: textTheme.labelLarge
                                                ?.copyWith(
                                              fontWeight: FontWeight.w600,
                                              color: AppColors.secondary
                                                  .withValues(alpha: 0.55),
                                            ),
                                          ),
                                        ),
                                      ],
                                      const SizedBox(height: 16),
                                      _FooterTagline(
                                        textTheme: textTheme,
                                        text: welcome.footerTagline,
                                      ),
                                      if (welcome.showPagerDots) ...[
                                        const SizedBox(height: 12),
                                        const _PagerDots(),
                                      ],
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _AmbientGlows extends StatelessWidget {
  const _AmbientGlows();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Stack(
        children: [
          Positioned(
            top: -50,
            right: -70,
            child: _blob(220, AppColors.primary.withValues(alpha: 0.12)),
          ),
          Positioned(
            top: 100,
            left: -80,
            child: _blob(200, WelcomeScreen._goldDeep.withValues(alpha: 0.07)),
          ),
        ],
      ),
    );
  }

  static Widget _blob(double size, Color color) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: RadialGradient(
          colors: [color, color.withValues(alpha: 0)],
          stops: const [0.2, 1.0],
        ),
      ),
    );
  }
}

class _FramedMark extends StatelessWidget {
  const _FramedMark();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(2.5),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(26),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.primary, WelcomeScreen._goldDeep],
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.32),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(23.5),
        child: Image.asset(
          BrandAssets.appIconSquircle,
          height: 92,
          width: 92,
          fit: BoxFit.cover,
          filterQuality: FilterQuality.high,
        ),
      ),
    );
  }
}

class _RegionChips extends StatelessWidget {
  const _RegionChips({required this.region});

  final ResolvedRegionConfig region;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final meta = [
      region.serviceAreaLabel,
      '${region.defaultCountryCode} · ${region.defaultCurrencyCode}',
      region.defaultDistanceUnit,
    ];
    return Wrap(
      alignment: WrapAlignment.center,
      spacing: 8,
      runSpacing: 8,
      children: meta.map((s) {
        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.72),
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: AppColors.secondary.withValues(alpha: 0.08),
            ),
          ),
          child: Text(
            s,
            style: textTheme.labelSmall?.copyWith(
              fontWeight: FontWeight.w700,
              color: AppColors.secondary.withValues(alpha: 0.72),
            ),
          ),
        );
      }).toList(),
    );
  }
}

class _WelcomeBackdrop extends StatelessWidget {
  const _WelcomeBackdrop({required this.welcome});

  final WelcomeUiConfig welcome;

  @override
  Widget build(BuildContext context) {
    final url = welcome.backgroundImageUrl.trim();
    if (url.isEmpty) {
      return const ColoredBox(color: Color(0xFF1A1A1A));
    }
    return CachedNetworkImage(
      imageUrl: url,
      fit: BoxFit.cover,
      width: double.infinity,
      height: double.infinity,
      fadeInDuration: const Duration(milliseconds: 280),
      placeholder: (context, _) => const ColoredBox(color: Color(0xFF2A2A2A)),
      errorWidget: (context, url, error) {
        if (kDebugMode) {
          debugPrint('Welcome background failed: $url — $error');
        }
        return const ColoredBox(color: Color(0xFF2A2A2A));
      },
    );
  }
}

class _FeatureTile extends StatelessWidget {
  const _FeatureTile({
    required this.icon,
    required this.iconBg,
    required this.title,
    required this.emphasized,
  });

  final IconData icon;
  final Color iconBg;
  final String title;
  final bool emphasized;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: emphasized
            ? Colors.white.withValues(alpha: 0.95)
            : Colors.white.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: AppColors.secondary.withValues(alpha: 0.06),
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 14, 12, 14),
        child: Row(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: iconBg,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 20, color: AppColors.secondary),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                title,
                style: textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: AppColors.secondary,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FooterTagline extends StatelessWidget {
  const _FooterTagline({
    required this.textTheme,
    required this.text,
  });

  final TextTheme textTheme;
  final String text;

  @override
  Widget build(BuildContext context) {
    final line = Expanded(
      child: Container(
        height: 1,
        color: AppColors.secondary.withValues(alpha: 0.12),
      ),
    );
    return Row(
      children: [
        line,
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10),
          child: Text(
            text,
            style: textTheme.labelSmall?.copyWith(
              fontWeight: FontWeight.w700,
              letterSpacing: 1.4,
              color: AppColors.secondary.withValues(alpha: 0.38),
            ),
          ),
        ),
        line,
      ],
    );
  }
}

class _PagerDots extends StatelessWidget {
  const _PagerDots();

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Container(
          width: 28,
          height: 5,
          decoration: BoxDecoration(
            color: AppColors.primary,
            borderRadius: BorderRadius.circular(99),
          ),
        ),
        const SizedBox(width: 8),
        Container(
          width: 6,
          height: 6,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: AppColors.secondary.withValues(alpha: 0.12),
          ),
        ),
        const SizedBox(width: 8),
        Container(
          width: 6,
          height: 6,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: AppColors.secondary.withValues(alpha: 0.12),
          ),
        ),
      ],
    );
  }
}
