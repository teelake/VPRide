import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_scope.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/app_buttons.dart';
import '../core/widgets/auth_form_widgets.dart';

class RiderLoginScreen extends StatefulWidget {
  const RiderLoginScreen({super.key});

  @override
  State<RiderLoginScreen> createState() => _RiderLoginScreenState();
}

class _RiderLoginScreenState extends State<RiderLoginScreen> {
  final _email = TextEditingController();
  final _pass = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    _pass.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final auth = AuthScope.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final err = await auth.signInWithEmail(
      email: _email.text.trim(),
      password: _pass.text,
    );
    if (!mounted) {
      return;
    }
    if (err != null) {
      messenger.showSnackBar(SnackBar(content: Text(err)));
      return;
    }
    context.go('/home');
  }

  Future<void> _google() async {
    final auth = AuthScope.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final err = await auth.signInWithGoogle();
    if (!mounted) {
      return;
    }
    if (err != null) {
      messenger.showSnackBar(SnackBar(content: Text(err)));
      return;
    }
    if (auth.isSignedIn) {
      context.go('/home');
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = AuthScope.of(context);
    final textTheme = Theme.of(context).textTheme;

    return Scaffold(
      extendBodyBehindAppBar: true,
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        title: const Text('Sign in'),
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
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.primary.withValues(alpha: 0.22),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(
                            color: AppColors.primary.withValues(alpha: 0.45),
                          ),
                        ),
                        child: Text(
                          'Welcome back',
                          style: textTheme.labelMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: AppColors.secondary.withValues(alpha: 0.75),
                            letterSpacing: 0.6,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),
                    Text(
                      'Sign in to ride',
                      style: textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.secondary,
                        letterSpacing: -0.8,
                        height: 1.05,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      'Use your email and password, or continue with Google.',
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
                            controller: _email,
                            label: 'Email',
                            hint: 'you@example.com',
                            keyboardType: TextInputType.emailAddress,
                            autocorrect: false,
                            prefixIcon: Icons.alternate_email_rounded,
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 18),
                          AuthPasswordField(
                            controller: _pass,
                            label: 'Password',
                            textInputAction: TextInputAction.done,
                            onSubmitted: (_) => _submit(),
                          ),
                          const SizedBox(height: 28),
                          AppPrimaryButton(
                            label: 'Sign in',
                            isLoading: auth.isBusy,
                            onPressed: auth.isBusy ? null : _submit,
                          ),
                          const SizedBox(height: 14),
                          AppSecondaryButton(
                            label: 'Continue with Google',
                            leading: const GoogleSignInMark(),
                            onPressed: auth.isBusy ? null : _google,
                          ),
                          const SizedBox(height: 8),
                          Center(
                            child: TextButton(
                              onPressed: auth.isBusy
                                  ? null
                                  : () => context.push('/welcome/register'),
                              child: Text(
                                'Need an account? Create one',
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
