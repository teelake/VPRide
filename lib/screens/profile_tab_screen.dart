import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/auth/auth_scope.dart';
import '../core/client/client_config_scope.dart';
import '../core/theme/app_colors.dart';
import '../models/driver_profile.dart';

/// Account hub: rider settings, optional driver summary, support links.
class ProfileTabScreen extends StatelessWidget {
  const ProfileTabScreen({super.key});

  static String _initials(String? name, String email) {
    final n = name?.trim();
    if (n != null && n.isNotEmpty) {
      final parts = n.split(RegExp(r'\s+')).where((e) => e.isNotEmpty).toList();
      if (parts.length >= 2) {
        return '${parts[0][0]}${parts[1][0]}'.toUpperCase();
      }
      return n.substring(0, n.length >= 2 ? 2 : 1).toUpperCase();
    }
    return email.isNotEmpty ? email[0].toUpperCase() : '?';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final auth = AuthScope.of(context);
    final clientCfg = ClientConfigScope.of(context);

    return ColoredBox(
      color: AppColors.surfaceMuted,
      child: ListenableBuilder(
        listenable: Listenable.merge([auth, clientCfg]),
        builder: (context, _) {
          final p = auth.profile;
          final signedIn = auth.isSignedIn;
          final driver = auth.driverProfile;
          final driverOnly =
              (p?.driverAccountOnly ?? false) && driver != null;
          final helpUrl = clientCfg.features.helpCenterUrl.trim();

          return CustomScrollView(
            physics: const BouncingScrollPhysics(
              parent: AlwaysScrollableScrollPhysics(),
            ),
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
                  child: Text(
                    'Account',
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.5,
                    ),
                  ),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: _HeroAccountCard(
                    signedIn: signedIn,
                    email: p?.email ?? '',
                    displayName: p?.displayName,
                    photoUrl: p?.photoUrl,
                    initials: p != null
                        ? _initials(p.displayName, p.email)
                        : '—',
                    driver: driver,
                  ),
                ),
              ),
              const SliverToBoxAdapter(child: SizedBox(height: 20)),
              if (signedIn) ...[
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: Text(
                      driverOnly ? 'Account' : 'Rider',
                      style: theme.textTheme.labelLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.secondary.withValues(alpha: 0.45),
                        letterSpacing: 0.6,
                      ),
                    ),
                  ),
                ),
                const SliverToBoxAdapter(child: SizedBox(height: 8)),
                SliverToBoxAdapter(
                  child: _PremiumTileGroup(
                    children: [
                      _PremiumTile(
                        icon: Icons.edit_outlined,
                        title: 'Edit profile',
                        subtitle: 'Name shown on trips',
                        onTap: () => context.push('/account/edit-profile'),
                      ),
                      if (p?.hasPassword ?? true)
                        _PremiumTile(
                          icon: Icons.lock_outline_rounded,
                          title: 'Change password',
                          subtitle: 'Update your sign-in password',
                          onTap: () => context.push('/account/change-password'),
                        ),
                    ],
                  ),
                ),
              ],
              if (signedIn && driver != null && !driverOnly) ...[
                const SliverToBoxAdapter(child: SizedBox(height: 24)),
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: Text(
                      'Driver',
                      style: theme.textTheme.labelLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.secondary.withValues(alpha: 0.45),
                        letterSpacing: 0.6,
                      ),
                    ),
                  ),
                ),
                const SliverToBoxAdapter(child: SizedBox(height: 8)),
                SliverToBoxAdapter(
                  child: _PremiumTileGroup(
                    children: [
                      _PremiumTile(
                        icon: Icons.local_taxi_rounded,
                        title: 'Drive workspace',
                        subtitle:
                            '${driver.fullName.isNotEmpty ? driver.fullName : 'Fleet #${driver.fleetDriverId}'} · ${driver.availability}',
                        onTap: () => context.go(
                          '/home?layout=driver&tab=drive',
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SliverToBoxAdapter(child: SizedBox(height: 24)),
              if (helpUrl.isNotEmpty)
                SliverToBoxAdapter(
                  child: _PremiumTileGroup(
                    children: [
                      _PremiumTile(
                        icon: Icons.help_outline_rounded,
                        title: 'Help center',
                        subtitle: 'Guides & support',
                        onTap: () async {
                          final uri = Uri.tryParse(helpUrl);
                          if (uri != null && await canLaunchUrl(uri)) {
                            await launchUrl(
                              uri,
                              mode: LaunchMode.externalApplication,
                            );
                          }
                        },
                      ),
                    ],
                  ),
                ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 40),
                  child: signedIn
                      ? OutlinedButton.icon(
                          onPressed: auth.isBusy
                              ? null
                              : () async {
                                  await auth.signOut();
                                  if (context.mounted) {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      const SnackBar(
                                        content: Text('Signed out'),
                                      ),
                                    );
                                  }
                                },
                          icon: const Icon(Icons.logout_rounded),
                          label: const Text('Sign out'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppColors.secondary,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            side: BorderSide(
                              color: AppColors.secondary.withValues(
                                alpha: 0.15,
                              ),
                            ),
                          ),
                        )
                      : FilledButton.icon(
                          onPressed: auth.isBusy
                              ? null
                              : () => context.push('/welcome'),
                          icon: const Icon(Icons.login_rounded),
                          label: const Text('Sign in'),
                        ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _HeroAccountCard extends StatelessWidget {
  const _HeroAccountCard({
    required this.signedIn,
    required this.email,
    required this.displayName,
    required this.photoUrl,
    required this.initials,
    required this.driver,
  });

  final bool signedIn;
  final String email;
  final String? displayName;
  final String? photoUrl;
  final String initials;
  final DriverProfile? driver;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.secondary,
            AppColors.secondary.withValues(alpha: 0.88),
          ],
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.secondary.withValues(alpha: 0.22),
            blurRadius: 28,
            offset: const Offset(0, 14),
          ),
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.18),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Row(
          children: [
            CircleAvatar(
              radius: 34,
              backgroundColor: AppColors.primary,
              foregroundColor: AppColors.secondary,
              child: ClipOval(
                child: () {
                  final url = photoUrl?.trim();
                  if (signedIn && url != null && url.isNotEmpty) {
                    return CachedNetworkImage(
                        imageUrl: url,
                        width: 68,
                        height: 68,
                        fit: BoxFit.cover,
                        errorWidget: (_, _, _) => Center(
                          child: Text(
                            initials,
                            style: theme.textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      );
                  }
                  return Text(
                    initials,
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  );
                }(),
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (!signedIn)
                    Text(
                      'Guest',
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                      ),
                    )
                  else ...[
                    Text(
                      displayName?.trim().isNotEmpty == true
                          ? displayName!.trim()
                          : email,
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      email,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: Colors.white.withValues(alpha: 0.72),
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                  if (signedIn) ...[
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _ChipPill(
                          label: 'Rider',
                          icon: Icons.person_rounded,
                          light: true,
                        ),
                        if (driver != null)
                          const _ChipPill(
                            label: 'Driver',
                            icon: Icons.local_taxi_rounded,
                            light: true,
                          ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ChipPill extends StatelessWidget {
  const _ChipPill({
    required this.label,
    required this.icon,
    this.light = false,
  });

  final String label;
  final IconData icon;
  final bool light;

  @override
  Widget build(BuildContext context) {
    final fg = light ? Colors.white : AppColors.secondary;
    final bg = light ? Colors.white.withValues(alpha: 0.12) : Colors.white;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: light
              ? Colors.white.withValues(alpha: 0.2)
              : AppColors.border,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: fg.withValues(alpha: light ? 0.95 : 0.7)),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              color: fg.withValues(alpha: light ? 0.95 : 0.85),
              fontWeight: FontWeight.w700,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}

class _PremiumTileGroup extends StatelessWidget {
  const _PremiumTileGroup({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
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
        clipBehavior: Clip.antiAlias,
        child: Column(children: _withDividers(children)),
      ),
    );
  }

  List<Widget> _withDividers(List<Widget> tiles) {
    if (tiles.isEmpty) return [];
    final out = <Widget>[tiles.first];
    for (var i = 1; i < tiles.length; i++) {
      out.add(Divider(height: 1, color: AppColors.border.withValues(alpha: 0.6)));
      out.add(tiles[i]);
    }
    return out;
  }
}

class _PremiumTile extends StatelessWidget {
  const _PremiumTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      color: Colors.white,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: AppColors.secondary, size: 22),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.secondary.withValues(alpha: 0.5),
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                Icons.chevron_right_rounded,
                color: AppColors.secondary.withValues(alpha: 0.25),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
