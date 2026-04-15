import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/auth/auth_scope.dart';
import '../core/client/client_config_scope.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/app_buttons.dart';

/// Account & settings — works for guests and signed-in riders.
class ProfileTabScreen extends StatelessWidget {
  const ProfileTabScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final auth = AuthScope.of(context);

    final clientCfg = ClientConfigScope.of(context);

    return ColoredBox(
      color: AppColors.surfaceMuted,
      child: ListenableBuilder(
        listenable: Listenable.merge([auth, clientCfg]),
        builder: (context, _) {
          final p = auth.profile;
          final signedIn = auth.isSignedIn;
          final helpUrl = clientCfg.features.helpCenterUrl.trim();

          return ListView(
            padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
            children: [
              Text(
                'Profile',
                style: textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                signedIn
                    ? 'Signed in with your Pride account.'
                    : 'You are browsing as a guest. Sign in to sync trips across devices.',
                style: textTheme.bodyMedium?.copyWith(
                  color: AppColors.secondary.withValues(alpha: 0.65),
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 24),
              DecoratedBox(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppColors.border),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.secondary.withValues(alpha: 0.04),
                      blurRadius: 16,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        signedIn ? 'Account' : 'Guest',
                        style: textTheme.labelSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.08,
                          color: AppColors.secondary.withValues(alpha: 0.45),
                        ),
                      ),
                      const SizedBox(height: 8),
                      if (signedIn && p != null) ...[
                        Text(
                          p.displayName ?? p.email,
                          style: textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          p.email,
                          style: textTheme.bodySmall?.copyWith(
                            color: AppColors.secondary.withValues(alpha: 0.55),
                          ),
                        ),
                      ] else
                        Text(
                          'Not signed in',
                          style: textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),
              if (helpUrl.isNotEmpty) ...[
                AppSecondaryButton(
                  label: 'Help center',
                  icon: Icons.help_outline_rounded,
                  onPressed: () async {
                    final uri = Uri.tryParse(helpUrl);
                    if (uri != null && await canLaunchUrl(uri)) {
                      await launchUrl(uri, mode: LaunchMode.externalApplication);
                    }
                  },
                ),
                const SizedBox(height: 12),
              ],
              if (!signedIn)
                AppPrimaryButton(
                  label: 'Sign in',
                  icon: Icons.login_rounded,
                  isLoading: auth.isBusy,
                  onPressed: auth.isBusy
                      ? null
                      : () => context.push('/welcome'),
                )
              else
                AppSecondaryButton(
                  label: 'Sign out',
                  icon: Icons.logout_rounded,
                  onPressed: () async {
                    await auth.signOut();
                    if (context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Signed out')),
                      );
                    }
                  },
                ),
            ],
          );
        },
      ),
    );
  }
}
