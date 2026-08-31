import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../storage/secure_store.dart';
import 'api_exception.dart';

/// Cliente HTTP de la aplicacion.
///
/// Se encarga de:
///  - anadir el token de acceso a cada peticion autenticada;
///  - renovarlo automaticamente cuando esta a punto de caducar;
///  - reintentar una sola vez si el servidor responde 401;
///  - traducir cualquier fallo a [ApiException] con mensaje legible.
class ApiClient {
  ApiClient._();

  static final ApiClient instance = ApiClient._();

  final http.Client _http = http.Client();
  final SecureStore _store = SecureStore.instance;

  /// Evita que varias peticiones simultaneas lancen varios refrescos.
  Future<void>? _refreshing;

  /// Se dispara cuando la sesion caduca de forma irrecuperable.
  void Function()? onSessionExpired;

  String _appVersion = '1.0.0';

  set appVersion(String value) => _appVersion = value;

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? query,
    bool authenticated = false,
  }) =>
      _send('GET', path, query: query, authenticated: authenticated);

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    bool authenticated = false,
  }) =>
      _send('POST', path, body: body, authenticated: authenticated);

  Future<Map<String, dynamic>> put(
    String path, {
    Map<String, dynamic>? body,
    bool authenticated = true,
  }) =>
      _send('PUT', path, body: body, authenticated: authenticated);

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? query,
    Map<String, dynamic>? body,
    bool authenticated = false,
    bool isRetry = false,
  }) async {
    if (authenticated) {
      await _ensureFreshToken();
    }

    final Uri uri = _buildUri(path, query);
    final Map<String, String> headers = await _headers(authenticated: authenticated);

    http.Response response;

    try {
      final Future<http.Response> request = switch (method) {
        'POST' => _http.post(uri, headers: headers, body: jsonEncode(body ?? <String, dynamic>{})),
        'PUT' => _http.put(uri, headers: headers, body: jsonEncode(body ?? <String, dynamic>{})),
        _ => _http.get(uri, headers: headers),
      };

      response = await request.timeout(ApiConfig.timeout);
    } on TimeoutException {
      throw const ApiException(
        message: 'El servidor tardo demasiado en responder. Revisa tu conexion.',
      );
    } catch (_) {
      throw const ApiException(
        message: 'No pudimos conectar. Comprueba tu conexion a internet.',
      );
    }

    // Un 401 con sesion activa merece un intento de refresco.
    if (response.statusCode == 401 && authenticated && !isRetry) {
      final bool refreshed = await _refreshSession();

      if (refreshed) {
        return _send(
          method,
          path,
          query: query,
          body: body,
          authenticated: authenticated,
          isRetry: true,
        );
      }

      await _store.clearSession();
      onSessionExpired?.call();
    }

    return _decode(response);
  }

  /// Subida de un comprobante como archivo (multipart).
  Future<Map<String, dynamic>> uploadFile(
    String path, {
    required String field,
    required String filePath,
    Map<String, String> fields = const <String, String>{},
  }) async {
    await _ensureFreshToken();

    final http.MultipartRequest request =
        http.MultipartRequest('POST', _buildUri(path, null));

    request.headers.addAll(await _headers(authenticated: true, json: false));
    request.fields.addAll(fields);
    request.files.add(await http.MultipartFile.fromPath(field, filePath));

    try {
      final http.StreamedResponse streamed =
          await request.send().timeout(const Duration(seconds: 60));

      return _decode(await http.Response.fromStream(streamed));
    } on TimeoutException {
      throw const ApiException(message: 'La subida tardo demasiado. Intentalo de nuevo.');
    }
  }

  Uri _buildUri(String path, Map<String, dynamic>? query) {
    final String url = '${ApiConfig.apiUrl}$path';

    if (query == null || query.isEmpty) {
      return Uri.parse(url);
    }

    final List<String> parts = <String>[];

    query.forEach((String key, dynamic value) {
      if (value == null) {
        return;
      }

      if (value is List) {
        for (final dynamic item in value) {
          parts.add('${Uri.encodeQueryComponent('$key[]')}='
              '${Uri.encodeQueryComponent('$item')}');
        }
      } else {
        parts.add('${Uri.encodeQueryComponent(key)}='
            '${Uri.encodeQueryComponent('$value')}');
      }
    });

    return Uri.parse('$url?${parts.join('&')}');
  }

  Future<Map<String, String>> _headers({
    required bool authenticated,
    bool json = true,
  }) async {
    final Map<String, String> headers = <String, String>{
      'Accept': 'application/json',
      'X-App-Version': _appVersion,
      // Permite al servidor distinguir la app del navegador movil.
      'User-Agent': 'EstiloApp/$_appVersion (Android)',
    };

    if (json) {
      headers['Content-Type'] = 'application/json';
    }

    if (authenticated) {
      final String? token = await _store.accessToken;

      if (token != null && token.isNotEmpty) {
        headers['Authorization'] = 'Bearer $token';
      }
    }

    return headers;
  }

  /// Renueva el token si esta caducado o a punto de caducar.
  Future<void> _ensureFreshToken() async {
    final DateTime? expiry = await _store.accessTokenExpiry;

    if (expiry == null) {
      return;
    }

    if (DateTime.now().add(ApiConfig.refreshMargin).isAfter(expiry)) {
      await _refreshSession();
    }
  }

  Future<bool> _refreshSession() async {
    // Si ya hay un refresco en marcha, se espera a ese.
    if (_refreshing != null) {
      await _refreshing;

      return (await _store.accessToken) != null;
    }

    final Completer<void> completer = Completer<void>();
    _refreshing = completer.future;

    try {
      final String? refreshToken = await _store.refreshToken;

      if (refreshToken == null || refreshToken.isEmpty) {
        return false;
      }

      final http.Response response = await _http
          .post(
            Uri.parse('${ApiConfig.apiUrl}/auth/refrescar'),
            headers: <String, String>{
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-App-Version': _appVersion,
            },
            body: jsonEncode(<String, dynamic>{'refresh_token': refreshToken}),
          )
          .timeout(ApiConfig.timeout);

      if (response.statusCode != 200) {
        return false;
      }

      final Map<String, dynamic> payload =
          jsonDecode(response.body) as Map<String, dynamic>;
      final Map<String, dynamic> data =
          (payload['data'] as Map<String, dynamic>?) ?? <String, dynamic>{};

      await _store.saveSession(
        accessToken: data['access_token'] as String? ?? '',
        refreshToken: data['refresh_token'] as String? ?? '',
        expiresInSeconds: (data['expires_in'] as num?)?.toInt() ?? 900,
      );

      if (data['user'] != null) {
        await _store.saveUser(jsonEncode(data['user']));
      }

      return true;
    } catch (_) {
      return false;
    } finally {
      completer.complete();
      _refreshing = null;
    }
  }

  Map<String, dynamic> _decode(http.Response response) {
    Map<String, dynamic> payload;

    try {
      payload = jsonDecode(response.body) as Map<String, dynamic>;
    } catch (_) {
      throw ApiException(
        message: response.statusCode >= 500
            ? 'El servidor tuvo un problema. Intentalo mas tarde.'
            : 'Respuesta no valida del servidor.',
        statusCode: response.statusCode,
      );
    }

    if (payload['ok'] == true) {
      return payload;
    }

    final Map<String, dynamic> error =
        (payload['error'] as Map<String, dynamic>?) ?? <String, dynamic>{};

    throw ApiException(
      message: error['message'] as String? ?? 'Ocurrio un error inesperado.',
      statusCode: response.statusCode,
      details: (error['details'] as Map<String, dynamic>?) ?? <String, dynamic>{},
    );
  }
}
