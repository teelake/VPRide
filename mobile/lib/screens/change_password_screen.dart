import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_scope.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/auth_form_widgets.dart';

/// In-app password change ([POST /api/v1/auth/change-password]).
class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key, this.forceEnrollment = false});

  /// When true (fleet first login), user cannot dismiss until password is updated.
  final bool forceEnrollment;

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _currentCtrl = TextEditingController();
  final _newCtrl = TextEditingController();
  final _confirmCtrl = TextEditingController();
  String? _error;
  bool _obscureCurrent = true;
  bool _obscureNew = true;
  bool _obscureConfirm = true;

  @override
  void dispose() {
    _currentCtrl.dispose();
    _newCtrl.dispose();
    _confirmCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_formKey.currentState?.validate() != true) return;
    setState(() => _error = null);
    final auth = AuthScope.of(context);
    final msg = await auth.changePassword(
      currentPassword: _currentCtrl.text,
      newPassword: _newCtrl.text,
      newPasswordConfirm: _confirmCtrl.text,
    );
    if (!mounted) return;
    if (msg != null) {
      setState(() => _error = msg);
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          widget.forceEnrollment
              ? 'Password updated. Welcome to VP Ride.'
              : 'Password updated. You stay signed in.',
        ),
      ),
    );
    if (widget.forceEnrollment) {
      context.go('/home');
    } else {
      context.pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = AuthScope.of(context);
    final theme = Theme.of(context);

    return PopScope(
      canPop: !widget.forceEnrollment,
      child: Scaffold(
        backgroundColor: AppColors.surfaceMuted,
        appBar: AppBar(
          title: Text(
            widget.forceEnrollment ? 'Set your password' : 'Change password',
          ),
          elevation: 0,
          backgroundColor: AppColors.surfaceMuted,
          foregroundColor: AppColors.secondary,
          automaticallyImplyLeading: !widget.forceEnrollment,
        ),
        body: ListenableBuilder(
          listenable: auth,
          builder: (context, _) {
            if (!auth.isSignedIn) {
              return const Center(
                child: Text('Sign in to change your password.'),
              );
            }
            if (auth.profile != null && !auth.profile!.hasPassword) {
              return Padding(
                padding: const EdgeInsets.all(24),
                child: AuthFormCard(
                  child: Text(
                    'You signed in with Google. Password change is not available for this account.',
                    style: theme.textTheme.bodyLarge?.copyWith(height: 1.45),
                  ),
                ),
              );
            }
            return SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (widget.forceEnrollment) ...[
                      Text(
                        'Your administrator created your account. Enter the temporary password from your email, then choose a new one.',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          height: 1.45,
                          color: AppColors.secondary.withValues(alpha: 0.75),
                        ),
                      ),
                      const SizedBox(height: 20),
                    ],
                    AuthFormCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          TextFormField(
                            controller: _currentCtrl,
                            obscureText: _obscureCurrent,
                            decoration: AuthFormDecor.fieldDecoration(
                              context,
                              label: 'Current password',
                              suffixIcon: IconButton(
                                icon: Icon(
                                  _obscureCurrent
                                      ? Icons.visibility_outlined
                                      : Icons.visibility_off_outlined,
                                ),
                                onPressed: () => setState(
                                  () => _obscureCurrent = !_obscureCurrent,
                                ),
                              ),
                            ),
                            validator: (v) {
                              if (v == null || v.isEmpty) {
                                return 'Enter your current password';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 18),
                          TextFormField(
                            controller: _newCtrl,
                            obscureText: _obscureNew,
                            decoration: AuthFormDecor.fieldDecoration(
                              context,
                              label: 'New password',
                              hint: 'At least 8 characters',
                              suffixIcon: IconButton(
                                icon: Icon(
                                  _obscureNew
                                      ? Icons.visibility_outlined
                                      : Icons.visibility_off_outlined,
                                ),
                                onPressed: () =>
                                    setState(() => _obscureNew = !_obscureNew),
                              ),
                            ),
                            validator: (v) {
                              final s = v ?? '';
                              if (s.length < 8) {
                                return 'Use at least 8 characters';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 18),
                          TextFormField(
                            controller: _confirmCtrl,
                            obscureText: _obscureConfirm,
                            decoration: AuthFormDecor.fieldDecoration(
                              context,
                              label: 'Confirm new password',
                              suffixIcon: IconButton(
                                icon: Icon(
                                  _obscureConfirm
                                      ? Icons.visibility_outlined
                                      : Icons.visibility_off_outlined,
                                ),
                                onPressed: () => setState(
                                  () => _obscureConfirm = !_obscureConfirm,
                                ),
                              ),
                            ),
                            validator: (v) {
                              if (v != _newCtrl.text) {
                                return 'Does not match new password';
                              }
                              return null;
                            },
                          ),
                        ],
                      ),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 16),
                      Text(
                        _error!,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.error,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: auth.isBusy ? null : _submit,
                      child: auth.isBusy
                          ? const SizedBox(
                              height: 22,
                              width: 22,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Text(
                              widget.forceEnrollment
                                  ? 'Continue'
                                  : 'Update password',
                            ),
                    ),
                    if (!widget.forceEnrollment) ...[
                      const SizedBox(height: 16),
                      TextButton(
                        onPressed: auth.isBusy
                            ? null
                            : () => context.push('/welcome/forgot-password'),
                        child: const Text('Forgot current password?'),
                      ),
                    ],
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}
