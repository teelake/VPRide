import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:go_router/go_router.dart';

import 'core/api/api_client.dart';
import 'core/api/api_scope.dart';
import 'core/auth/auth_repository.dart';
import 'core/auth/auth_scope.dart';
import 'core/auth/google_auth_service.dart';
import 'core/auth/session_store.dart';
import 'core/client/client_config_repository.dart';
import 'core/client/client_config_scope.dart';
import 'core/region/region_config_repository.dart';
import 'core/region/region_config_scope.dart';
import 'core/ride/ride_pickup_controller.dart';
import 'core/ride/ride_pickup_scope.dart';
import 'core/logging/app_error_reporter.dart';
import 'core/theme/app_theme.dart';
import 'screens/home_shell_screen.dart';
import 'screens/welcome_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  FlutterError.onError = (details) {
    FlutterError.presentError(details);
    AppErrorReporter.report(
      'fatal',
      details.exceptionAsString(),
      context: {
        'library': '${details.library}',
        'stack': AppErrorReporter.trimStack(details.stack?.toString()),
      },
    );
  };

  PlatformDispatcher.instance.onError = (error, stack) {
    AppErrorReporter.report(
      'fatal',
      error.toString(),
      context: {'stack': AppErrorReporter.trimStack(stack.toString())},
    );
    return true;
  };

  final clientConfigRepository = ClientConfigRepository();
  await clientConfigRepository.loadInitial();

  final regionRepository = RegionConfigRepository();
  await regionRepository.loadInitial();

  final sessionStore = SessionStore();
  final apiClient = ApiClient();
  final authRepository = AuthRepository(
    apiClient: apiClient,
    googleAuth: GoogleAuthService(clientConfig: clientConfigRepository),
    sessionStore: sessionStore,
  );
  await authRepository.hydrate();

  final initialLocation = authRepository.isSignedIn ? '/home' : '/welcome';

  final ridePickupController = RidePickupController();

  final router = GoRouter(
    initialLocation: initialLocation,
    refreshListenable: Listenable.merge([
      authRepository,
      clientConfigRepository,
    ]),
    redirect: (context, state) {
      final path = state.uri.path;
      if (path == '/welcome' && authRepository.isSignedIn) {
        return '/home';
      }
      if ((path == '/home' || path.startsWith('/home/')) &&
          !authRepository.isSignedIn &&
          clientConfigRepository.features.requireSignInForHome) {
        return '/welcome';
      }
      return null;
    },
    routes: [
      GoRoute(
        path: '/welcome',
        builder: (context, state) => const WelcomeScreen(),
      ),
      GoRoute(
        path: '/home',
        builder: (context, state) {
          final tabRaw = int.tryParse(state.uri.queryParameters['tab'] ?? '');
          final tab = (tabRaw ?? 0).clamp(0, 1);
          return HomeShellScreen(initialTab: tab);
        },
      ),
    ],
  );

  runApp(
    ApiScope(
      client: apiClient,
      child: ClientConfigScope(
        repository: clientConfigRepository,
        child: RegionConfigScope(
          repository: regionRepository,
          child: AuthScope(
            repository: authRepository,
            child: RidePickupScope(
              controller: ridePickupController,
              child: VprideApp(router: router),
            ),
          ),
        ),
      ),
    ),
  );
}

class VprideApp extends StatelessWidget {
  const VprideApp({super.key, required this.router});

  final GoRouter router;

  @override
  Widget build(BuildContext context) {
    final regionRepository = RegionConfigScope.of(context);
    return ListenableBuilder(
      listenable: regionRepository,
      builder: (context, child) {
        final region = regionRepository.resolved;
        return MaterialApp.router(
          routerConfig: router,
          title: 'VP Ride',
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
        );
      },
    );
  }
}
