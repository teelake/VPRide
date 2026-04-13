import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'core/auth/google_auth_service.dart';
import 'core/region/region_config_repository.dart';
import 'core/region/region_config_scope.dart';
import 'core/theme/app_colors.dart';
import 'core/theme/app_theme.dart';
import 'core/widgets/app_buttons.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final regionRepository = RegionConfigRepository();
  await regionRepository.loadInitial();
  runApp(
    RegionConfigScope(repository: regionRepository, child: const VprideApp()),
  );
}

class VprideApp extends StatelessWidget {
  const VprideApp({super.key});

  @override
  Widget build(BuildContext context) {
    final regionRepository = RegionConfigScope.of(context);
    return ListenableBuilder(
      listenable: regionRepository,
      builder: (context, child) {
        final region = regionRepository.resolved;
        return MaterialApp(
          title: 'Vpride',
          theme: buildAppTheme(),
          locale: region.materialDefaultLocale,
          supportedLocales: region.supportedLocales,
          localizationsDelegates: const [
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          localeResolutionCallback: (locale, supported) {
            if (locale == null) return region.materialDefaultLocale;
            for (final l in supported) {
              if (l.languageCode != locale.languageCode) continue;
              if (l.countryCode == null ||
                  l.countryCode == locale.countryCode) {
                return l;
              }
            }
            return region.materialDefaultLocale;
          },
          home: child,
        );
      },
      child: const ButtonLoadingDemo(),
    );
  }
}

/// Demo screen — replace with your real shell / routes.
class ButtonLoadingDemo extends StatefulWidget {
  const ButtonLoadingDemo({super.key});

  @override
  State<ButtonLoadingDemo> createState() => _ButtonLoadingDemoState();
}

class _ButtonLoadingDemoState extends State<ButtonLoadingDemo> {
  bool _primaryLoading = false;
  bool _secondaryLoading = false;
  bool _textLoading = false;
  bool _googleLoading = false;

  final _googleAuth = GoogleAuthService();

  Future<void> _simulatePrimary() async {
    setState(() => _primaryLoading = true);
    await Future<void>.delayed(const Duration(seconds: 2));
    if (mounted) setState(() => _primaryLoading = false);
  }

  Future<void> _simulateSecondary() async {
    setState(() => _secondaryLoading = true);
    await Future<void>.delayed(const Duration(seconds: 2));
    if (mounted) setState(() => _secondaryLoading = false);
  }

  Future<void> _simulateText() async {
    setState(() => _textLoading = true);
    await Future<void>.delayed(const Duration(seconds: 2));
    if (mounted) setState(() => _textLoading = false);
  }

  Future<void> _signInWithGoogle() async {
    setState(() => _googleLoading = true);
    try {
      final result = await _googleAuth.signIn();
      if (!mounted) return;
      if (result == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Google sign-in cancelled')),
        );
        return;
      }
      final tokenPreview = result.idToken == null || result.idToken!.length < 24
          ? 'missing — set GOOGLE_SERVER_CLIENT_ID for PHP verification'
          : '${result.idToken!.substring(0, 20)}…';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${result.email ?? 'Signed in'}\nID token: $tokenPreview',
          ),
        ),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Google sign-in failed: $e')));
      }
    } finally {
      if (mounted) setState(() => _googleLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final region = RegionConfigScope.resolvedOf(context);
    final repo = RegionConfigScope.of(context);
    return Scaffold(
      backgroundColor: AppColors.surfaceMuted,
      appBar: AppBar(
        title: const Text('Buttons'),
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.secondary,
        elevation: 0,
        actions: [
          IconButton(
            tooltip: 'Reload region config',
            onPressed: () => repo.refresh(),
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Text(
            'Serving: ${region.serviceAreaLabel}',
            style: Theme.of(context).textTheme.titleSmall,
          ),
          Text(
            'Default: ${region.defaultCountryCode} · ${region.defaultCurrencyCode} · ${region.defaultDistanceUnit}'
            '${region.defaultMapCenter != null ? ' · map ${region.defaultMapCenter!.latitude.toStringAsFixed(2)}, ${region.defaultMapCenter!.longitude.toStringAsFixed(2)}' : ''}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 16),
          AppPrimaryButton(
            label: 'Book ride',
            isLoading: _primaryLoading,
            onPressed: _simulatePrimary,
          ),
          const SizedBox(height: 12),
          AppSecondaryButton(
            label: 'Choose on map',
            icon: Icons.map_outlined,
            isLoading: _secondaryLoading,
            onPressed: _simulateSecondary,
          ),
          const SizedBox(height: 12),
          AppTextLoadingButton(
            label: 'Skip for now',
            isLoading: _textLoading,
            onPressed: _simulateText,
          ),
          const SizedBox(height: 24),
          AppPrimaryButton(
            label: 'Sign in with Google',
            icon: Icons.login,
            isLoading: _googleLoading,
            onPressed: _signInWithGoogle,
          ),
        ],
      ),
    );
  }
}
