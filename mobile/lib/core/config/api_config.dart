/// Configuracion de conexion con el servidor.
///
/// Cambia [defaultBaseUrl] por el dominio de tu negocio antes de compilar.
/// Tambien puede sobrescribirse al construir:
///   flutter build apk --dart-define=API_BASE_URL=https://mibarberia.com
class ApiConfig {
  ApiConfig._();

  static const String defaultBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://mibarberia.com',
  );

  /// Prefijo de la API. Debe coincidir con routes/api.php del servidor.
  static const String apiPrefix = '/api/v1';

  static String get baseUrl => defaultBaseUrl.replaceAll(RegExp(r'/+$'), '');

  static String get apiUrl => '$baseUrl$apiPrefix';

  /// Tiempo maximo de espera de una peticion.
  static const Duration timeout = Duration(seconds: 20);

  /// Margen antes de que caduque el token de acceso para renovarlo.
  static const Duration refreshMargin = Duration(seconds: 60);

  /// Cada cuanto se vuelve a pedir la configuracion remota.
  static const Duration configCacheTtl = Duration(hours: 6);
}
