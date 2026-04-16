import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../theme/app_colors.dart';

/// Shared styling for rider auth screens (sign in / register).
abstract final class AuthFormDecor {
  static const double radius = 16;

  static InputDecoration fieldDecoration(
    BuildContext context, {
    required String label,
    String? hint,
    Widget? prefixIcon,
    Widget? suffixIcon,
  }) {
    final subtleBorder = OutlineInputBorder(
      borderRadius: BorderRadius.circular(radius),
      borderSide: BorderSide(
        color: AppColors.secondary.withValues(alpha: 0.08),
      ),
    );
    return InputDecoration(
      labelText: label,
      hintText: hint,
      prefixIcon: prefixIcon,
      suffixIcon: suffixIcon,
      filled: true,
      fillColor: const Color(0xFFFAFAFA),
      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
      labelStyle: GoogleFonts.plusJakartaSans(
        fontWeight: FontWeight.w600,
        color: AppColors.secondary.withValues(alpha: 0.45),
        fontSize: 14,
      ),
      hintStyle: GoogleFonts.plusJakartaSans(
        color: AppColors.secondary.withValues(alpha: 0.28),
        fontSize: 15,
      ),
      floatingLabelBehavior: FloatingLabelBehavior.auto,
      border: subtleBorder,
      enabledBorder: subtleBorder,
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(radius),
        borderSide: const BorderSide(color: AppColors.primary, width: 2),
      ),
    );
  }
}

/// Elevated surface for form fields (premium card).
class AuthFormCard extends StatelessWidget {
  const AuthFormCard({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(22, 26, 22, 26),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(28),
        border: Border.all(
          color: AppColors.secondary.withValues(alpha: 0.06),
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.secondary.withValues(alpha: 0.07),
            blurRadius: 48,
            offset: const Offset(0, 20),
            spreadRadius: -8,
          ),
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.12),
            blurRadius: 32,
            offset: const Offset(0, 12),
            spreadRadius: -16,
          ),
        ],
      ),
      child: child,
    );
  }
}

class AuthScreenBackdrop extends StatelessWidget {
  const AuthScreenBackdrop({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color.lerp(Colors.white, AppColors.primary, 0.12)!,
            AppColors.surfaceMuted,
            const Color(0xFFF3F3F3),
          ],
          stops: const [0.0, 0.45, 1.0],
        ),
      ),
      child: child,
    );
  }
}

class AuthTextField extends StatelessWidget {
  const AuthTextField({
    super.key,
    required this.controller,
    required this.label,
    this.hint,
    this.keyboardType = TextInputType.text,
    this.textCapitalization = TextCapitalization.none,
    this.autocorrect = true,
    this.prefixIcon,
    this.textInputAction,
    this.onSubmitted,
  });

  final TextEditingController controller;
  final String label;
  final String? hint;
  final TextInputType keyboardType;
  final TextCapitalization textCapitalization;
  final bool autocorrect;
  final IconData? prefixIcon;
  final TextInputAction? textInputAction;
  final void Function(String)? onSubmitted;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
      textCapitalization: textCapitalization,
      autocorrect: autocorrect,
      textInputAction: textInputAction,
      onSubmitted: onSubmitted,
      style: GoogleFonts.plusJakartaSans(
        fontWeight: FontWeight.w600,
        fontSize: 16,
        color: AppColors.secondary,
        letterSpacing: -0.2,
      ),
      decoration: AuthFormDecor.fieldDecoration(
        context,
        label: label,
        hint: hint,
        prefixIcon: prefixIcon != null
            ? Icon(
                prefixIcon,
                size: 22,
                color: AppColors.secondary.withValues(alpha: 0.35),
              )
            : null,
      ),
    );
  }
}

class AuthPasswordField extends StatefulWidget {
  const AuthPasswordField({
    super.key,
    required this.controller,
    required this.label,
    this.hint,
    this.textInputAction,
    this.onSubmitted,
  });

  final TextEditingController controller;
  final String label;
  final String? hint;
  final TextInputAction? textInputAction;
  final void Function(String)? onSubmitted;

  @override
  State<AuthPasswordField> createState() => _AuthPasswordFieldState();
}

class _AuthPasswordFieldState extends State<AuthPasswordField> {
  bool _obscure = true;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: widget.controller,
      obscureText: _obscure,
      textInputAction: widget.textInputAction,
      onSubmitted: widget.onSubmitted,
      style: GoogleFonts.plusJakartaSans(
        fontWeight: FontWeight.w600,
        fontSize: 16,
        color: AppColors.secondary,
        letterSpacing: _obscure ? 1.2 : -0.2,
      ),
      decoration: AuthFormDecor.fieldDecoration(
        context,
        label: widget.label,
        hint: widget.hint,
        prefixIcon: Icon(
          Icons.lock_outline_rounded,
          size: 22,
          color: AppColors.secondary.withValues(alpha: 0.35),
        ),
        suffixIcon: IconButton(
          tooltip: _obscure ? 'Show password' : 'Hide password',
          onPressed: () => setState(() => _obscure = !_obscure),
          icon: Icon(
            _obscure
                ? Icons.visibility_outlined
                : Icons.visibility_off_outlined,
            color: AppColors.secondary.withValues(alpha: 0.45),
          ),
        ),
      ),
    );
  }
}
