import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/services/auth_service.dart';
import '../../core/services/config_service.dart';
import '../../models/remote_config.dart';
import '../../widgets/ad_banner.dart';
import '../../widgets/common.dart';
import 'main_shell.dart';

/// Pantalla de arranque.
///
/// Carga la configuracion del negocio y restaura la sesion. Si el negocio
/// activo un anuncio de bienvenida, se muestra antes de entrar.
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  String? _error;

  @override
  void initState() {
    super.initState();
    _boot();
  }

  Future<void> _boot() async {
    setState(() => _error = null);

    try {
      await ConfigService.instance.load();
      await AuthService.instance.restore();
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'No pudimos conectar con el servidor. Revisa tu conexion.');
      }

      return;
    }

    if (!mounted) {
      return;
    }

    final RemoteConfig config = ConfigService.instance.config;

    // Version demasiado antigua: no se deja continuar.
    if (config.app.updateRequired) {
      await _showUpdateDialog(config);

      return;
    }

    if (config.maintenance.active) {
      setState(() => _error = config.maintenance.message);

      return;
    }

    await Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(builder: (BuildContext context) => const MainShell()),
    );

    // El anuncio de bienvenida se muestra ya dentro de la app.
    if (mounted && config.ads.enabled && config.ads.showSplash) {
      await AdInterstitial.maybeShow(context, 'app_splash');
    }
  }

  Future<void> _showUpdateDialog(RemoteConfig config) async {
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Actualiza la aplicacion'),
        content: Text(
          'Hay una version nueva de ${config.business.name} con mejoras importantes. '
          'Necesitas actualizarla para seguir agendando.',
        ),
        actions: <Widget>[
          if (config.app.downloadUrl.isNotEmpty)
            ElevatedButton(
              onPressed: () async {
                final Uri? uri = Uri.tryParse(config.app.downloadUrl);

                if (uri != null && <String>['http', 'https'].contains(uri.scheme)) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                }
              },
              child: const Text('Actualizar ahora'),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final RemoteConfig config = ConfigService.instance.config;
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              if (config.business.logoUrl != null)
                RemoteImage(
                  url: config.business.logoUrl,
                  height: 92,
                  fit: BoxFit.contain,
                )
              else
                Icon(Icons.content_cut_rounded, size: 72, color: theme.colorScheme.primary),

              const SizedBox(height: 22),

              Text(
                config.business.name,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w700),
              ),

              if (config.business.tagline.isNotEmpty) ...<Widget>[
                const SizedBox(height: 6),
                Text(
                  config.business.tagline,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: theme.colorScheme.onSurface.withValues(alpha: 0.65),
                  ),
                ),
              ],

              const SizedBox(height: 40),

              if (_error == null)
                const CircularProgressIndicator()
              else ...<Widget>[
                Text(
                  _error!,
                  textAlign: TextAlign.center,
                  style: TextStyle(color: theme.colorScheme.error),
                ),
                const SizedBox(height: 20),
                SizedBox(
                  width: 220,
                  child: ElevatedButton(onPressed: _boot, child: const Text('Reintentar')),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
