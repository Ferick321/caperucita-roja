import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Almacenamiento local.
///
/// Los tokens de sesion van al almacen cifrado del sistema (Keystore en
/// Android); las preferencias sin valor sensible van a SharedPreferences.
class SecureStore {
  SecureStore._();

  static final SecureStore instance = SecureStore._();

  static const FlutterSecureStorage _secure = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const String _keyAccessToken = 'access_token';
  static const String _keyRefreshToken = 'refresh_token';
  static const String _keyExpiresAt = 'access_expires_at';
  static const String _keyUser = 'user_json';
  static const String _keyDeviceId = 'device_id';

  Future<void> saveSession({
    required String accessToken,
    required String refreshToken,
    required int expiresInSeconds,
  }) async {
    final DateTime expiresAt =
        DateTime.now().add(Duration(seconds: expiresInSeconds));

    await Future.wait(<Future<void>>[
      _secure.write(key: _keyAccessToken, value: accessToken),
      _secure.write(key: _keyRefreshToken, value: refreshToken),
      _secure.write(key: _keyExpiresAt, value: expiresAt.toIso8601String()),
    ]);
  }

  Future<String?> get accessToken => _secure.read(key: _keyAccessToken);

  Future<String?> get refreshToken => _secure.read(key: _keyRefreshToken);

  Future<DateTime?> get accessTokenExpiry async {
    final String? raw = await _secure.read(key: _keyExpiresAt);

    return raw == null ? null : DateTime.tryParse(raw);
  }

  Future<void> saveUser(String json) => _secure.write(key: _keyUser, value: json);

  Future<String?> get user => _secure.read(key: _keyUser);

  Future<void> clearSession() async {
    await Future.wait(<Future<void>>[
      _secure.delete(key: _keyAccessToken),
      _secure.delete(key: _keyRefreshToken),
      _secure.delete(key: _keyExpiresAt),
      _secure.delete(key: _keyUser),
    ]);
  }

  /// Identificador estable del dispositivo, generado la primera vez.
  /// Solo sirve para reconocer la sesion; no identifica a la persona.
  Future<String> deviceId() async {
    String? stored = await _secure.read(key: _keyDeviceId);

    if (stored != null && stored.isNotEmpty) {
      return stored;
    }

    stored = DateTime.now().microsecondsSinceEpoch.toRadixString(36) +
        (100000 + DateTime.now().millisecond * 37).toRadixString(36);

    await _secure.write(key: _keyDeviceId, value: stored);

    return stored;
  }

  // ---- Preferencias sin datos sensibles --------------------------------

  Future<void> setString(String key, String value) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.setString(key, value);
  }

  Future<String?> getString(String key) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();

    return prefs.getString(key);
  }

  Future<void> setBool(String key, {required bool value}) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.setBool(key, value);
  }

  Future<bool> getBool(String key, {bool fallback = false}) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();

    return prefs.getBool(key) ?? fallback;
  }

  Future<void> remove(String key) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.remove(key);
  }
}
