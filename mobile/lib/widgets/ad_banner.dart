import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/config/api_config.dart';
import '../core/services/ad_service.dart';
import '../core/theme/app_theme.dart';
import '../models/user_profile.dart';
import 'common.dart';

/// Tarjeta publicitaria dentro del inicio de la app.
///
/// Se pinta con los colores que definio el negocio en el panel y avisa al
/// servidor de cada vista, clic o cierre.
class AdBannerCard extends StatefulWidget {
  const AdBannerCard({super.key, required this.placement});

  final String placement;

  @override
  State<AdBannerCard> createState() => _AdBannerCardState();
}

class _AdBannerCardState extends State<AdBannerCard> {
  AppBanner? _banner;
  bool _dismissed = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final List<AppBanner> banners =
        await AdService.instance.forPlacement(widget.placement);

    if (!mounted || banners.isEmpty) {
      return;
    }

    setState(() => _banner = banners.first);
    AdService.instance.track(banners.first.id, 'impression', widget.placement);
  }

  @override
  Widget build(BuildContext context) {
    final AppBanner? banner = _banner;

    if (banner == null || _dismissed) {
      return const SizedBox.shrink();
    }

    final Color background =
        AppTheme.parseColor(banner.backgroundColor, Theme.of(context).colorScheme.surface);
    final Color foreground = AppTheme.parseColor(banner.textColor, Colors.white);

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(16),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (banner.imageUrl.isNotEmpty)
            RemoteImage(url: banner.imageUrl, height: 150),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(
                        banner.title,
                        style: TextStyle(
                          color: foreground,
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    if (banner.isDismissible)
                      IconButton(
                        icon: Icon(Icons.close, color: foreground.withValues(alpha: 0.7), size: 20),
                        tooltip: 'Cerrar',
                        onPressed: () {
                          setState(() => _dismissed = true);
                          AdService.instance.track(banner.id, 'dismiss', widget.placement);
                        },
                      ),
                  ],
                ),
                if (banner.subtitle.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      banner.subtitle,
                      style: TextStyle(color: foreground.withValues(alpha: 0.85), fontSize: 14),
                    ),
                  ),
                if (banner.hasAction)
                  Padding(
                    padding: const EdgeInsets.only(top: 14),
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: foreground,
                          foregroundColor: background,
                        ),
                        onPressed: () => _openAction(banner),
                        child: Text(banner.ctaLabel),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openAction(AppBanner banner) async {
    AdService.instance.track(banner.id, 'click', widget.placement);

    final String target = banner.ctaUrl.startsWith('/')
        ? '${ApiConfig.baseUrl}${banner.ctaUrl}'
        : banner.ctaUrl;

    final Uri? uri = Uri.tryParse(target);

    // Solo se abren enlaces http(s): nunca esquemas arbitrarios.
    if (uri == null || !<String>['http', 'https'].contains(uri.scheme)) {
      return;
    }

    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}

/// Anuncio a pantalla completa (bienvenida o intersticial).
///
/// Se muestra como hoja modal para no bloquear la navegacion.
class AdInterstitial {
  AdInterstitial._();

  static Future<void> maybeShow(BuildContext context, String placement) async {
    final List<AppBanner> banners = await AdService.instance.forPlacement(placement);

    if (banners.isEmpty || !context.mounted) {
      return;
    }

    final AppBanner banner = banners.first;

    if (banner.delaySeconds > 0) {
      await Future<void>.delayed(Duration(seconds: banner.delaySeconds));
    }

    if (!context.mounted) {
      return;
    }

    AdService.instance.track(banner.id, 'impression', placement);

    final Color background =
        AppTheme.parseColor(banner.backgroundColor, Theme.of(context).colorScheme.surface);
    final Color foreground = AppTheme.parseColor(banner.textColor, Colors.white);

    // El cierre automatico se programa junto con la apertura.
    if (banner.autoCloseSeconds > 0) {
      Future<void>.delayed(Duration(seconds: banner.autoCloseSeconds), () {
        if (context.mounted && Navigator.of(context).canPop()) {
          Navigator.of(context).maybePop();
        }
      });
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      isDismissible: banner.isDismissible,
      enableDrag: banner.isDismissible,
      backgroundColor: background,
      builder: (BuildContext sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              if (banner.isDismissible)
                Align(
                  alignment: Alignment.centerRight,
                  child: IconButton(
                    icon: Icon(Icons.close, color: foreground),
                    tooltip: 'Cerrar',
                    onPressed: () {
                      AdService.instance.track(banner.id, 'dismiss', placement);
                      Navigator.of(sheetContext).pop();
                    },
                  ),
                ),
              if (banner.imageUrl.isNotEmpty)
                RemoteImage(
                  url: banner.imageUrl,
                  height: 200,
                  borderRadius: BorderRadius.circular(14),
                ),
              const SizedBox(height: 18),
              Text(
                banner.title,
                textAlign: TextAlign.center,
                style: TextStyle(color: foreground, fontSize: 22, fontWeight: FontWeight.w700),
              ),
              if (banner.subtitle.isNotEmpty) ...<Widget>[
                const SizedBox(height: 8),
                Text(
                  banner.subtitle,
                  textAlign: TextAlign.center,
                  style: TextStyle(color: foreground.withValues(alpha: 0.85), fontSize: 15),
                ),
              ],
              if (banner.body.isNotEmpty) ...<Widget>[
                const SizedBox(height: 10),
                Text(
                  banner.body,
                  textAlign: TextAlign.center,
                  style: TextStyle(color: foreground.withValues(alpha: 0.75), fontSize: 14),
                ),
              ],
              if (banner.hasAction) ...<Widget>[
                const SizedBox(height: 22),
                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: foreground,
                    foregroundColor: background,
                    minimumSize: const Size.fromHeight(52),
                  ),
                  onPressed: () async {
                    AdService.instance.track(banner.id, 'click', placement);

                    final String target = banner.ctaUrl.startsWith('/')
                        ? '${ApiConfig.baseUrl}${banner.ctaUrl}'
                        : banner.ctaUrl;
                    final Uri? uri = Uri.tryParse(target);

                    if (uri != null && <String>['http', 'https'].contains(uri.scheme)) {
                      await launchUrl(uri, mode: LaunchMode.externalApplication);
                    }

                    if (sheetContext.mounted) {
                      Navigator.of(sheetContext).pop();
                    }
                  },
                  child: Text(banner.ctaLabel),
                ),
              ],
              const SizedBox(height: 12),
            ],
          ),
        ),
      ),
    );
  }
}
