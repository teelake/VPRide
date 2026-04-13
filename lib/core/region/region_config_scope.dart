import 'package:flutter/widgets.dart';

import 'region_config_repository.dart';
import 'resolved_region_config.dart';

/// Provides [RegionConfigRepository] to the widget tree. Extends [InheritedNotifier]
/// so widgets using [of] rebuild when the repository calls [ChangeNotifier.notifyListeners].
class RegionConfigScope extends InheritedNotifier<RegionConfigRepository> {
  const RegionConfigScope({
    super.key,
    required RegionConfigRepository repository,
    required super.child,
  }) : super(notifier: repository);

  /// The repository for this app. Registers a dependency — the caller rebuilds on refresh.
  static RegionConfigRepository of(BuildContext context) {
    final scope = context
        .dependOnInheritedWidgetOfExactType<RegionConfigScope>();
    assert(
      scope != null,
      'RegionConfigScope not found — wrap MaterialApp with RegionConfigScope',
    );
    return scope!.notifier!;
  }

  /// Same as [of] without asserting; returns null if absent.
  static RegionConfigRepository? maybeOf(BuildContext context) {
    return context.getInheritedWidgetOfExactType<RegionConfigScope>()?.notifier;
  }

  /// Shorthand for [of](context).resolved.
  static ResolvedRegionConfig resolvedOf(BuildContext context) =>
      of(context).resolved;
}
