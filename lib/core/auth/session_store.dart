import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Stores the rider session token in platform secure storage (Keychain / Keystore).
final class SessionStore {
  SessionStore({FlutterSecureStorage? storage})
    : _storage =
          storage ??
          const FlutterSecureStorage(
            aOptions: AndroidOptions(encryptedSharedPreferences: true),
            iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
          );

  static const _key = 'rider_session_token_v1';

  final FlutterSecureStorage _storage;

  Future<String?> readToken() => _storage.read(key: _key);

  Future<void> writeToken(String token) =>
      _storage.write(key: _key, value: token);

  Future<void> clearToken() => _storage.delete(key: _key);
}
