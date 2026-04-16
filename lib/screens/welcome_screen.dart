import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_scope.dart';
import '../core/brand/brand_assets.dart';
import '../core/region/region_config_scope.dart';
import '../core/region/resolved_region_config.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/app_buttons.dart';

/// Concierge-style welcome: one path into the app (map), optional Google sign-in.
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

  static const Color _cream = Color(0xFFFFFBF5);
  static const Color _goldDeep = Color(0xFFE8AC00);

  Future<void> _signInWithGoogle(BuildContext context) async {
    final auth = AuthScope.of(context);
    final err = await auth.signInWithGoogle();
    if (!context.mounted) return;
    if (err != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(err)),
      );
      return;
    }
    if (!auth.isSignedIn) return;
    context.go('/home');
  }

  @override
  Widget build(BuildContext context) {
    final region = RegionConfigScope.resolvedOf(context);
    final textTheme = Theme.of(context).textTheme;
    final auth = AuthScope.of(context);
    final canPop = GoRouter.of(context).canPop();

    return Scaffold(
      backgroundColor: AppColors.surfaceMuted,
      body: Stack(
        clipBehavior: Clip.none,
        children: [
          DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  _cream,
                  AppColors.surfaceMuted.withValues(alpha: 0.92),
                  AppColors.surfaceMuted,
                ],
                stops: const [0.0, 0.45, 1.0],
              ),
            ),
            child: const SizedBox.expand(),
          ),
          const _AmbientGlows(),
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
                        padding: const EdgeInsets.fromLTRB(24, 12, 24, 20),
                        child: Center(
                          child: FittedBox(
                            fit: BoxFit.scaleDown,
                            alignment: Alignment.center,
                            child: SizedBox(
                              width: maxW,
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Text(
                                    'VP RIDE',
                                    textAlign: TextAlign.center,
                                    style: textTheme.labelSmall?.copyWith(
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: 2.8,
                                      color: AppColors.secondary.withValues(
                                        alpha: 0.38,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(height: 20),
                                  const Center(child: _FramedMark()),
                                  const SizedBox(height: 28),
                                  Text(
                                    'Move with intention',
                                    textAlign: TextAlign.center,
                                    style: textTheme.headlineMedium?.copyWith(
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: -1.0,
                                      height: 1.05,
                                      color: AppColors.secondary,
                                    ),
                                  ),
                                  const SizedBox(height: 12),
                                  Text(
                                    'Pin your pickup on the map and request a ride '
                                    'in seconds — tailored for your area.',
                                    textAlign: TextAlign.center,
                                    style: textTheme.bodyLarge?.copyWith(
                                      color: AppColors.secondary.withValues(
                                        alpha: 0.58,
                                      ),
                                      height: 1.45,
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),
                                  const SizedBox(height: 20),
                                  _RegionChips(region: region),
                                  const SizedBox(height: 28),
                                  AppPrimaryButton(
                                    label: 'Get started',
                                    icon: Icons.arrow_forward_rounded,
                                    onPressed: () => context.go('/home?tab=0'),
                                  ),
                                  const SizedBox(height: 22),
                                  Text(
                                    'Already riding with us?',
                                    textAlign: TextAlign.center,
                                    style: textTheme.labelMedium?.copyWith(
                                      fontWeight: FontWeight.w600,
                                      color: AppColors.secondary.withValues(
                                        alpha: 0.42,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(height: 10),
                                  ListenableBuilder(
                                    listenable: auth,
                                    builder: (context, _) {
                                      return AppSecondaryButton(
                                        label: 'Continue with Google',
                                        isLoading: auth.isBusy,
                                        onPressed: auth.isBusy
                                            ? null
                                            : () => _signInWithGoogle(
                                                  context,
                                                ),
                                      );
                                    },
                                  ),
                                  const SizedBox(height: 18),
                                  Text(
                                    'Pride',
                                    textAlign: TextAlign.center,
                                    style: textTheme.labelSmall?.copyWith(
                                      letterSpacing: 0.28,
                                      color: AppColors.secondary.withValues(
                                        alpha: 0.28,
                                      ),
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
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
  }
}

/// Soft gold wash + depth without clutter.
class _AmbientGlows extends StatelessWidget {
  const _AmbientGlows();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Stack(
        children: [
          Positioned(
            top: -60,
            right: -80,
            child: _blob(260, AppColors.primary.withValues(alpha: 0.14)),
          ),
          Positioned(
            top: 120,
            left: -100,
            child: _blob(220, WelcomeScreen._goldDeep.withValues(alpha: 0.08)),
          ),
          Positioned(
            bottom: -40,
            right: -20,
            child: _blob(180, AppColors.primary.withValues(alpha: 0.06)),
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
          colors: [
            AppColors.primary,
            WelcomeScreen._goldDeep,
          ],
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.35),
            blurRadius: 28,
            offset: const Offset(0, 12),
          ),
          BoxShadow(
            color: AppColors.secondary.withValues(alpha: 0.06),
            blurRadius: 24,
            offset: const Offset(0, 8),
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
            boxShadow: [
              BoxShadow(
                color: AppColors.secondary.withValues(alpha: 0.04),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Text(
            s,
            style: textTheme.labelSmall?.copyWith(
              fontWeight: FontWeight.w700,
              letterSpacing: 0.02,
              color: AppColors.secondary.withValues(alpha: 0.72),
            ),
          ),
        );
      }).toList(),
    );
  }
}
