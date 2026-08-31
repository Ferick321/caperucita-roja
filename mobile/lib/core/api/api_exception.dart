/// Error devuelto por el servidor o por la red.
///
/// Lleva el mensaje ya traducido que envia la API, de modo que la pantalla
/// solo tiene que mostrarlo sin inventar textos propios.
class ApiException implements Exception {
  const ApiException({
    required this.message,
    this.statusCode = 0,
    this.details = const <String, dynamic>{},
  });

  final String message;
  final int statusCode;
  final Map<String, dynamic> details;

  /// Sin conexion o el servidor no responde.
  bool get isNetworkError => statusCode == 0;

  /// La sesion caduco o el token no es valido.
  bool get isUnauthorized => statusCode == 401;

  /// El usuario no tiene permiso.
  bool get isForbidden => statusCode == 403;

  /// Datos invalidos: [details] trae los errores campo por campo.
  bool get isValidationError => statusCode == 422;

  /// Se pidieron demasiadas veces en poco tiempo.
  bool get isRateLimited => statusCode == 429;

  /// La app es demasiado antigua y hay que actualizarla.
  bool get needsAppUpdate => statusCode == 426;

  /// El horario elegido acaba de ocuparse.
  bool get isConflict => statusCode == 409;

  /// Primer error de un campo concreto, para marcarlo en el formulario.
  String? errorFor(String field) {
    final dynamic value = details[field];

    if (value is List && value.isNotEmpty) {
      return value.first.toString();
    }

    return value?.toString();
  }

  @override
  String toString() => message;
}
