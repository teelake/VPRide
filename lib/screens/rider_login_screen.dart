import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_scope.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/app_buttons.dart';

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
      backgroundColor: AppColors.surfaceMuted,
      appBar: AppBar(
        title: const Text('Sign in'),
        backgroundColor: AppColors.surfaceMuted,
        foregroundColor: AppColors.secondary,
        elevation: 0,
      ),
      body: ListenableBuilder(
        listenable: auth,
        builder: (context, _) {
          return SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Welcome back',
                  style: textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.secondary,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Sign in with the email and password you used to create your account.',
                  style: textTheme.bodyMedium?.copyWith(
                    color: AppColors.secondary.withValues(alpha: 0.6),
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 24),
                TextField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  autocorrect: false,
                  decoration: const InputDecoration(
                    labelText: 'Email',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _pass,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Password',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 28),
                AppPrimaryButton(
                  label: 'Sign in',
                  isLoading: auth.isBusy,
                  onPressed: auth.isBusy ? null : _submit,
                ),
                const SizedBox(height: 12),
                AppSecondaryButton(
                  label: 'Continue with Google',
                  onPressed: auth.isBusy ? null : _google,
                ),
                const SizedBox(height: 20),
                TextButton(
                  onPressed: auth.isBusy
                      ? null
                      : () => context.push('/welcome/register'),
                  child: const Text('Need an account? Create one'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
