import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../core/auth/auth_scope.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/auth_form_widgets.dart';

/// Edit rider display name ([PATCH /api/v1/me]).
class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({super.key});

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  String? _error;
  bool _seededName = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_seededName) return;
    _seededName = true;
    final p = AuthScope.of(context).profile;
    final n = p?.displayName?.trim();
    if (n != null && n.isNotEmpty) {
      _nameCtrl.text = n;
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickAndUploadPhoto() async {
    final picker = ImagePicker();
    final x = await picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 1600,
      maxHeight: 1600,
      imageQuality: 88,
    );
    if (x == null || !mounted) return;
    setState(() => _error = null);
    final auth = AuthScope.of(context);
    final msg = await auth.uploadProfilePhoto(x.path);
    if (!mounted) return;
    if (msg != null) {
      setState(() => _error = msg);
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Photo updated')),
    );
  }

  Future<void> _save() async {
    final err = _formKey.currentState?.validate();
    if (err != true) return;
    setState(() => _error = null);
    final auth = AuthScope.of(context);
    final msg = await auth.updateDisplayName(_nameCtrl.text.trim());
    if (!mounted) return;
    if (msg != null) {
      setState(() => _error = msg);
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Profile updated')),
    );
    context.pop();
  }

  @override
  Widget build(BuildContext context) {
    final auth = AuthScope.of(context);
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: AppColors.surfaceMuted,
      appBar: AppBar(
        title: const Text('Edit profile'),
        elevation: 0,
        backgroundColor: AppColors.surfaceMuted,
        foregroundColor: AppColors.secondary,
      ),
      body: ListenableBuilder(
        listenable: auth,
        builder: (context, _) {
          if (!auth.isSignedIn) {
            return const Center(child: Text('Sign in to edit your profile.'));
          }
          return SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  AuthFormCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Profile photo',
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'JPEG, PNG, or WebP · up to 2 MB',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.secondary.withValues(alpha: 0.5),
                          ),
                        ),
                        const SizedBox(height: 16),
                        OutlinedButton.icon(
                          onPressed: auth.isBusy ? null : _pickAndUploadPhoto,
                          icon: const Icon(Icons.photo_camera_outlined),
                          label: const Text('Choose photo'),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  AuthFormCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Name',
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Shown to drivers and in your trip history.',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.secondary.withValues(alpha: 0.5),
                          ),
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          controller: _nameCtrl,
                          textCapitalization: TextCapitalization.words,
                          decoration: AuthFormDecor.fieldDecoration(
                            context,
                            label: 'Display name',
                            hint: 'Your name',
                          ),
                          validator: (v) {
                            final s = v?.trim() ?? '';
                            if (s.isEmpty) return 'Enter your name';
                            if (s.length > 255) return 'Name is too long';
                            return null;
                          },
                          onFieldSubmitted: (_) => _save(),
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
                    onPressed: auth.isBusy ? null : _save,
                    child: auth.isBusy
                        ? const SizedBox(
                            height: 22,
                            width: 22,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Save'),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
