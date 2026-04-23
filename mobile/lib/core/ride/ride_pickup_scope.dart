import 'package:flutter/widgets.dart';

import 'ride_pickup_controller.dart';

class RidePickupScope extends InheritedNotifier<RidePickupController> {
  const RidePickupScope({
    super.key,
    required RidePickupController controller,
    required super.child,
  }) : super(notifier: controller);

  static RidePickupController of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<RidePickupScope>();
    assert(scope != null, 'RidePickupScope not found');
    return scope!.notifier!;
  }
}
