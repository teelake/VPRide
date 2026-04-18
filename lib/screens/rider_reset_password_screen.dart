import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_scope.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/app_buttons.dart';
import '../core/widgets/auth_form_widgets.dart';

/// In-app password reset using the token from the email link (`?token=` on this route).
class RiderResetPasswordScreen extends StatefulWidget {
  const RiderResetPasswordScreen({super.key, this.initialToken});

  final String? initialToken;

  @override
  State<RiderResetPasswordScreen> createState() =>
      _RiderResetPasswordScreenState();
}

class _RiderResetPasswordScreenState extends State<RiderResetPasswordScreen> {
  late final TextEditingController _token;
  final _pass = TextEditingController();
  final _pass2 = TextEditingController();

  @override
  void initState() {
    super.initState();
    _token = TextEditingController(text: widget.initialToken ?? '');
  }

  @override
  void dispose() {
    _token.dispose();
    _pass.dispose();
    _pass2.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final auth = AuthScope.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final t = _token.text.trim();
    final p1 = _pass.text;
    final p2 = _pass2.text;
    if (t.length < 8) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Paste the reset token from your email link.'),
        ),
      );
      return;
    }
    if (p1.length < 8) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Password must be at least 8 characters.')),
      );
      return;
    }
    if (p1 != p2) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Passwords do not match.')),
      );
      return;
    }
    final err = await auth.resetPasswordWithToken(
      token: t,
      password: p1,
      passwordConfirm: p2,
    );
    if (!mounted) {
      return;
    }
    if (err != null) {
      messenger.showSnackBar(SnackBar(content: Text(err)));
      return;
    }
    messenger.showSnackBar(
      const SnackBar(content: Text('Password updated. Sign in with your new password.')),
    );
    context.go('/welcome/login');
  }

  @override
  Widget build(BuildContext context) {
    final auth = AuthScope.of(context);
    final textTheme = Theme.of(context).textTheme;

    return Scaffold(
      extendBodyBehindAppBar: true,
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        title: const Text('New password'),
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.secondary,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
      ),
      body: AuthScreenBackdrop(
        child: SafeArea(
          child: ListenableBuilder(
            listenable: auth,
            builder: (context, _) {
              return SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(22, 8, 22, 32),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Set a new password',
                      style: textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.secondary,
                        letterSpacing: -0.8,
                        height: 1.05,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      'Paste the token from the reset link in your email, or open the link in your browser to reset there.',
                      style: textTheme.bodyLarge?.copyWith(
                        color: AppColors.secondary.withValues(alpha: 0.52),
                        height: 1.45,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 28),
                    AuthFormCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          AuthTextField(
                            controller: _token,
                            label: 'Reset token',
                            hint: 'From your email link',
                            keyboardType: TextInputType.text,
                            autocorrect: false,
                            prefixIcon: Icons.vpn_key_rounded,
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 18),
                          AuthPasswordField(
                            controller: _pass,
                            label: 'New password',
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 18),
                          AuthPasswordField(
                            controller: _pass2,
                            label: 'Confirm password',
                            textInputAction: TextInputAction.done,
                            onSubmitted: (_) => _submit(),
                          ),
                          const SizedBox(height: 28),
                          AppPrimaryButton(
                            label: 'Update password',
                            isLoading: auth.isBusy,
                            onPressed: auth.isBusy ? null : _submit,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}
