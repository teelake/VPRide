import 'package:flutter/material.dart';

/// **Compile-time fallbacks** when the backend region config is unavailable
/// (offline, empty `API_BASE_URL`, or parse errors). Prefer [ResolvedRegionConfig]
/// from [RegionConfigRepository] for runtime values.
abstract final class AppRegion {
  /// Shown to riders and drivers as the served area (e.g. “Serving …”).
  static const String fallbackServiceAreaLabel = 'Modern Canada';

  static const Locale fallbackDefaultLocale = Locale('en', 'CA');

  static const List<Locale> fallbackSupportedLocales = <Locale>[
    Locale('en', 'CA'),
    Locale('fr', 'CA'),
  ];

  static const String fallbackCountryCode = 'CA';
  static const String fallbackCurrencyCode = 'CAD';
  static const int fallbackCurrencyCodeNumeric = 124;
}
