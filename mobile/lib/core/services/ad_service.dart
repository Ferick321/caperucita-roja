import '../api/api_client.dart';
import '../../models/user_profile.dart';

/// Publicidad dentro de la app.
///
/// El servidor decide que anuncio corresponde a cada persona y cuantas veces
/// puede verlo; la app solo lo muestra y avisa de lo que ocurre con el.
class AdService {
  AdService._();

  static final AdService instance = AdService._();

  /// Ubicaciones: app_splash, app_home_card, app_interstitial.
  Future<List<AppBanner>> forPlacement(String placement, {int limit = 1}) async {
    try {
      final Map<String, dynamic> response = await ApiClient.instance.get(
        '/publicidad',
        query: <String, dynamic>{'placement': placement, 'limit': limit},
      );

      final Map<String, dynamic> data =
          (response['data'] as Map<String, dynamic>?) ?? <String, dynamic>{};

      return ((data['banners'] as List<dynamic>?) ?? <dynamic>[])
          .map((dynamic b) => AppBanner.fromJson(b as Map<String, dynamic>))
          .toList();
    } catch (_) {
      // La publicidad nunca debe impedir usar la app.
      return <AppBanner>[];
    }
  }

  Future<void> track(int bannerId, String event, String placement) async {
    try {
      await ApiClient.instance.post(
        '/publicidad/evento',
        body: <String, dynamic>{
          'banner_id': bannerId,
          'event': event,
          'placement': placement,
        },
      );
    } catch (_) {
      // La medicion es secundaria: si falla, se ignora.
    }
  }
}
