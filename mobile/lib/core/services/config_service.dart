import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../api/api_client.dart';
import '../config/api_config.dart';
import '../storage/secure_store.dart';
import '../../models/remote_config.dart';

/// Configuracion remota del negocio.
///
/// Se guarda en cache para que la app abra al instante aunque no haya red, y
/// se refresca en segundo plano.
class ConfigService extends ChangeNotifier {
  ConfigService._();

  static final ConfigService instance = ConfigService._();

  static const String _cacheKey = 'remote_config_json';
  static const String _cacheAtKey = 'remote_config_at';

  RemoteConfig _config = RemoteConfig.fallback();
  bool _loaded = false;

  RemoteConfig get config => _config;

  bool get isLoaded => _loaded;

  /// Carga desde cache y, si toca, refresca contra el servidor.
  Future<void> load({bool force = false}) async {
    await _loadFromCache();

    final String? cachedAt = await SecureStore.instance.getString(_cacheAtKey);
    final DateTime? at = cachedAt == null ? null : DateTime.tryParse(cachedAt);
    final bool expired = at == null ||
        DateTime.now().difference(at) > ApiConfig.configCacheTtl;

    if (force || expired || !_loaded) {
      await refresh();
    }
  }

  Future<void> refresh() async {
    try {
      final Map<String, dynamic> response = await ApiClient.instance.get('/config');
      final Map<String, dynamic> data =
          (response['data'] as Map<String, dynamic>?) ?? <String, dynamic>{};

      _config = RemoteConfig.fromJson(data);
      _loaded = true;

      await SecureStore.instance.setString(_cacheKey, jsonEncode(data));
      await SecureStore.instance
          .setString(_cacheAtKey, DateTime.now().toIso8601String());

      notifyListeners();
    } catch (_) {
      // Sin red se sigue con lo ultimo que se guardo.
      if (!_loaded) {
        notifyListeners();
      }
    }
  }

  Future<void> _loadFromCache() async {
    final String? raw = await SecureStore.instance.getString(_cacheKey);

    if (raw == null || raw.isEmpty) {
      return;
    }

    try {
      _config = RemoteConfig.fromJson(jsonDecode(raw) as Map<String, dynamic>);
      _loaded = true;
      notifyListeners();
    } catch (_) {
      // Cache corrupta: se ignora y se pedira de nuevo.
    }
  }

  /// Atajo para formatear importes con la moneda del negocio.
  String money(num amount) => _config.business.money(amount);
}
