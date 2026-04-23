// Backend contract (PHP): JSON body for GET {apiBaseUrl}{regionConfigPath}
//
// {
//   "version": 1,
//   "updatedAt": "2026-04-13T12:00:00Z",
//   "branding": { "serviceAreaLabel": "Winkler, MB" },
//   "localization": {
//     "defaultLocale": "en_CA",
//     "supportedLocales": ["en_CA", "fr_CA"]
//   },
//   "countries": [
//     {
//       "code": "CA",
//       "name": "Canada",
//       "currencyCode": "CAD",
//       "distanceUnit": "km",
//       "cities": [
//         {
//           "id": "winkler",
//           "name": "Winkler",
//           "subdivision": "ON",
//           "isActive": true,
//           "center": { "latitude": 43.6532, "longitude": -79.3832 }
//         }
//       ]
//     }
//   ],
//   "defaults": { "countryCode": "CA", "cityId": "winkler" }
// }

class RegionConfigDto {
  const RegionConfigDto({
    required this.version,
    this.updatedAt,
    this.branding,
    this.localization,
    required this.countries,
    this.defaults,
  });

  factory RegionConfigDto.fromJson(Map<String, dynamic> json) {
    final rawCountries = json['countries'];
    return RegionConfigDto(
      version: (json['version'] as num?)?.toInt() ?? 1,
      updatedAt: json['updatedAt'] as String?,
      branding: json['branding'] is Map<String, dynamic>
          ? BrandingDto.fromJson(json['branding'] as Map<String, dynamic>)
          : null,
      localization: json['localization'] is Map<String, dynamic>
          ? LocalizationDto.fromJson(
              json['localization'] as Map<String, dynamic>,
            )
          : null,
      countries: rawCountries is List<dynamic>
          ? rawCountries
                .whereType<Map<String, dynamic>>()
                .map(CountryDto.fromJson)
                .toList()
          : const <CountryDto>[],
      defaults: json['defaults'] is Map<String, dynamic>
          ? DefaultsDto.fromJson(json['defaults'] as Map<String, dynamic>)
          : null,
    );
  }

  final int version;
  final String? updatedAt;
  final BrandingDto? branding;
  final LocalizationDto? localization;
  final List<CountryDto> countries;
  final DefaultsDto? defaults;
}

class BrandingDto {
  const BrandingDto({this.serviceAreaLabel});

  factory BrandingDto.fromJson(Map<String, dynamic> json) {
    return BrandingDto(serviceAreaLabel: json['serviceAreaLabel'] as String?);
  }

  final String? serviceAreaLabel;
}

class LocalizationDto {
  const LocalizationDto({
    this.defaultLocale,
    this.supportedLocales = const <String>[],
  });

  factory LocalizationDto.fromJson(Map<String, dynamic> json) {
    final raw = json['supportedLocales'];
    return LocalizationDto(
      defaultLocale: json['defaultLocale'] as String?,
      supportedLocales: raw is List<dynamic>
          ? raw.whereType<String>().toList()
          : const <String>[],
    );
  }

  final String? defaultLocale;
  final List<String> supportedLocales;
}

class CountryDto {
  const CountryDto({
    required this.code,
    this.name,
    this.currencyCode,
    this.distanceUnit,
    required this.cities,
  });

  factory CountryDto.fromJson(Map<String, dynamic> json) {
    final rawCities = json['cities'];
    return CountryDto(
      code: (json['code'] as String?)?.toUpperCase() ?? '',
      name: json['name'] as String?,
      currencyCode: json['currencyCode'] as String?,
      distanceUnit: json['distanceUnit'] as String?,
      cities: rawCities is List<dynamic>
          ? rawCities
                .whereType<Map<String, dynamic>>()
                .map(CityDto.fromJson)
                .toList()
          : const <CityDto>[],
    );
  }

  final String code;
  final String? name;
  final String? currencyCode;

  /// `km` or `mi` — drives copy and formatting client-side.
  final String? distanceUnit;
  final List<CityDto> cities;
}

class CityDto {
  const CityDto({
    required this.id,
    required this.name,
    this.subdivision,
    this.isActive = true,
    this.center,
  });

  factory CityDto.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic>? centerMap;
    final c = json['center'];
    if (c is Map<String, dynamic>) centerMap = c;

    return CityDto(
      id: json['id'] as String? ?? '',
      name: json['name'] as String? ?? '',
      subdivision: json['subdivision'] as String?,
      isActive: json['isActive'] as bool? ?? true,
      center: centerMap != null ? RegionLatLng.fromJson(centerMap) : null,
    );
  }

  final String id;
  final String name;
  final String? subdivision;
  final bool isActive;
  final RegionLatLng? center;
}

class RegionLatLng {
  const RegionLatLng({required this.latitude, required this.longitude});

  factory RegionLatLng.fromJson(Map<String, dynamic> json) {
    return RegionLatLng(
      latitude: (json['latitude'] as num?)?.toDouble() ?? 0,
      longitude: (json['longitude'] as num?)?.toDouble() ?? 0,
    );
  }

  final double latitude;
  final double longitude;
}

class DefaultsDto {
  const DefaultsDto({this.countryCode, this.cityId});

  factory DefaultsDto.fromJson(Map<String, dynamic> json) {
    return DefaultsDto(
      countryCode: (json['countryCode'] as String?)?.toUpperCase(),
      cityId: json['cityId'] as String?,
    );
  }

  final String? countryCode;
  final String? cityId;
}
