import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_scope.dart';
import '../core/auth/auth_repository.dart';
import '../core/auth/auth_scope.dart';
import '../core/brand/brand_assets.dart';
import '../core/client/client_config_scope.dart';
import '../core/navigation/home_shell_route.dart';
import '../core/region/region_config_scope.dart';
import '../core/theme/app_colors.dart';
import 'driver_tab_screen.dart';
import 'map_tab_screen.dart';
import 'profile_tab_screen.dart';
import 'trips_tab_screen.dart';

/// Main shell: [HomeWorkspace] switcher for fleet drivers; named routes via [HomeShellRoute].
class HomeShellScreen extends StatefulWidget {
  const HomeShellScreen({super.key, required this.initialRoute});

  final HomeShellRoute initialRoute;

  @override
  State<HomeShellScreen> createState() => _HomeShellScreenState();
}

class _HomeShellScreenState extends State<HomeShellScreen>
    with WidgetsBindingObserver {
  late HomeShellRoute _route;
  bool? _hadDriverTab;
  Timer? _driverPollTimer;
  AuthRepository? _authListenTarget;
  Set<int> _lastIncomingPollIds = {};
  bool _incomingPollPrimed = false;

  @override
  void initState() {
    super.initState();
    _route = widget.initialRoute;
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    _driverPollTimer?.cancel();
    _authListenTarget?.removeListener(_scheduleDriverPollUpdate);
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _scheduleDriverPollUpdate();
    } else {
      _driverPollTimer?.cancel();
      _driverPollTimer = null;
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final auth = AuthScope.of(context);
    if (!identical(_authListenTarget, auth)) {
      _authListenTarget?.removeListener(_scheduleDriverPollUpdate);
      _authListenTarget = auth;
      auth.addListener(_scheduleDriverPollUpdate);
    }
    _scheduleDriverPollUpdate();
  }

  void _scheduleDriverPollUpdate() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _syncDriverBackgroundPoll();
    });
  }

  bool _onDriveTab({required bool isDriver}) {
    if (!isDriver) return false;
    return _route.workspace == HomeWorkspace.driver &&
        _route.tab == HomeTabId.drive;
  }

  void _syncDriverBackgroundPoll() {
    final auth = AuthScope.of(context);
    final isDriver = auth.driverProfile != null;
    final online = auth.driverProfile?.availability == 'online';
    final resumed =
        WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed;
    final onDrive = _onDriveTab(isDriver: isDriver);
    final shouldPoll =
        isDriver && online && resumed && !onDrive && auth.sessionToken != null;

    if (!shouldPoll) {
      _driverPollTimer?.cancel();
      _driverPollTimer = null;
      return;
    }

    if (_driverPollTimer != null) return;

    _incomingPollPrimed = false;
    _lastIncomingPollIds = {};

    _driverPollTimer = Timer.periodic(const Duration(seconds: 22), (_) {
      unawaited(_runBackgroundIncomingPoll());
    });
    unawaited(_runBackgroundIncomingPoll());
  }

  Future<void> _runBackgroundIncomingPoll() async {
    if (!mounted) return;
    final auth = AuthScope.of(context);
    final token = auth.sessionToken;
    if (token == null ||
        auth.driverProfile == null ||
        auth.driverProfile!.availability != 'online' ||
        _onDriveTab(isDriver: true)) {
      return;
    }

    final api = ApiScope.of(context);
    try {
      final res = await api.getDriverRidesIncoming(token);
      if (!mounted) return;

      final raw = res['rides'];
      final ids = <int>{};
      if (raw is List) {
        for (final e in raw) {
          if (e is Map<String, dynamic>) {
            final id = e['id'];
            if (id is int) {
              ids.add(id);
            } else if (id is num) {
              ids.add(id.toInt());
            } else if (id != null) {
              final p = int.tryParse('$id');
              if (p != null) ids.add(p);
            }
          }
        }
      }

      if (!_incomingPollPrimed) {
        _lastIncomingPollIds = ids;
        _incomingPollPrimed = true;
        return;
      }

      final newIds = ids.difference(_lastIncomingPollIds);
      _lastIncomingPollIds = ids;
      if (newIds.isEmpty || !mounted) return;

      final messenger = ScaffoldMessenger.maybeOf(context);
      messenger?.showSnackBar(
        SnackBar(
          content: const Text('New ride request — open Drive to respond.'),
          action: SnackBarAction(
            label: 'Drive',
            onPressed: () {
              if (!mounted) return;
              final auth = AuthScope.of(context);
              final has = auth.driverProfile != null;
              if (!has) return;
              setState(() {
                _route = const HomeShellRoute(
                  workspace: HomeWorkspace.driver,
                  tab: HomeTabId.drive,
                );
              });
              context.go(_route.toUri().toString());
              _scheduleDriverPollUpdate();
            },
          ),
        ),
      );
    } on ApiException catch (e) {
      if (e.statusCode == 403 && e.message == 'not_a_driver') {
        await auth.refreshProfile();
      }
    } catch (_) {}
  }

  @override
  void didUpdateWidget(HomeShellScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.initialRoute != oldWidget.initialRoute) {
      _route = widget.initialRoute;
    }
  }

  void _applyRoute(HomeShellRoute next) {
    setState(() => _route = next);
    context.go(next.toUri().toString());
    _scheduleDriverPollUpdate();
  }

  @override
  Widget build(BuildContext context) {
    final repo = RegionConfigScope.of(context);
    final auth = AuthScope.of(context);
    final isDriver = auth.driverProfile != null;
    final driverAccountOnly =
        auth.profile?.driverAccountOnly == true && isDriver;
    final idx = _route.stackIndex(
      hasDriverProfile: isDriver,
      driverAccountOnly: driverAccountOnly,
    );
    final maxNav = _route.navDestinationCount(
          hasDriverProfile: isDriver,
          driverAccountOnly: driverAccountOnly,
        ) -
        1;

    if (driverAccountOnly &&
        _route.workspace == HomeWorkspace.passenger) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        final tab = _route.tab == HomeTabId.map
            ? HomeTabId.drive
            : _route.tab;
        _applyRoute(
          HomeShellRoute(workspace: HomeWorkspace.driver, tab: tab),
        );
      });
    }

    final prev = _hadDriverTab;
    _hadDriverTab = isDriver;
    if (prev != null && prev != isDriver) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        if (prev && !isDriver && _route.workspace == HomeWorkspace.driver) {
          _applyRoute(
            const HomeShellRoute(
              workspace: HomeWorkspace.passenger,
              tab: HomeTabId.map,
            ),
          );
        } else {
          _scheduleDriverPollUpdate();
        }
      });
    } else if (!isDriver && idx > 2) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        _applyRoute(
          const HomeShellRoute(
            workspace: HomeWorkspace.passenger,
            tab: HomeTabId.profile,
          ),
        );
      });
    }

    final onDriveTab = _onDriveTab(isDriver: isDriver);
    final safeIdx = idx.clamp(0, maxNav < 0 ? 0 : maxNav);
    final dualRoleDriver = isDriver && !driverAccountOnly;
    final driverFourTab =
        dualRoleDriver && _route.workspace == HomeWorkspace.driver;

    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.surface,
      appBar: AppBar(
        title: Image.asset(
          BrandAssets.logoHorizontalLightBg,
          height: 30,
          fit: BoxFit.contain,
          alignment: Alignment.centerLeft,
          filterQuality: FilterQuality.high,
        ),
        elevation: 0,
        actions: [
          IconButton(
            tooltip: 'Refresh app & region settings',
            onPressed: () async {
              await ClientConfigScope.of(context).refresh();
              await repo.refresh();
            },
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (dualRoleDriver)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: SegmentedButton<HomeWorkspace>(
                segments: const [
                  ButtonSegment(
                    value: HomeWorkspace.passenger,
                    label: Text('Ride'),
                    icon: Icon(Icons.person_rounded, size: 18),
                  ),
                  ButtonSegment(
                    value: HomeWorkspace.driver,
                    label: Text('Drive'),
                    icon: Icon(Icons.local_taxi_rounded, size: 18),
                  ),
                ],
                selected: {_route.workspace},
                onSelectionChanged: (s) {
                  final w = s.first;
                  _applyRoute(
                    _route.withWorkspace(
                      w,
                      hasDriverProfile: isDriver,
                      driverAccountOnly: driverAccountOnly,
                    ),
                  );
                },
              ),
            ),
          ListenableBuilder(
            listenable: ClientConfigScope.of(context),
            builder: (context, _) {
              final f = ClientConfigScope.of(context).features;
              if (!f.maintenanceMode) return const SizedBox.shrink();
              final msg = f.maintenanceMessage.trim().isNotEmpty
                  ? f.maintenanceMessage.trim()
                  : 'We are performing maintenance. Ride requests may be unavailable.';
              return Material(
                color: Colors.amber.shade100,
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 10,
                  ),
                  child: Text(
                    msg,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      fontWeight: FontWeight.w600,
                      color: Colors.brown.shade900,
                    ),
                  ),
                ),
              );
            },
          ),
          if (dualRoleDriver &&
              _route.workspace == HomeWorkspace.passenger &&
              _route.tab == HomeTabId.map)
            Material(
              color: AppColors.primary.withValues(alpha: 0.22),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 10,
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.local_taxi_rounded,
                      size: 20,
                      color: AppColors.secondary.withValues(alpha: 0.85),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Ride: book trips here. Switch to Drive for incoming requests.',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: AppColors.secondary.withValues(alpha: 0.88),
                          height: 1.35,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          if (driverFourTab && _route.tab == HomeTabId.map)
            Material(
              color: AppColors.primary.withValues(alpha: 0.18),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 10,
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.map_rounded,
                      size: 20,
                      color: AppColors.secondary.withValues(alpha: 0.85),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Map in Drive mode: you are booking as a rider.',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: AppColors.secondary.withValues(alpha: 0.88),
                          height: 1.35,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          Expanded(
            child: IndexedStack(
              index: safeIdx,
              sizing: StackFit.expand,
              children: [
                if (driverAccountOnly) ...[
                  DriverTabScreen(isActive: onDriveTab),
                  const TripsTabScreen(),
                  const ProfileTabScreen(),
                ] else if (driverFourTab) ...[
                  DriverTabScreen(isActive: onDriveTab),
                  const MapTabScreen(),
                  const TripsTabScreen(),
                  const ProfileTabScreen(),
                ] else ...[
                  const MapTabScreen(),
                  const TripsTabScreen(),
                  const ProfileTabScreen(),
                ],
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: NavigationBarTheme(
        data: NavigationBarThemeData(
          height: 72,
          indicatorColor: AppColors.primary.withValues(alpha: 0.35),
          labelTextStyle: WidgetStateProperty.resolveWith((states) {
            final selected = states.contains(WidgetState.selected);
            return TextStyle(
              fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
              fontSize: 12,
            );
          }),
        ),
        child: NavigationBar(
          labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
          selectedIndex: _navBarSelectedIndex(
            route: _route,
            stackIndex: safeIdx,
            hasDriver: isDriver,
            driverAccountOnly: driverAccountOnly,
          ),
          onDestinationSelected: (i) {
            final next = _route.withNavDestinationIndex(
              i,
              hasDriverProfile: isDriver,
              driverAccountOnly: driverAccountOnly,
            );
            _applyRoute(next);
          },
          destinations: driverFourTab
              ? const [
                  NavigationDestination(
                    icon: Icon(Icons.local_taxi_outlined),
                    selectedIcon: Icon(Icons.local_taxi_rounded),
                    label: 'Drive',
                  ),
                  NavigationDestination(
                    icon: Icon(Icons.map_outlined),
                    selectedIcon: Icon(Icons.map_rounded),
                    label: 'Map',
                  ),
                  NavigationDestination(
                    icon: Icon(Icons.history_outlined),
                    selectedIcon: Icon(Icons.history_rounded),
                    label: 'Trips',
                  ),
                  NavigationDestination(
                    icon: Icon(Icons.person_outline_rounded),
                    selectedIcon: Icon(Icons.person_rounded),
                    label: 'Profile',
                  ),
                ]
              : driverAccountOnly
                  ? const [
                      NavigationDestination(
                        icon: Icon(Icons.local_taxi_outlined),
                        selectedIcon: Icon(Icons.local_taxi_rounded),
                        label: 'Drive',
                      ),
                      NavigationDestination(
                        icon: Icon(Icons.history_outlined),
                        selectedIcon: Icon(Icons.history_rounded),
                        label: 'Trips',
                      ),
                      NavigationDestination(
                        icon: Icon(Icons.person_outline_rounded),
                        selectedIcon: Icon(Icons.person_rounded),
                        label: 'Profile',
                      ),
                    ]
                  : const [
                  NavigationDestination(
                    icon: Icon(Icons.map_outlined),
                    selectedIcon: Icon(Icons.map_rounded),
                    label: 'Map',
                  ),
                  NavigationDestination(
                    icon: Icon(Icons.history_outlined),
                    selectedIcon: Icon(Icons.history_rounded),
                    label: 'Trips',
                  ),
                  NavigationDestination(
                    icon: Icon(Icons.person_outline_rounded),
                    selectedIcon: Icon(Icons.person_rounded),
                    label: 'Profile',
                  ),
                ],
        ),
      ),
    );
  }

  /// Maps [stackIndex] to NavigationBar position (same order as destinations).
  int _navBarSelectedIndex({
    required HomeShellRoute route,
    required int stackIndex,
    required bool hasDriver,
    required bool driverAccountOnly,
  }) {
    if (!hasDriver || route.workspace == HomeWorkspace.passenger) {
      return stackIndex.clamp(0, 2);
    }
    if (driverAccountOnly) {
      return stackIndex.clamp(0, 2);
    }
    return stackIndex.clamp(0, 3);
  }
}
