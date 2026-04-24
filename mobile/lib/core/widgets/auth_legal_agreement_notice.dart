import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../theme/app_colors.dart';

/// Baseline copy with links to in-app legal readers (content from the API).
class AuthLegalAgreementNotice extends StatefulWidget {
  const AuthLegalAgreementNotice({
    super.key,
    this.textAlign = TextAlign.center,
    this.accountCreationCopy = true,
  });

  /// When false, uses "By using VP Ride…" (e.g. sign-in screen).
  final bool accountCreationCopy;

  final TextAlign textAlign;

  @override
  State<AuthLegalAgreementNotice> createState() =>
      _AuthLegalAgreementNoticeState();
}

class _AuthLegalAgreementNoticeState extends State<AuthLegalAgreementNotice> {
  late final TapGestureRecognizer _termsTap;
  late final TapGestureRecognizer _privacyTap;

  @override
  void initState() {
    super.initState();
    _termsTap = TapGestureRecognizer()..onTap = _openTerms;
    _privacyTap = TapGestureRecognizer()..onTap = _openPrivacy;
  }

  void _openTerms() {
    context.push('/welcome/terms');
  }

  void _openPrivacy() {
    context.push('/welcome/privacy');
  }

  @override
  void dispose() {
    _termsTap.dispose();
    _privacyTap.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final baseStyle = textTheme.bodySmall?.copyWith(
      color: AppColors.secondary.withValues(alpha: 0.52),
      height: 1.45,
      fontWeight: FontWeight.w500,
    );
    final linkStyle = baseStyle?.copyWith(
      color: AppColors.secondary.withValues(alpha: 0.72),
      fontWeight: FontWeight.w800,
      decoration: TextDecoration.underline,
      decorationColor: AppColors.secondary.withValues(alpha: 0.35),
    );

    return Text.rich(
      TextSpan(
        style: baseStyle,
        children: [
          TextSpan(
            text: widget.accountCreationCopy
                ? 'By creating an account, you agree to our '
                : 'By using VP Ride, you agree to our ',
          ),
          TextSpan(
            text: 'Terms of Use',
            style: linkStyle,
            recognizer: _termsTap,
          ),
          const TextSpan(text: ' and '),
          TextSpan(
            text: 'Privacy Policy',
            style: linkStyle,
            recognizer: _privacyTap,
          ),
          const TextSpan(text: '.'),
        ],
      ),
      textAlign: widget.textAlign,
    );
  }
}
