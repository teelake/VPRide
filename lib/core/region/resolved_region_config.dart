import 'package:flutter/material.dart';

import '../locale/app_region.dart';
import 'region_config_dto.dart';

/// Effective geography + localization after merging backend [RegionConfigDto] with
/// [AppRegion] fallbacks.
class ResolvedRegionConfig {
  const ResolvedRegionConfig({this.remote});

  final RegionConfigDto? remote;

  String get serviceAreaLabel =>
      remote?.branding?.serviceAreaLabel?.trim().isNotEmpty == true
      ? remote!.branding!.serviceAreaLabel!.trim()
      : AppRegion.fallbackServiceAreaLabel;

  Locale get materialDefaultLocale {
    final tag = remote?.localization?.defaultLocale?.replaceAll('-', '_');
    if (tag != null && tag.isNotEmpty) {
      final parsed = _parseLocaleTag(tag);
      if (parsed != null) return parsed;
    }
    return AppRegion.fallbackDefaultLocale;
  }

  List<Locale> get supportedLocales {
    final tags = remote?.localization?.supportedLocales;
    if (tags != null && tags.isNotEmpty) {
      final out = <Locale>[];
      for (final t in tags) {
        final parsed = _parseLocaleTag(t.replaceAll('-', '_'));
        if (parsed != null) out.add(parsed);
      }
      if (out.isNotEmpty) return out;
    }
    return AppRegion.fallbackSupportedLocales;
  }

  /// Active countries from API; empty if none configured (caller may still use fallbacks for pricing).
  List<CountryDto> get countries => remote?.countries ?? const <CountryDto>[];

  CountryDto? countryByCode(String code) {
    final u = code.toUpperCase();
    for (final c in countries) {
      if (c.code == u) return c;
    }
    return null;
  }

  CountryDto? get defaultCountry {
    final code = remote?.defaults?.countryCode;
    if (code != null && code.isNotEmpty) return countryByCode(code);
    return _firstOrNull(countries.where((c) => c.code.isNotEmpty));
  }

  CityDto? cityById(String id) {
    for (final c in countries) {
      for (final city in c.cities) {
        if (city.id == id) return city;
      }
    }
    return null;
  }

  CityDto? get defaultCity {
    final id = remote?.defaults?.cityId;
    if (id != null && id.isNotEmpty) {
      final city = cityById(id);
      if (city != null && city.isActive) return city;
    }
    final country = defaultCountry;
    if (country == null) return null;
    return _firstOrNull(country.cities.where((e) => e.isActive));
  }

  RegionLatLng? get defaultMapCenter => defaultCity?.center;

  String get defaultCountryCode =>
      defaultCountry?.code ?? AppRegion.fallbackCountryCode;

  String get defaultCurrencyCode =>
      defaultCountry?.currencyCode ?? AppRegion.fallbackCurrencyCode;

  String get defaultDistanceUnit =>
      (defaultCountry?.distanceUnit ?? 'km').toLowerCase();

  static Locale? _parseLocaleTag(String tag) {
    final parts = tag.split('_');
    if (parts.isEmpty || parts.first.isEmpty) return null;
    if (parts.length >= 2) {
      return Locale(parts[0].toLowerCase(), parts[1].toUpperCase());
    }
    return Locale(parts[0].toLowerCase());
  }
}

T? _firstOrNull<T>(Iterable<T> items) {
  final it = items.iterator;
  if (!it.moveNext()) return null;
  return it.current;
}
