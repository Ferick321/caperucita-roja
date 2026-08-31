import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:package_info_plus/package_info_plus.dart';

import 'core/api/api_client.dart';
import 'core/services/auth_service.dart';
import 'core/services/config_service.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/login_screen.dart';
import 'features/home/main_shell.dart';
import 'features/home/splash_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // La app esta pensada en vertical.
  await SystemChrome.setPreferredOrientations(<DeviceOrientation>[
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  // La version se envia en cada peticion: el servidor puede exigir actualizar.
  try {
    final PackageInfo info = await PackageInfo.fromPlatform();
    ApiClient.instance.appVersion = info.version;
  } catch (_) {
    ApiClient.instance.appVersion = '1.0.0';
  }

  runApp(const EstiloApp());
}

class EstiloApp extends StatefulWidget {
  const EstiloApp({super.key});

  @override
  State<EstiloApp> createState() => _EstiloAppState();
}

class _EstiloAppState extends State<EstiloApp> {
  final GlobalKey<NavigatorState> _navigatorKey = GlobalKey<NavigatorState>();

  @override
  void initState() {
    super.initState();

    // Si la sesion caduca sin remedio, se vuelve al acceso.
    ApiClient.instance.onSessionExpired = () {
      _navigatorKey.currentState?.pushNamedAndRemoveUntil(
        '/login',
        (Route<dynamic> route) => false,
      );
    };
  }

  @override
  Widget build(BuildContext context) {
    // El tema se reconstruye cuando cambia la configuracion del panel.
    return AnimatedBuilder(
      animation: Listenable.merge(<Listenable>[
        ConfigService.instance,
        AuthService.instance,
      ]),
      builder: (BuildContext context, Widget? _) {
        final ThemeData theme = AppTheme.build(ConfigService.instance.config.theme);

        return MaterialApp(
          navigatorKey: _navigatorKey,
          title: ConfigService.instance.config.business.name,
          debugShowCheckedModeBanner: false,
          theme: theme,
          locale: const Locale('es'),
          supportedLocales: const <Locale>[Locale('es'), Locale('en')],
          localizationsDelegates: const <LocalizationsDelegate<dynamic>>[
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          home: const SplashScreen(),
          routes: <String, WidgetBuilder>{
            '/login': (BuildContext context) => const LoginScreen(),
            '/home': (BuildContext context) => const MainShell(),
          },
          builder: (BuildContext context, Widget? child) {
            // Evita que el tamano de letra del sistema rompa la interfaz.
            final MediaQueryData media = MediaQuery.of(context);

            return MediaQuery(
              data: media.copyWith(
                textScaler: media.textScaler.clamp(minScaleFactor: 0.85, maxScaleFactor: 1.3),
              ),
              child: child ?? const SizedBox.shrink(),
            );
          },
        );
      },
    );
  }
}
