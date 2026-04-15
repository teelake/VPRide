import 'package:flutter/widgets.dart';

import 'client_config_repository.dart';

class ClientConfigScope extends InheritedNotifier<ClientConfigRepository> {
  const ClientConfigScope({
    super.key,
    required ClientConfigRepository repository,
    required super.child,
  }) : super(notifier: repository);

  static ClientConfigRepository of(BuildContext context) {
    final scope = context
        .dependOnInheritedWidgetOfExactType<ClientConfigScope>();
    assert(
      scope != null,
      'ClientConfigScope not found — wrap the app with ClientConfigScope',
    );
    return scope!.notifier!;
  }

  static ClientConfigRepository? maybeOf(BuildContext context) {
    return context
        .getInheritedWidgetOfExactType<ClientConfigScope>()
        ?.notifier;
  }
}
