import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_scope.dart';
import '../core/brand/brand_assets.dart';
import '../core/region/region_config_scope.dart';
import '../core/region/resolved_region_config.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/app_buttons.dart';

/// Consumer-facing welcome / get-started screen (Pride brand).
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

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
    final repo = RegionConfigScope.of(context);
    final textTheme = Theme.of(context).textTheme;
    final auth = AuthScope.of(context);
    final canPop = GoRouter.of(context).canPop();

    return Scaffold(
      backgroundColor: AppColors.surfaceMuted,
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Color(0xFFFFF6D6),
              AppColors.surfaceMuted,
              AppColors.surfaceMuted,
            ],
            stops: [0.0, 0.32, 1.0],
          ),
        ),
        child: SafeArea(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(4, 4, 8, 0),
                child: Row(
                  children: [
                    if (canPop)
                      IconButton(
                        tooltip: 'Back',
                        onPressed: () => context.pop(),
                        icon: const Icon(Icons.arrow_back_rounded),
                        color: AppColors.secondary,
                      ),
                    Expanded(
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: Image.asset(
                          BrandAssets.logoHorizontalLightBg,
                          height: 30,
                          fit: BoxFit.contain,
                          alignment: Alignment.centerLeft,
                          filterQuality: FilterQuality.high,
                        ),
                      ),
                    ),
                    Material(
                      color: Colors.white.withValues(alpha: 0.92),
                      shape: const CircleBorder(),
                      child: IconButton(
                        tooltip: 'Refresh region settings',
                        onPressed: () => repo.refresh(),
                        icon: const Icon(Icons.refresh_rounded),
                        color: AppColors.secondary,
                      ),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(24, 8, 24, 32),
                  child: Center(
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 420),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const SizedBox(height: 8),
                          Center(
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(28),
                                boxShadow: [
                                  BoxShadow(
                                    color: AppColors.secondary.withValues(
                                      alpha: 0.12,
                                    ),
                                    blurRadius: 32,
                                    offset: const Offset(0, 14),
                                  ),
                                  BoxShadow(
                                    color: AppColors.primary.withValues(
                                      alpha: 0.18,
                                    ),
                                    blurRadius: 24,
                                    offset: const Offset(0, 8),
                                  ),
                                ],
                              ),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(28),
                                child: Image.asset(
                                  BrandAssets.appIconSquircle,
                                  height: 100,
                                  filterQuality: FilterQuality.high,
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: 28),
                          Text(
                            'Ready when you are',
                            textAlign: TextAlign.center,
                            style: textTheme.headlineMedium?.copyWith(
                              fontWeight: FontWeight.w700,
                              letterSpacing: -0.6,
                              height: 1.15,
                              color: AppColors.secondary,
                            ),
                          ),
                          const SizedBox(height: 12),
                          Text(
                            'Book a ride in a few taps, or open the map to choose '
                            'your pickup. We are built for ${region.serviceAreaLabel}.',
                            textAlign: TextAlign.center,
                            style: textTheme.bodyLarge?.copyWith(
                              color: AppColors.secondary.withValues(
                                alpha: 0.68,
                              ),
                              height: 1.45,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                          const SizedBox(height: 24),
                          _RegionSummaryCard(region: region),
                          const SizedBox(height: 32),
                          AppPrimaryButton(
                            label: 'Book ride',
                            onPressed: () => context.go('/home?tab=0'),
                          ),
                          const SizedBox(height: 14),
                          AppSecondaryButton(
                            label: 'Choose on map',
                            icon: Icons.map_outlined,
                            onPressed: () => context.go('/home?tab=0'),
                          ),
                          const SizedBox(height: 8),
                          Center(
                            child: AppTextLoadingButton(
                              label: 'Skip for now',
                              onPressed: () => context.go('/home?tab=0'),
                            ),
                          ),
                          const SizedBox(height: 28),
                          _SignInDivider(textTheme: textTheme),
                          const SizedBox(height: 20),
                          ListenableBuilder(
                            listenable: auth,
                            builder: (context, _) {
                              return AppPrimaryButton(
                                label: 'Sign in with Google',
                                icon: Icons.login_rounded,
                                isLoading: auth.isBusy,
                                onPressed: auth.isBusy
                                    ? null
                                    : () => _signInWithGoogle(context),
                              );
                            },
                          ),
                          const SizedBox(height: 24),
                          Text(
                            'Pride',
                            textAlign: TextAlign.center,
                            style: textTheme.labelSmall?.copyWith(
                              letterSpacing: 0.12,
                              color: AppColors.secondary.withValues(
                                alpha: 0.35,
                              ),
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RegionSummaryCard extends StatelessWidget {
  const _RegionSummaryCard({required this.region});

  final ResolvedRegionConfig region;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final meta =
        '${region.defaultCountryCode} · ${region.defaultCurrencyCode} · '
        '${region.defaultDistanceUnit}';

    return DecoratedBox(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.88),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: AppColors.secondary.withValues(alpha: 0.05),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.22),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(
                Icons.near_me_rounded,
                color: AppColors.secondary,
                size: 22,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Your region',
                    style: textTheme.labelSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      letterSpacing: 0.06,
                      color: AppColors.secondary.withValues(alpha: 0.45),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    region.serviceAreaLabel,
                    style: textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.secondary,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    meta,
                    style: textTheme.bodySmall?.copyWith(
                      color: AppColors.secondary.withValues(alpha: 0.55),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SignInDivider extends StatelessWidget {
  const _SignInDivider({required this.textTheme});

  final TextTheme textTheme;

  @override
  Widget build(BuildContext context) {
    final line = Expanded(
      child: Container(
        height: 1,
        color: AppColors.secondary.withValues(alpha: 0.1),
      ),
    );
    return Row(
      children: [
        line,
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          child: Text(
            'Returning?',
            style: textTheme.labelMedium?.copyWith(
              fontWeight: FontWeight.w600,
              color: AppColors.secondary.withValues(alpha: 0.45),
              letterSpacing: 0.02,
            ),
          ),
        ),
        line,
      ],
    );
  }
}
