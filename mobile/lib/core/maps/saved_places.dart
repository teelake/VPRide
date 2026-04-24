import 'dart:convert';

import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// A single rider-saved location (device-local only).
class SavedPlace {
  const SavedPlace({
    required this.lat,
    required this.lng,
    required this.address,
  });

  final double lat;
  final double lng;
  final String address;

  LatLng get latLng => LatLng(lat, lng);

  Map<String, dynamic> toJson() => {
        'lat': lat,
        'lng': lng,
        'address': address,
      };

  static SavedPlace? fromJson(Object? raw) {
    if (raw is! Map) {
      return null;
    }
    final m = Map<String, dynamic>.from(raw);
    final lat = m['lat'];
    final lng = m['lng'];
    if (lat is! num || lng is! num) {
      return null;
    }
    final addr = m['address']?.toString().trim() ?? '';
    return SavedPlace(
      lat: lat.toDouble(),
      lng: lng.toDouble(),
      address: addr,
    );
  }
}

/// Named shortcut (e.g. "Gym") in addition to Home / Work.
class SavedNamedPlace extends SavedPlace {
  const SavedNamedPlace({
    required this.id,
    required this.title,
    required super.lat,
    required super.lng,
    required super.address,
  });

  final String id;
  final String title;

  @override
  Map<String, dynamic> toJson() => {
        ...super.toJson(),
        'id': id,
        'title': title,
      };

  static SavedNamedPlace? fromJson(Object? raw) {
    if (raw is! Map) {
      return null;
    }
    final m = Map<String, dynamic>.from(raw);
    final base = SavedPlace.fromJson(m);
    if (base == null) {
      return null;
    }
    final id = m['id']?.toString().trim() ?? '';
    final title = m['title']?.toString().trim() ?? '';
    if (id.isEmpty || title.isEmpty) {
      return null;
    }
    return SavedNamedPlace(
      id: id,
      title: title,
      lat: base.lat,
      lng: base.lng,
      address: base.address,
    );
  }
}

/// Home / Work + up to [maxExtras] custom shortcuts.
class SavedPlacesData {
  const SavedPlacesData({
    this.home,
    this.work,
    this.extras = const [],
  });

  static const int maxExtras = 3;

  final SavedPlace? home;
  final SavedPlace? work;
  final List<SavedNamedPlace> extras;

  static const SavedPlacesData empty = SavedPlacesData();

  Map<String, dynamic> toJson() => {
        'v': 1,
        'home': home?.toJson(),
        'work': work?.toJson(),
        'extras': extras.map((e) => e.toJson()).toList(),
      };

  static SavedPlacesData fromJson(Object? raw) {
    if (raw is! Map) {
      return empty;
    }
    final m = Map<String, dynamic>.from(raw);
    final home = SavedPlace.fromJson(m['home']);
    final work = SavedPlace.fromJson(m['work']);
    final rawExtras = m['extras'];
    final extras = <SavedNamedPlace>[];
    if (rawExtras is List) {
      for (final e in rawExtras) {
        final p = SavedNamedPlace.fromJson(e);
        if (p != null && extras.length < maxExtras) {
          extras.add(p);
        }
      }
    }
    return SavedPlacesData(home: home, work: work, extras: extras);
  }
}

/// Persists saved places on-device (not synced to VP Ride servers).
final class SavedPlacesPersistence {
  SavedPlacesPersistence._();

  static const _key = 'vpride_rider_saved_places_v1';

  static Future<SavedPlacesData> load() async {
    final p = await SharedPreferences.getInstance();
    final raw = p.getString(_key);
    if (raw == null || raw.isEmpty) {
      return SavedPlacesData.empty;
    }
    try {
      final decoded = jsonDecode(raw);
      return SavedPlacesData.fromJson(decoded);
    } catch (_) {
      return SavedPlacesData.empty;
    }
  }

  static Future<void> save(SavedPlacesData data) async {
    final p = await SharedPreferences.getInstance();
    await p.setString(_key, jsonEncode(data.toJson()));
  }
}
