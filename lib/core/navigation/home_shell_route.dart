import 'package:flutter/foundation.dart';

/// Bottom nav + IndexedStack layout for the signed-in home shell.
/// Query params: `layout=passenger|driver`, `tab=map|trips|drive|profile` (or legacy numeric tab index).
///
/// [driverAccountOnly]: fleet-provisioned driver logins — always driver workspace, no passenger Map tab.
@immutable
final class HomeShellRoute {
  const HomeShellRoute({
    required this.workspace,
    required this.tab,
  });

  final HomeWorkspace workspace;
  final HomeTabId tab;

  static HomeShellRoute fromUri(
    Uri uri, {
    required bool hasDriverProfile,
    bool driverAccountOnly = false,
  }) {
    final q = uri.queryParameters;
    final rawTab = (q['tab'] ?? '').trim();
    final tabLower = rawTab.toLowerCase();

    HomeWorkspace ws = HomeWorkspace.passenger;
    final layout = (q['layout'] ?? q['mode'] ?? '').toLowerCase().trim();
    if (hasDriverProfile &&
        !driverAccountOnly &&
        (layout == 'driver' || layout == 'fleet' || layout == 'work')) {
      ws = HomeWorkspace.driver;
    }

    HomeTabId tabId = HomeTabId.map;

    if (rawTab.isNotEmpty &&
        int.tryParse(rawTab) != null &&
        RegExp(r'^\d+$').hasMatch(rawTab)) {
      return _fromLegacyIndex(
        int.parse(rawTab),
        hasDriverProfile: hasDriverProfile,
        driverAccountOnly: driverAccountOnly,
      );
    }

    switch (tabLower) {
      case 'trips':
        tabId = HomeTabId.trips;
        break;
      case 'drive':
        tabId = HomeTabId.drive;
        break;
      case 'profile':
        tabId = HomeTabId.profile;
        break;
      case 'map':
      default:
        tabId = HomeTabId.map;
    }

    if (tabId == HomeTabId.drive && hasDriverProfile) {
      ws = HomeWorkspace.driver;
    }

    if (!hasDriverProfile && tabId == HomeTabId.drive) {
      tabId = HomeTabId.map;
    }

    if (driverAccountOnly && hasDriverProfile) {
      ws = HomeWorkspace.driver;
      if (tabId == HomeTabId.map) {
        tabId = HomeTabId.drive;
      }
    }

    return HomeShellRoute(workspace: ws, tab: tabId);
  }

  static HomeShellRoute _fromLegacyIndex(
    int n, {
    required bool hasDriverProfile,
    bool driverAccountOnly = false,
  }) {
    if (!hasDriverProfile) {
      final i = n.clamp(0, 2);
      const tabs = [HomeTabId.map, HomeTabId.trips, HomeTabId.profile];
      return HomeShellRoute(workspace: HomeWorkspace.passenger, tab: tabs[i]);
    }
    if (driverAccountOnly) {
      switch (n.clamp(0, 3)) {
        case 0:
          return const HomeShellRoute(
            workspace: HomeWorkspace.driver,
            tab: HomeTabId.drive,
          );
        case 1:
          return const HomeShellRoute(
            workspace: HomeWorkspace.driver,
            tab: HomeTabId.trips,
          );
        case 2:
        case 3:
        default:
          return const HomeShellRoute(
            workspace: HomeWorkspace.driver,
            tab: HomeTabId.profile,
          );
      }
    }
    switch (n.clamp(0, 3)) {
      case 0:
        return const HomeShellRoute(
          workspace: HomeWorkspace.passenger,
          tab: HomeTabId.map,
        );
      case 1:
        return const HomeShellRoute(
          workspace: HomeWorkspace.passenger,
          tab: HomeTabId.trips,
        );
      case 2:
        return const HomeShellRoute(
          workspace: HomeWorkspace.driver,
          tab: HomeTabId.drive,
        );
      case 3:
      default:
        return const HomeShellRoute(
          workspace: HomeWorkspace.passenger,
          tab: HomeTabId.profile,
        );
    }
  }

  int stackIndex({
    required bool hasDriverProfile,
    bool driverAccountOnly = false,
  }) {
    if (!hasDriverProfile) {
      switch (tab) {
        case HomeTabId.map:
          return 0;
        case HomeTabId.trips:
          return 1;
        case HomeTabId.profile:
          return 2;
        case HomeTabId.drive:
          return 0;
      }
    }
    if (driverAccountOnly) {
      switch (tab) {
        case HomeTabId.drive:
          return 0;
        case HomeTabId.trips:
          return 1;
        case HomeTabId.profile:
          return 2;
        case HomeTabId.map:
          return 0;
      }
    }
    if (workspace == HomeWorkspace.passenger) {
      switch (tab) {
        case HomeTabId.map:
          return 0;
        case HomeTabId.trips:
          return 1;
        case HomeTabId.profile:
          return 2;
        case HomeTabId.drive:
          return 0;
      }
    }
    switch (tab) {
      case HomeTabId.drive:
        return 0;
      case HomeTabId.map:
        return 1;
      case HomeTabId.trips:
        return 2;
      case HomeTabId.profile:
        return 3;
    }
  }

  int navDestinationCount({
    required bool hasDriverProfile,
    bool driverAccountOnly = false,
  }) {
    if (!hasDriverProfile) return 3;
    if (driverAccountOnly) return 3;
    return workspace == HomeWorkspace.driver ? 4 : 3;
  }

  /// Maps bottom-bar index to a new route (depends on current workspace layout).
  HomeShellRoute withNavDestinationIndex(
    int i, {
    required bool hasDriverProfile,
    bool driverAccountOnly = false,
  }) {
    if (!hasDriverProfile) {
      switch (i.clamp(0, 2)) {
        case 0:
          return const HomeShellRoute(
            workspace: HomeWorkspace.passenger,
            tab: HomeTabId.map,
          );
        case 1:
          return const HomeShellRoute(
            workspace: HomeWorkspace.passenger,
            tab: HomeTabId.trips,
          );
        case 2:
        default:
          return const HomeShellRoute(
            workspace: HomeWorkspace.passenger,
            tab: HomeTabId.profile,
          );
      }
    }
    if (driverAccountOnly) {
      switch (i.clamp(0, 2)) {
        case 0:
          return const HomeShellRoute(
            workspace: HomeWorkspace.driver,
            tab: HomeTabId.drive,
          );
        case 1:
          return const HomeShellRoute(
            workspace: HomeWorkspace.driver,
            tab: HomeTabId.trips,
          );
        case 2:
        default:
          return const HomeShellRoute(
            workspace: HomeWorkspace.driver,
            tab: HomeTabId.profile,
          );
      }
    }
    if (workspace == HomeWorkspace.passenger) {
      switch (i.clamp(0, 2)) {
        case 0:
          return const HomeShellRoute(
            workspace: HomeWorkspace.passenger,
            tab: HomeTabId.map,
          );
        case 1:
          return const HomeShellRoute(
            workspace: HomeWorkspace.passenger,
            tab: HomeTabId.trips,
          );
        case 2:
        default:
          return const HomeShellRoute(
            workspace: HomeWorkspace.passenger,
            tab: HomeTabId.profile,
          );
      }
    }
    switch (i.clamp(0, 3)) {
      case 0:
        return const HomeShellRoute(
          workspace: HomeWorkspace.driver,
          tab: HomeTabId.drive,
        );
      case 1:
        return const HomeShellRoute(
          workspace: HomeWorkspace.driver,
          tab: HomeTabId.map,
        );
      case 2:
        return const HomeShellRoute(
          workspace: HomeWorkspace.driver,
          tab: HomeTabId.trips,
        );
      case 3:
      default:
        return const HomeShellRoute(
          workspace: HomeWorkspace.driver,
          tab: HomeTabId.profile,
        );
    }
  }

  HomeShellRoute withWorkspace(
    HomeWorkspace ws, {
    required bool hasDriverProfile,
    bool driverAccountOnly = false,
  }) {
    if (!hasDriverProfile) {
      return HomeShellRoute(workspace: HomeWorkspace.passenger, tab: tab);
    }
    if (driverAccountOnly) {
      return HomeShellRoute(workspace: HomeWorkspace.driver, tab: tab);
    }
    if (ws == HomeWorkspace.driver) {
      if (tab == HomeTabId.map ||
          tab == HomeTabId.trips ||
          tab == HomeTabId.profile) {
        return HomeShellRoute(workspace: ws, tab: tab);
      }
      return const HomeShellRoute(
        workspace: HomeWorkspace.driver,
        tab: HomeTabId.drive,
      );
    }
    if (tab == HomeTabId.drive) {
      return const HomeShellRoute(
        workspace: HomeWorkspace.passenger,
        tab: HomeTabId.map,
      );
    }
    return HomeShellRoute(workspace: ws, tab: tab);
  }

  Uri toUri() {
    final layout = workspace == HomeWorkspace.driver ? 'driver' : 'passenger';
    final tabStr = switch (tab) {
      HomeTabId.map => 'map',
      HomeTabId.trips => 'trips',
      HomeTabId.drive => 'drive',
      HomeTabId.profile => 'profile',
    };
    return Uri(
      path: '/home',
      queryParameters: <String, String>{
        'layout': layout,
        'tab': tabStr,
      },
    );
  }

  @override
  bool operator ==(Object other) {
    return other is HomeShellRoute &&
        other.workspace == workspace &&
        other.tab == tab;
  }

  @override
  int get hashCode => Object.hash(workspace, tab);
}

enum HomeWorkspace { passenger, driver }

enum HomeTabId { map, trips, drive, profile }
