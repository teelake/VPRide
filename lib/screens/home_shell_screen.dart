import 'dart:async';

import 'package:flutter/material.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_scope.dart';
import '../core/auth/auth_repository.dart';
import '../core/auth/auth_scope.dart';
import '../core/brand/brand_assets.dart';
import '../core/client/client_config_scope.dart';
import '../core/region/region_config_scope.dart';
import 'driver_tab_screen.dart';
import 'map_tab_screen.dart';
import 'profile_tab_screen.dart';
import 'trips_tab_screen.dart';

/// Main shell after onboarding — keeps tab state with [IndexedStack] for performance.
class HomeShellScreen extends StatefulWidget {
  const HomeShellScreen({super.key, this.initialTab = 0});

  final int initialTab;

  @override
  State<HomeShellScreen> createState() => _HomeShellScreenState();
}

class _HomeShellScreenState extends State<HomeShellScreen>
    with WidgetsBindingObserver {
  late int _index;
  bool? _hadDriverTab;
  Timer? _driverPollTimer;
  AuthRepository? _authListenTarget;
  Set<int> _lastIncomingPollIds = {};
  bool _incomingPollPrimed = false;

  @override
  void initState() {
    super.initState();
    _index = widget.initialTab.clamp(0, 3);
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

  void _syncDriverBackgroundPoll() {
    final auth = AuthScope.of(context);
    final isDriver = auth.driverProfile != null;
    final online = auth.driverProfile?.availability == 'online';
    final resumed =
        WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed;
    final onDriveTab = isDriver && _index == 2;
    final shouldPoll =
        isDriver && online && resumed && !onDriveTab && auth.sessionToken != null;

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
        _index == 2) {
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
              setState(() => _index = 2);
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
    if (oldWidget.initialTab != widget.initialTab) {
      _index = widget.initialTab.clamp(0, 3);
    }
  }

  @override
  Widget build(BuildContext context) {
    final repo = RegionConfigScope.of(context);
    final auth = AuthScope.of(context);
    final isDriver = auth.driverProfile != null;
    final maxTab = isDriver ? 3 : 2;

    final prev = _hadDriverTab;
    _hadDriverTab = isDriver;
    if (prev != null && prev != isDriver) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        if (!prev && isDriver && _index == 2) {
          setState(() => _index = 3);
        } else if (prev && !isDriver) {
          if (_index == 3) {
            setState(() => _index = 2);
          } else if (_index == 2) {
            setState(() => _index = 1);
          }
        }
        _scheduleDriverPollUpdate();
      });
    } else if (!isDriver && _index > 2) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        setState(() => _index = 2);
        _scheduleDriverPollUpdate();
      });
    }

    final onDriveTab = isDriver && _index == 2;

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
          Expanded(
            child: IndexedStack(
              index: _index.clamp(0, maxTab),
              sizing: StackFit.expand,
              children: [
                const MapTabScreen(),
                const TripsTabScreen(),
                if (isDriver) DriverTabScreen(isActive: onDriveTab),
                const ProfileTabScreen(),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        height: 72,
        selectedIndex: _index.clamp(0, maxTab),
        onDestinationSelected: (i) {
          setState(() => _index = i);
          _scheduleDriverPollUpdate();
        },
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.map_outlined),
            selectedIcon: Icon(Icons.map_rounded),
            label: 'Map',
          ),
          const NavigationDestination(
            icon: Icon(Icons.history_outlined),
            selectedIcon: Icon(Icons.history_rounded),
            label: 'Trips',
          ),
          if (isDriver)
            const NavigationDestination(
              icon: Icon(Icons.local_taxi_outlined),
              selectedIcon: Icon(Icons.local_taxi_rounded),
              label: 'Drive',
            ),
          const NavigationDestination(
            icon: Icon(Icons.person_outline_rounded),
            selectedIcon: Icon(Icons.person_rounded),
            label: 'Profile',
          ),
        ],
      ),
    );
  }
}
