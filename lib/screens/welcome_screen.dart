import 'dart:math' as math;

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_scope.dart';
import '../core/client/client_config_scope.dart';
import '../core/client/welcome_ui_config.dart';
import '../core/config/app_config.dart';
import '../core/region/region_config_scope.dart';
import '../core/theme/app_colors.dart';

/// VP Ride welcome — hero, optional remote background + overlay from public config.
class WelcomeScreen extends StatefulWidget {
  const WelcomeScreen({super.key});

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

  Future<void> _getStartedOrSignIn(BuildContext context) async {
    final auth = AuthScope.of(context);
    if (auth.isSignedIn) {
      if (!context.mounted) {
        return;
      }
      context.go('/home?tab=0');
      return;
    }
    await _googleSignIn(context);
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
              Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        Colors.white.withValues(alpha: 0.05),
                        Colors.white.withValues(alpha: 0.88),
                        Colors.white.withValues(alpha: 0.94),
                      ],
                      stops: const [0.0, 0.42, 1.0],
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
                    Padding(
                      padding: const EdgeInsets.fromLTRB(8, 8, 16, 0),
                      child: Row(
                        children: [
                          if (canPop)
                            IconButton(
                              tooltip: 'Back',
                              onPressed: () => context.pop(),
                              icon: const Icon(Icons.arrow_back_rounded),
                              color: AppColors.secondary,
                            ),
                          Icon(
                            Icons.bolt_rounded,
                            color: AppColors.primary,
                            size: 28,
                          ),
                          const SizedBox(width: 6),
                          Text(
                            'VP Ride',
                            style: textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.3,
                              color: AppColors.secondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: LayoutBuilder(
                        builder: (context, constraints) {
                          final maxW = math.max(
                            0.0,
                            math.min(400.0, constraints.maxWidth - 40),
                          );
                          return Padding(
                            padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
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
                                        'THE URBAN',
                                        textAlign: TextAlign.center,
                                        style: textTheme.headlineLarge
                                            ?.copyWith(
                                          fontWeight: FontWeight.w800,
                                          letterSpacing: -0.8,
                                          height: 1.0,
                                          color: AppColors.secondary,
                                        ),
                                      ),
                                      Text(
                                        'PULSE.',
                                        textAlign: TextAlign.center,
                                        style: textTheme.headlineLarge
                                            ?.copyWith(
                                          fontWeight: FontWeight.w800,
                                          letterSpacing: -0.8,
                                          height: 1.0,
                                          color: AppColors.primary,
                                        ),
                                      ),
                                      const SizedBox(height: 14),
                                      Text(
                                        'Premium concierge mobility designed for '
                                        'the pace of the modern city. Serving '
                                        '${region.serviceAreaLabel}.',
                                        textAlign: TextAlign.center,
                                        style: textTheme.bodyLarge?.copyWith(
                                          color: AppColors.secondary.withValues(
                                            alpha: 0.62,
                                          ),
                                          height: 1.45,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                      const SizedBox(height: 22),
                                      Row(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Expanded(
                                            child: _FeatureTile(
                                              icon: Icons.verified_user_rounded,
                                              iconBg: AppColors.primary
                                                  .withValues(alpha: 0.22),
                                              title: 'Elite Safety',
                                              emphasized: true,
                                            ),
                                          ),
                                          const SizedBox(width: 12),
                                          Expanded(
                                            child: _FeatureTile(
                                              icon: Icons.schedule_rounded,
                                              iconBg: AppColors.secondary
                                                  .withValues(alpha: 0.06),
                                              title: 'On Demand',
                                              emphasized: false,
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 26),
                                      SizedBox(
                                        height: 54,
                                        child: FilledButton(
                                          onPressed: auth.isBusy
                                              ? null
                                              : () => _getStartedOrSignIn(
                                                    context,
                                                  ),
                                          style: FilledButton.styleFrom(
                                            backgroundColor: AppColors.primary,
                                            foregroundColor:
                                                AppColors.secondary,
                                            shape: RoundedRectangleBorder(
                                              borderRadius:
                                                  BorderRadius.circular(16),
                                            ),
                                          ),
                                          child: auth.isBusy
                                              ? const SizedBox(
                                                  height: 22,
                                                  width: 22,
                                                  child:
                                                      CircularProgressIndicator(
                                                    strokeWidth: 2.5,
                                                    color: AppColors.secondary,
                                                  ),
                                                )
                                              : Text(
                                                  'GET STARTED',
                                                  style: textTheme.labelLarge
                                                      ?.copyWith(
                                                    fontWeight: FontWeight.w800,
                                                    letterSpacing: 0.6,
                                                  ),
                                                ),
                                        ),
                                      ),
                                      const SizedBox(height: 12),
                                      SizedBox(
                                        height: 54,
                                        child: FilledButton(
                                          onPressed: auth.isBusy
                                              ? null
                                              : () => _googleSignIn(context),
                                          style: FilledButton.styleFrom(
                                            backgroundColor: const Color(
                                              0xFFE6E6E6,
                                            ),
                                            foregroundColor:
                                                AppColors.secondary,
                                            shape: RoundedRectangleBorder(
                                              borderRadius:
                                                  BorderRadius.circular(16),
                                            ),
                                          ),
                                          child: auth.isBusy
                                              ? const SizedBox(
                                                  height: 22,
                                                  width: 22,
                                                  child:
                                                      CircularProgressIndicator(
                                                    strokeWidth: 2.5,
                                                  ),
                                                )
                                              : Text(
                                                  'Sign in',
                                                  style: textTheme.titleSmall
                                                      ?.copyWith(
                                                    fontWeight: FontWeight.w700,
                                                  ),
                                                ),
                                        ),
                                      ),
                                      if (!requireSignIn) ...[
                                        const SizedBox(height: 12),
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
                                      const SizedBox(height: 18),
                                      _FooterTagline(textTheme: textTheme),
                                      const SizedBox(height: 14),
                                      const _PagerDots(),
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

class _WelcomeBackdrop extends StatelessWidget {
  const _WelcomeBackdrop({required this.welcome});

  final WelcomeUiConfig welcome;

  @override
  Widget build(BuildContext context) {
    final url = welcome.backgroundImageUrl.trim();
    if (url.isEmpty) {
      return const ColoredBox(color: Color(0xFF2A2A2A));
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
        boxShadow: [
          BoxShadow(
            color: AppColors.secondary.withValues(alpha: 0.05),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
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
  const _FooterTagline({required this.textTheme});

  final TextTheme textTheme;

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
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Text(
            'NAVIGATE THE CITY',
            style: textTheme.labelSmall?.copyWith(
              fontWeight: FontWeight.w700,
              letterSpacing: 1.6,
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
