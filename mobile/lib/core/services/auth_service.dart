import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../api/api_client.dart';
import '../storage/secure_store.dart';
import '../../models/user_profile.dart';

/// Sesion del cliente en la app.
class AuthService extends ChangeNotifier {
  AuthService._();

  static final AuthService instance = AuthService._();

  final SecureStore _store = SecureStore.instance;

  UserProfile? _user;
  bool _checked = false;

  UserProfile? get user => _user;

  bool get isLoggedIn => _user != null;

  bool get sessionChecked => _checked;

  /// Restaura la sesion guardada al abrir la app.
  Future<void> restore() async {
    final String? token = await _store.accessToken;

    if (token == null || token.isEmpty) {
      _checked = true;
      notifyListeners();

      return;
    }

    final String? cached = await _store.user;

    if (cached != null && cached.isNotEmpty) {
      try {
        _user = UserProfile.fromJson(jsonDecode(cached) as Map<String, dynamic>);
        notifyListeners();
      } catch (_) {
        // Perfil en cache ilegible: se pedira al servidor.
      }
    }

    // Se confirma contra el servidor: si el token ya no vale, se cierra sesion.
    try {
      await refreshProfile();
    } catch (_) {
      await logout(notifyServer: false);
    }

    _checked = true;
    notifyListeners();
  }

  Future<void> register({
    required String firstName,
    required String lastName,
    required String email,
    required String phone,
    required String password,
    required bool acceptsMarketing,
  }) async {
    final Map<String, dynamic> response = await ApiClient.instance.post(
      '/auth/registro',
      body: <String, dynamic>{
        'first_name': firstName,
        'last_name': lastName,
        'email': email,
        'phone': phone,
        'password': password,
        'accepts_terms': true,
        'accepts_marketing': acceptsMarketing,
        'device_id': await _store.deviceId(),
        'platform': 'android',
      },
    );

    await _persist(response);
  }

  Future<void> login({required String email, required String password}) async {
    final Map<String, dynamic> response = await ApiClient.instance.post(
      '/auth/login',
      body: <String, dynamic>{
        'email': email,
        'password': password,
        'device_id': await _store.deviceId(),
        'platform': 'android',
      },
    );

    await _persist(response);
  }

  Future<void> forgotPassword(String email) async {
    await ApiClient.instance.post(
      '/auth/recuperar',
      body: <String, dynamic>{'email': email},
    );
  }

  Future<void> logout({bool notifyServer = true}) async {
    if (notifyServer) {
      try {
        final String? refresh = await _store.refreshToken;

        await ApiClient.instance.post(
          '/auth/salir',
          body: <String, dynamic>{'refresh_token': refresh ?? ''},
        );
      } catch (_) {
        // Aunque el servidor no responda, la sesion local se limpia.
      }
    }

    await _store.clearSession();
    _user = null;
    notifyListeners();
  }

  Future<void> refreshProfile() async {
    final Map<String, dynamic> response =
        await ApiClient.instance.get('/perfil', authenticated: true);

    final Map<String, dynamic> data =
        (response['data'] as Map<String, dynamic>?) ?? <String, dynamic>{};

    _user = UserProfile.fromJson(data);
    await _store.saveUser(jsonEncode(data));
    notifyListeners();
  }

  Future<void> updateProfile(Map<String, dynamic> fields) async {
    final Map<String, dynamic> response =
        await ApiClient.instance.put('/perfil', body: fields);

    final Map<String, dynamic> data =
        (response['data'] as Map<String, dynamic>?) ?? <String, dynamic>{};

    _user = UserProfile.fromJson(data);
    await _store.saveUser(jsonEncode(data));
    notifyListeners();
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    await ApiClient.instance.post(
      '/perfil/clave',
      body: <String, dynamic>{
        'current_password': currentPassword,
        'password': newPassword,
      },
      authenticated: true,
    );

    // El servidor invalida todas las sesiones: hay que volver a entrar.
    await _store.clearSession();
    _user = null;
    notifyListeners();
  }

  Future<void> deleteAccount(String password) async {
    await ApiClient.instance.post(
      '/perfil/eliminar',
      body: <String, dynamic>{'password': password, 'confirm': 'ELIMINAR'},
      authenticated: true,
    );

    await _store.clearSession();
    _user = null;
    notifyListeners();
  }

  Future<void> _persist(Map<String, dynamic> response) async {
    final Map<String, dynamic> data =
        (response['data'] as Map<String, dynamic>?) ?? <String, dynamic>{};

    await _store.saveSession(
      accessToken: data['access_token'] as String? ?? '',
      refreshToken: data['refresh_token'] as String? ?? '',
      expiresInSeconds: (data['expires_in'] as num?)?.toInt() ?? 900,
    );

    final Map<String, dynamic> userJson =
        (data['user'] as Map<String, dynamic>?) ?? <String, dynamic>{};

    _user = UserProfile.fromJson(userJson);
    await _store.saveUser(jsonEncode(userJson));

    notifyListeners();
  }
}
