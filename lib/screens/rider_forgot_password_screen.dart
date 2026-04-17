import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_scope.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/app_buttons.dart';
import '../core/widgets/auth_form_widgets.dart';

class RiderForgotPasswordScreen extends StatefulWidget {
  const RiderForgotPasswordScreen({super.key});

  @override
  State<RiderForgotPasswordScreen> createState() =>
      _RiderForgotPasswordScreenState();
}

class _RiderForgotPasswordScreenState extends State<RiderForgotPasswordScreen> {
  final _email = TextEditingController();
  bool _sent = false;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final auth = AuthScope.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final email = _email.text.trim();
    if (email.isEmpty) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Enter your email.')),
      );
      return;
    }
    final err = await auth.requestPasswordReset(email: email);
    if (!mounted) {
      return;
    }
    if (err != null) {
      messenger.showSnackBar(SnackBar(content: Text(err)));
      return;
    }
    setState(() => _sent = true);
  }

  @override
  Widget build(BuildContext context) {
    final auth = AuthScope.of(context);
    final textTheme = Theme.of(context).textTheme;

    return Scaffold(
      extendBodyBehindAppBar: true,
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        title: const Text('Forgot password'),
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
                      'Reset by email',
                      style: textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.secondary,
                        letterSpacing: -0.8,
                        height: 1.05,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      _sent
                          ? 'If that address has a password account, check your email for a link (valid about one hour). Spam folders too.'
                          : 'We will email a reset link if this address is registered with email and password (not Google-only).',
                      style: textTheme.bodyLarge?.copyWith(
                        color: AppColors.secondary.withValues(alpha: 0.52),
                        height: 1.45,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 28),
                    if (!_sent)
                      AuthFormCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            AuthTextField(
                              controller: _email,
                              label: 'Email',
                              hint: 'you@example.com',
                              keyboardType: TextInputType.emailAddress,
                              autocorrect: false,
                              prefixIcon: Icons.alternate_email_rounded,
                              textInputAction: TextInputAction.done,
                              onSubmitted: (_) => _submit(),
                            ),
                            const SizedBox(height: 28),
                            AppPrimaryButton(
                              label: 'Send reset link',
                              isLoading: auth.isBusy,
                              onPressed: auth.isBusy ? null : _submit,
                            ),
                          ],
                        ),
                      ),
                    if (_sent) ...[
                      AppSecondaryButton(
                        label: 'Back to sign in',
                        onPressed: () => context.go('/welcome/login'),
                      ),
                    ],
                    if (!_sent) ...[
                      const SizedBox(height: 14),
                      Center(
                        child: TextButton(
                          onPressed: auth.isBusy
                              ? null
                              : () => context.pop(),
                          child: Text(
                            'Cancel',
                            style: textTheme.labelLarge?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: AppColors.secondary.withValues(
                                alpha: 0.65,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ],
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
