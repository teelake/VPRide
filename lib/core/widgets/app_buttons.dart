import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

const double _kButtonHeight = 52;
const double _kSpinnerSize = 22;
const double _kStrokeWidth = 2.5;

/// Primary filled action — yellow fill, dark label; shows a spinner when [isLoading].
class AppPrimaryButton extends StatelessWidget {
  const AppPrimaryButton({
    super.key,
    required this.label,
    this.onPressed,
    this.isLoading = false,
    this.icon,
    this.expand = true,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool isLoading;
  final IconData? icon;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final busy = isLoading;
    final effectiveOnPressed = (busy || onPressed == null) ? null : onPressed;

    final button = FilledButton(
      onPressed: effectiveOnPressed,
      style: FilledButton.styleFrom(
        minimumSize: expand ? const Size.fromHeight(_kButtonHeight) : null,
        backgroundColor: AppColors.primary,
        foregroundColor: AppColors.secondary,
        disabledBackgroundColor: AppColors.primary.withValues(alpha: 0.55),
        disabledForegroundColor: AppColors.secondary.withValues(alpha: 0.45),
      ),
      child: _ButtonContent(
        label: label,
        isLoading: busy,
        icon: icon,
        indicatorColor: AppColors.secondary,
        labelColor: AppColors.secondary,
      ),
    );

    return Semantics(
      button: true,
      label: busy ? '$label, loading' : label,
      enabled: effectiveOnPressed != null,
      child: expand ? SizedBox(width: double.infinity, child: button) : button,
    );
  }
}

/// Outlined action on light surfaces — dark border and text; loading uses dark spinner.
class AppSecondaryButton extends StatelessWidget {
  const AppSecondaryButton({
    super.key,
    required this.label,
    this.onPressed,
    this.isLoading = false,
    this.icon,
    this.leading,
    this.expand = true,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool isLoading;
  final IconData? icon;
  /// Optional widget before the label (e.g. brand mark). Takes precedence over [icon].
  final Widget? leading;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final busy = isLoading;
    final effectiveOnPressed = (busy || onPressed == null) ? null : onPressed;

    final button = OutlinedButton(
      onPressed: effectiveOnPressed,
      style: OutlinedButton.styleFrom(
        minimumSize: expand ? const Size.fromHeight(_kButtonHeight) : null,
        foregroundColor: AppColors.secondary,
        side: const BorderSide(color: AppColors.secondary, width: 1.5),
        disabledForegroundColor: AppColors.secondary.withValues(alpha: 0.38),
      ),
      child: _ButtonContent(
        label: label,
        isLoading: busy,
        icon: icon,
        leading: leading,
        indicatorColor: AppColors.secondary,
        labelColor: AppColors.secondary,
      ),
    );

    return Semantics(
      button: true,
      label: busy ? '$label, loading' : label,
      enabled: effectiveOnPressed != null,
      child: expand ? SizedBox(width: double.infinity, child: button) : button,
    );
  }
}

/// Pre-approved **icon-only** mark from Google’s Sign in with Google asset pack (Android,
/// neutral / rounded). Source: https://developers.google.com/identity/branding-guidelines
/// → “Download Pre-Approved Brand Icons” (`signin-assets.zip`, `android_neutral_rd_na@3x.png`).
class GoogleSignInMark extends StatelessWidget {
  const GoogleSignInMark({super.key, this.size = 22});

  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: Image.asset(
        'assets/brand/google_signin_mark_official.png',
        fit: BoxFit.contain,
        filterQuality: FilterQuality.medium,
      ),
    );
  }
}

/// Text-only action with loading state (e.g. cancel / skip).
class AppTextLoadingButton extends StatelessWidget {
  const AppTextLoadingButton({
    super.key,
    required this.label,
    this.onPressed,
    this.isLoading = false,
    this.icon,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool isLoading;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final busy = isLoading;
    final effectiveOnPressed = (busy || onPressed == null) ? null : onPressed;

    final button = TextButton(
      onPressed: effectiveOnPressed,
      style: TextButton.styleFrom(
        foregroundColor: AppColors.secondary,
        disabledForegroundColor: AppColors.secondary.withValues(alpha: 0.38),
      ),
      child: _ButtonContent(
        label: label,
        isLoading: busy,
        icon: icon,
        indicatorColor: AppColors.secondary,
        labelColor: AppColors.secondary,
        dense: true,
      ),
    );

    return Semantics(
      button: true,
      label: busy ? '$label, loading' : label,
      enabled: effectiveOnPressed != null,
      child: button,
    );
  }
}

class _ButtonContent extends StatelessWidget {
  const _ButtonContent({
    required this.label,
    required this.isLoading,
    required this.indicatorColor,
    required this.labelColor,
    this.icon,
    this.leading,
    this.dense = false,
  });

  final String label;
  final bool isLoading;
  final Color indicatorColor;
  final Color labelColor;
  final IconData? icon;
  final Widget? leading;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return SizedBox(
        height: dense ? 24 : _kSpinnerSize,
        child: Center(
          child: SizedBox(
            width: _kSpinnerSize,
            height: _kSpinnerSize,
            child: CircularProgressIndicator(
              strokeWidth: _kStrokeWidth,
              color: indicatorColor,
            ),
          ),
        ),
      );
    }

    if (leading != null) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          leading!,
          const SizedBox(width: 10),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: labelColor),
            ),
          ),
        ],
      );
    }

    if (icon != null) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, size: 20, color: labelColor),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: labelColor),
            ),
          ),
        ],
      );
    }

    return Text(
      label,
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: TextStyle(color: labelColor),
    );
  }
}
