import 'package:flutter/material.dart';

import '../core/brand/brand_assets.dart';
import '../core/client/client_config_scope.dart';
import '../core/region/region_config_scope.dart';
import 'map_tab_screen.dart';
import 'profile_tab_screen.dart';

/// Main shell after onboarding — keeps tab state with [IndexedStack] for performance.
class HomeShellScreen extends StatefulWidget {
  const HomeShellScreen({super.key, this.initialTab = 0});

  final int initialTab;

  @override
  State<HomeShellScreen> createState() => _HomeShellScreenState();
}

class _HomeShellScreenState extends State<HomeShellScreen> {
  late int _index;

  @override
  void initState() {
    super.initState();
    _index = widget.initialTab.clamp(0, 1);
  }

  @override
  void didUpdateWidget(HomeShellScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.initialTab != widget.initialTab) {
      _index = widget.initialTab.clamp(0, 1);
    }
  }

  @override
  Widget build(BuildContext context) {
    final repo = RegionConfigScope.of(context);

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
              index: _index,
              sizing: StackFit.expand,
              children: const [MapTabScreen(), ProfileTabScreen()],
            ),
          ),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        height: 72,
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.map_outlined),
            selectedIcon: Icon(Icons.map_rounded),
            label: 'Map',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline_rounded),
            selectedIcon: Icon(Icons.person_rounded),
            label: 'Profile',
          ),
        ],
      ),
    );
  }
}
