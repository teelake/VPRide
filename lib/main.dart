import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'core/region/region_config_repository.dart';
import 'core/region/region_config_scope.dart';
import 'core/theme/app_theme.dart';
import 'screens/welcome_screen.dart';

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
          title: 'Pride',
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
      child: const WelcomeScreen(),
    );
  }
}
