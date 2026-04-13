import 'package:flutter_test/flutter_test.dart';

import 'package:vpride/core/region/region_config_repository.dart';
import 'package:vpride/core/region/region_config_scope.dart';
import 'package:vpride/main.dart';

void main() {
  testWidgets('App loads button demo', (WidgetTester tester) async {
    final regionRepository = RegionConfigRepository();
    await regionRepository.loadInitial();
    await tester.pumpWidget(
      RegionConfigScope(repository: regionRepository, child: const VprideApp()),
    );
    await tester.pumpAndSettle();
    expect(find.text('Buttons'), findsOneWidget);
    expect(find.text('Book ride'), findsOneWidget);
    expect(find.textContaining('Serving:'), findsOneWidget);
    regionRepository.dispose();
  });
}
