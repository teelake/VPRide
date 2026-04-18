import 'package:flutter/material.dart';

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

class _HomeShellScreenState extends State<HomeShellScreen> {
  late int _index;
  bool? _hadDriverTab;

  @override
  void initState() {
    super.initState();
    _index = widget.initialTab.clamp(0, 3);
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
      });
    } else if (!isDriver && _index > 2) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) setState(() => _index = 2);
      });
    }

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
                if (isDriver) const DriverTabScreen(),
                const ProfileTabScreen(),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        height: 72,
        selectedIndex: _index.clamp(0, maxTab),
        onDestinationSelected: (i) => setState(() => _index = i),
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
