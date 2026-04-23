import 'package:flutter/widgets.dart';

import 'auth_repository.dart';

class AuthScope extends InheritedNotifier<AuthRepository> {
  const AuthScope({
    super.key,
    required AuthRepository repository,
    required super.child,
  }) : super(notifier: repository);

  static AuthRepository of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AuthScope>();
    assert(scope != null, 'AuthScope not found — wrap the app with AuthScope');
    return scope!.notifier!;
  }
}
