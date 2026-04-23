import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:vpride/core/config/app_config.dart';

void main() {
  test('public config path is non-empty', () {
    expect(AppConfig.publicConfigPath, isNotEmpty);
  });

  test('production API default matches vpride.ca/backend', () {
    expect(
      AppConfig.defaultProductionApiBaseUrl,
      'https://vpride.ca/backend',
    );
  });

  testWidgets('smoke: MaterialApp renders', (WidgetTester tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: Scaffold(body: Text('VP Ride'))),
    );
    expect(find.text('VP Ride'), findsOneWidget);
  });
}
