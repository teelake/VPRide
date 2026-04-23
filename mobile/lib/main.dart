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
import 'core/navigation/home_shell_route.dart';
import 'screens/change_password_screen.dart';
import 'screens/edit_profile_screen.dart';
import 'screens/home_shell_screen.dart';
import 'screens/rider_forgot_password_screen.dart';
import 'screens/rider_login_screen.dart';
import 'screens/rider_register_screen.dart';
import 'screens/rider_reset_password_screen.dart';
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
      if (path.startsWith('/account/') && !authRepository.isSignedIn) {
        return '/welcome';
      }
      if (authRepository.isSignedIn &&
          authRepository.profile?.mustChangePassword == true) {
        final allowed = path == '/account/force-password' ||
            path == '/welcome/forgot-password' ||
            path == '/welcome/reset-password';
        if (!allowed) {
          return '/account/force-password';
        }
      }
      // Allow /welcome/forgot-password while signed in (e.g. from Change password).
      if ((path == '/welcome' ||
              path == '/welcome/register' ||
              path == '/welcome/login' ||
              path == '/welcome/reset-password') &&
          authRepository.isSignedIn) {
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
        path: '/welcome/register',
        builder: (context, state) => const RiderRegisterScreen(),
      ),
      GoRoute(
        path: '/welcome/login',
        builder: (context, state) => const RiderLoginScreen(),
      ),
      GoRoute(
        path: '/welcome/forgot-password',
        builder: (context, state) => const RiderForgotPasswordScreen(),
      ),
      GoRoute(
        path: '/welcome/reset-password',
        builder: (context, state) => RiderResetPasswordScreen(
          initialToken: state.uri.queryParameters['token'],
        ),
      ),
      GoRoute(
        path: '/home',
        builder: (context, state) {
          final hasDriver = authRepository.driverProfile != null;
          final driverOnly = authRepository.profile?.driverAccountOnly == true &&
              hasDriver;
          final route = HomeShellRoute.fromUri(
            state.uri,
            hasDriverProfile: hasDriver,
            driverAccountOnly: driverOnly,
          );
          return HomeShellScreen(initialRoute: route);
        },
      ),
      GoRoute(
        path: '/account/edit-profile',
        builder: (context, state) => const EditProfileScreen(),
      ),
      GoRoute(
        path: '/account/change-password',
        builder: (context, state) => const ChangePasswordScreen(),
      ),
      GoRoute(
        path: '/account/force-password',
        builder: (context, state) =>
            const ChangePasswordScreen(forceEnrollment: true),
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
