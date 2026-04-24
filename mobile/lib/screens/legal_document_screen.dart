import 'package:flutter/material.dart';
import 'package:flutter_html/flutter_html.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_scope.dart';
import '../core/legal/legal_page_slugs.dart';
import '../core/theme/app_colors.dart';

/// Full-screen reader for CMS legal HTML from the API.
class LegalDocumentScreen extends StatefulWidget {
  const LegalDocumentScreen({super.key, required this.slug});

  /// [LegalPageSlugs.termsOfUse] or [LegalPageSlugs.privacyPolicy].
  final String slug;

  @override
  State<LegalDocumentScreen> createState() => _LegalDocumentScreenState();
}

class _LegalDocumentScreenState extends State<LegalDocumentScreen> {
  late Future<_LegalPagePayload> _future;
  var _didStartLoad = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_didStartLoad) {
      return;
    }
    _didStartLoad = true;
    _future = _load();
  }

  Future<_LegalPagePayload> _load() async {
    final client = ApiScope.of(context);
    final raw = await client.getLegalPage(widget.slug);
    final title = raw['title']?.toString().trim() ?? '';
    final html = raw['html']?.toString() ?? '';
    if (title.isEmpty) {
      throw ApiException(0, 'Missing document title');
    }
    return _LegalPagePayload(title: title, html: html);
  }

  Future<void> _retry() async {
    setState(() {
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;

    return Scaffold(
      backgroundColor: AppColors.surfaceMuted,
      appBar: AppBar(
        title: FutureBuilder<_LegalPagePayload>(
          future: _future,
          builder: (context, snap) {
            final t = snap.data?.title;
            return Text(t ?? _fallbackTitle(widget.slug));
          },
        ),
        backgroundColor: AppColors.surfaceMuted,
        foregroundColor: AppColors.secondary,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
      ),
      body: FutureBuilder<_LegalPagePayload>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'Could not load this page.',
                      style: textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.secondary,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      snap.error.toString(),
                      style: textTheme.bodySmall?.copyWith(
                        color: AppColors.secondary.withValues(alpha: 0.55),
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: _retry,
                      child: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            );
          }
          final data = snap.data!;
          return SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
            child: Html(
              data: data.html.isEmpty ? '<p>No content yet.</p>' : data.html,
              style: {
                'body': Style(
                  margin: Margins.zero,
                  padding: HtmlPaddings.zero,
                  fontSize: FontSize(16),
                  color: AppColors.secondary,
                  lineHeight: const LineHeight(1.5),
                ),
                'p': Style(
                  margin: Margins.only(bottom: 12),
                ),
                'h1': Style(
                  fontSize: FontSize(22),
                  fontWeight: FontWeight.w800,
                  color: AppColors.secondary,
                  margin: Margins.only(bottom: 12, top: 8),
                ),
                'h2': Style(
                  fontSize: FontSize(18),
                  fontWeight: FontWeight.w800,
                  color: AppColors.secondary,
                  margin: Margins.only(bottom: 10, top: 16),
                ),
                'h3': Style(
                  fontSize: FontSize(16),
                  fontWeight: FontWeight.w800,
                  color: AppColors.secondary,
                  margin: Margins.only(bottom: 8, top: 12),
                ),
                'a': Style(
                  color: AppColors.primary,
                  textDecoration: TextDecoration.underline,
                ),
                'ul': Style(
                  margin: Margins.only(bottom: 12),
                ),
                'ol': Style(
                  margin: Margins.only(bottom: 12),
                ),
                'li': Style(
                  margin: Margins.only(bottom: 6),
                ),
                'blockquote': Style(
                  border: Border(
                    left: BorderSide(
                      color: AppColors.secondary.withValues(alpha: 0.2),
                      width: 4,
                    ),
                  ),
                  padding: HtmlPaddings.only(left: 14),
                  margin: Margins.only(bottom: 12),
                  color: AppColors.secondary.withValues(alpha: 0.75),
                ),
              },
              onLinkTap: (url, attributes, element) async {
                final u = url?.trim() ?? '';
                if (u.isEmpty) {
                  return;
                }
                final uri = Uri.tryParse(u);
                if (uri == null) {
                  return;
                }
                await launchUrl(uri, mode: LaunchMode.externalApplication);
              },
            ),
          );
        },
      ),
    );
  }

  static String _fallbackTitle(String slug) {
    switch (slug) {
      case LegalPageSlugs.termsOfUse:
        return 'Terms of Use';
      case LegalPageSlugs.privacyPolicy:
        return 'Privacy Policy';
      default:
        return 'Legal';
    }
  }
}

class _LegalPagePayload {
  const _LegalPagePayload({required this.title, required this.html});

  final String title;
  final String html;
}
