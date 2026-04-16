import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_scope.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/app_buttons.dart';
import '../core/widgets/auth_form_widgets.dart';

/// Email + password registration → same session shape as Google.
class RiderRegisterScreen extends StatefulWidget {
  const RiderRegisterScreen({super.key});

  @override
  State<RiderRegisterScreen> createState() => _RiderRegisterScreenState();
}

class _RiderRegisterScreenState extends State<RiderRegisterScreen> {
  final _email = TextEditingController();
  final _name = TextEditingController();
  final _pass = TextEditingController();
  final _pass2 = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    _name.dispose();
    _pass.dispose();
    _pass2.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final auth = AuthScope.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final email = _email.text.trim();
    final name = _name.text.trim();
    final p1 = _pass.text;
    final p2 = _pass2.text;
    if (email.isEmpty) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Enter your email.')),
      );
      return;
    }
    if (name.isEmpty) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Enter your full name.')),
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
    final err = await auth.registerWithEmail(
      email: email,
      password: p1,
      displayName: name,
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

  @override
  Widget build(BuildContext context) {
    final auth = AuthScope.of(context);
    final textTheme = Theme.of(context).textTheme;

    return Scaffold(
      extendBodyBehindAppBar: true,
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        title: const Text('Create account'),
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
                          'Rider account',
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
                      'Join VP Ride',
                      style: textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.secondary,
                        letterSpacing: -0.8,
                        height: 1.05,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      'Create your profile with email. You’ll use this name when you ride.',
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
                          AuthTextField(
                            controller: _name,
                            label: 'Full name',
                            hint: 'First and last name',
                            textCapitalization: TextCapitalization.words,
                            autocorrect: false,
                            prefixIcon: Icons.person_outline_rounded,
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 18),
                          AuthPasswordField(
                            controller: _pass,
                            label: 'Password',
                            hint: '8+ characters',
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
                            label: 'Create account',
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
