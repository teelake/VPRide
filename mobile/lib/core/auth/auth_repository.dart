import 'package:flutter/foundation.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../config/app_config.dart';
import 'google_auth_service.dart';
import 'session_store.dart';
import '../../models/driver_profile.dart';
import '../../models/rider_profile.dart';

/// Rider auth: Google on device + session token from PHP API.
final class AuthRepository extends ChangeNotifier {
  AuthRepository({
    required ApiClient apiClient,
    required GoogleAuthService googleAuth,
    required SessionStore sessionStore,
  }) : _api = apiClient,
       _googleAuth = googleAuth,
       _sessionStore = sessionStore;

  final ApiClient _api;
  final GoogleAuthService _googleAuth;
  final SessionStore _sessionStore;

  String? _token;
  RiderProfile? _profile;
  DriverProfile? _driverProfile;
  bool _busy = false;

  String? get sessionToken => _token;
  RiderProfile? get profile => _profile;
  DriverProfile? get driverProfile => _driverProfile;
  bool get isSignedIn => _token != null;
  bool get isBusy => _busy;

  /// Load stored token and validate with [GET /api/v1/me] when API URL is set.
  Future<void> hydrate() async {
    _token = await _sessionStore.readToken();
    notifyListeners();

    if (_token == null) return;
    final base = AppConfig.apiBaseUrl.trim();
    if (base.isEmpty) return;

    try {
      final body = await _api.getMe(_token!);
      _applyMeResponse(body);
    } on ApiException {
      await _sessionStore.clearToken();
      _token = null;
      _profile = null;
      _driverProfile = null;
      notifyListeners();
    } catch (_) {
      await _sessionStore.clearToken();
      _token = null;
      _profile = null;
      _driverProfile = null;
      notifyListeners();
    }
  }

  void _applyMeResponse(Map<String, dynamic> body) {
    final user = body['user'];
    if (user is Map<String, dynamic>) {
      _profile = RiderProfile.fromJson(user);
    }
    final d = body['driver'];
    if (d is Map<String, dynamic>) {
      _driverProfile = DriverProfile.fromJson(d);
    } else {
      _driverProfile = null;
    }
  }

  /// Refreshes profile and driver linkage from [GET /api/v1/me] without clearing the session on failure.
  Future<void> refreshProfile() async {
    final t = _token;
    if (t == null || AppConfig.apiBaseUrl.trim().isEmpty) return;
    try {
      final body = await _api.getMe(t);
      _applyMeResponse(body);
      notifyListeners();
    } on ApiException {
      // Keep cached profile if /me fails transiently.
    } catch (_) {}
  }

  /// Returns null on success, or an error message for UI.
  Future<String?> registerWithEmail({
    required String email,
    required String password,
    required String displayName,
  }) async {
    _busy = true;
    notifyListeners();
    try {
      if (AppConfig.apiBaseUrl.trim().isEmpty) {
        return 'Set API_BASE_URL when building the app.';
      }
      final out = await _api.postAuthRegister(
        email: email,
        password: password,
        displayName: displayName,
      );
      final token = out['sessionToken'] as String?;
      if (token == null || token.isEmpty) {
        return 'Server did not return a session.';
      }
      final userRaw = out['user'];
      await _sessionStore.writeToken(token);
      _token = token;
      if (userRaw is Map<String, dynamic>) {
        _profile = RiderProfile.fromJson(userRaw);
      }
      _driverProfile = null;
      notifyListeners();
      await refreshProfile();
      return null;
    } on ApiException catch (e) {
      if (e.statusCode == 409) {
        return 'That email is already registered. Sign in or use Google.';
      }
      if (e.statusCode == 403) {
        return e.message;
      }
      if (e.statusCode == 400) {
        return e.message;
      }
      return e.message;
    } catch (e) {
      return e.toString();
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  /// Returns null on success (email sent if account exists), or an error for UI.
  Future<String?> requestPasswordReset({required String email}) async {
    _busy = true;
    notifyListeners();
    try {
      if (AppConfig.apiBaseUrl.trim().isEmpty) {
        return 'Set API_BASE_URL when building the app.';
      }
      await _api.postAuthForgotPassword(email: email);
      return null;
    } on ApiException catch (e) {
      if (e.statusCode == 400) {
        return e.message;
      }
      if (e.statusCode == 429) {
        return 'Too many requests. Try again later.';
      }
      return e.message;
    } catch (e) {
      return e.toString();
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  /// Returns null on success, or an error message for UI.
  Future<String?> resetPasswordWithToken({
    required String token,
    required String password,
    required String passwordConfirm,
  }) async {
    _busy = true;
    notifyListeners();
    try {
      if (AppConfig.apiBaseUrl.trim().isEmpty) {
        return 'Set API_BASE_URL when building the app.';
      }
      await _api.postAuthResetPassword(
        token: token,
        password: password,
        passwordConfirm: passwordConfirm,
      );
      return null;
    } on ApiException catch (e) {
      if (e.statusCode == 400) {
        final m = e.message;
        if (m == 'password_mismatch') {
          return 'Passwords do not match.';
        }
        if (m == 'invalid_or_expired_token') {
          return 'This reset link is invalid or expired. Request a new one.';
        }
        if (m == 'password_too_short') {
          return 'Password must be at least 8 characters.';
        }
        return m;
      }
      if (e.statusCode == 429) {
        return 'Too many requests. Try again later.';
      }
      return e.message;
    } catch (e) {
      return e.toString();
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  /// Returns null on success, or an error message for UI.
  Future<String?> signInWithEmail({
    required String email,
    required String password,
  }) async {
    _busy = true;
    notifyListeners();
    try {
      if (AppConfig.apiBaseUrl.trim().isEmpty) {
        return 'Set API_BASE_URL when building the app.';
      }
      final out = await _api.postAuthLogin(email: email, password: password);
      final token = out['sessionToken'] as String?;
      if (token == null || token.isEmpty) {
        return 'Server did not return a session.';
      }
      final userRaw = out['user'];
      await _sessionStore.writeToken(token);
      _token = token;
      if (userRaw is Map<String, dynamic>) {
        _profile = RiderProfile.fromJson(userRaw);
      }
      _driverProfile = null;
      notifyListeners();
      await refreshProfile();
      return null;
    } on ApiException catch (e) {
      if (e.statusCode == 401) {
        return 'Wrong email or password.';
      }
      return e.message;
    } catch (e) {
      return e.toString();
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  /// Returns null on success, or a short error key / message for UI.
  Future<String?> signInWithGoogle() async {
    _busy = true;
    notifyListeners();
    try {
      if (AppConfig.apiBaseUrl.trim().isEmpty) {
        return 'Set API_BASE_URL when building the app.';
      }
      final result = await _googleAuth.signIn();
      if (result == null) return null;
      if (result.idToken == null || result.idToken!.isEmpty) {
        return 'No ID token — set the Web client ID in admin (App settings) or '
            'GOOGLE_SERVER_CLIENT_ID when building.';
      }
      final out = await _api.postAuthGoogle(result.idToken!);
      final token = out['sessionToken'] as String?;
      if (token == null || token.isEmpty) {
        return 'Server did not return a session.';
      }
      final userRaw = out['user'];
      await _sessionStore.writeToken(token);
      _token = token;
      if (userRaw is Map<String, dynamic>) {
        _profile = RiderProfile.fromJson(userRaw);
      }
      _driverProfile = null;
      notifyListeners();
      await refreshProfile();
      return null;
    } on ApiException catch (e) {
      if (e.statusCode == 400) {
        return e.message;
      }
      if (e.statusCode == 403) {
        return e.message;
      }
      if (e.statusCode == 409) {
        return 'This email already has a password account. Sign in with email, '
            'or use a different Google account.';
      }
      return e.message;
    } catch (e) {
      return e.toString();
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  /// Uploads a profile photo ([POST /api/v1/me/photo]). Returns null on success.
  Future<String?> uploadProfilePhoto(String filePath) async {
    _busy = true;
    notifyListeners();
    try {
      final t = _token;
      if (t == null) return 'Not signed in.';
      if (AppConfig.apiBaseUrl.trim().isEmpty) {
        return 'Set API_BASE_URL when building the app.';
      }
      final body = await _api.postMePhoto(
        bearerToken: t,
        filePath: filePath,
      );
      final user = body['user'];
      if (user is Map<String, dynamic>) {
        _profile = RiderProfile.fromJson(user);
      }
      notifyListeners();
      return null;
    } on ApiException catch (e) {
      if (e.statusCode == 400) {
        return e.message;
      }
      if (e.statusCode == 429) {
        return 'Too many requests. Try again later.';
      }
      return e.message;
    } catch (e) {
      return e.toString();
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  /// Updates display name via [PATCH /api/v1/me]. Returns null on success.
  Future<String?> updateDisplayName(String displayName) async {
    _busy = true;
    notifyListeners();
    try {
      final t = _token;
      if (t == null) return 'Not signed in.';
      if (AppConfig.apiBaseUrl.trim().isEmpty) {
        return 'Set API_BASE_URL when building the app.';
      }
      final body = await _api.patchMe(
        bearerToken: t,
        displayName: displayName,
      );
      final user = body['user'];
      if (user is Map<String, dynamic>) {
        _profile = RiderProfile.fromJson(user);
      }
      notifyListeners();
      return null;
    } on ApiException catch (e) {
      if (e.statusCode == 400) {
        return e.message;
      }
      if (e.statusCode == 429) {
        return 'Too many requests. Try again later.';
      }
      return e.message;
    } catch (e) {
      return e.toString();
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  /// Changes password; rotates session token. Returns null on success.
  Future<String?> changePassword({
    required String currentPassword,
    required String newPassword,
    required String newPasswordConfirm,
  }) async {
    _busy = true;
    notifyListeners();
    try {
      final t = _token;
      if (t == null) return 'Not signed in.';
      if (AppConfig.apiBaseUrl.trim().isEmpty) {
        return 'Set API_BASE_URL when building the app.';
      }
      final out = await _api.postAuthChangePassword(
        bearerToken: t,
        currentPassword: currentPassword,
        newPassword: newPassword,
        newPasswordConfirm: newPasswordConfirm,
      );
      final token = out['sessionToken'] as String?;
      if (token == null || token.isEmpty) {
        return 'Server did not return a session.';
      }
      await _sessionStore.writeToken(token);
      _token = token;
      notifyListeners();
      await refreshProfile();
      return null;
    } on ApiException catch (e) {
      if (e.statusCode == 401) {
        return e.message;
      }
      if (e.statusCode == 400) {
        return e.message;
      }
      if (e.statusCode == 429) {
        return 'Too many requests. Try again later.';
      }
      return e.message;
    } catch (e) {
      return e.toString();
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  Future<void> signOut() async {
    final t = _token;
    if (t != null && AppConfig.apiBaseUrl.trim().isNotEmpty) {
      await _api.postLogout(t);
    }
    await _googleAuth.signOut();
    await _sessionStore.clearToken();
    _token = null;
    _profile = null;
    _driverProfile = null;
    notifyListeners();
  }
}
