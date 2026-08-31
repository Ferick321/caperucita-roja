import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../models/remote_config.dart';

/// Construye el tema visual a partir de la configuracion del panel.
///
/// Cambiar un color en el panel cambia el aspecto de la app sin publicar
/// una version nueva en la tienda.
class AppTheme {
  AppTheme._();

  static ThemeData build(ThemeConfig config) {
    final bool dark = config.darkMode;
    final Color surface = config.surface;
    final Color background = config.background;
    final Color text = config.text;

    final ColorScheme scheme = ColorScheme(
      brightness: dark ? Brightness.dark : Brightness.light,
      primary: config.primary,
      onPrimary: _contrast(config.primary),
      secondary: config.accent,
      onSecondary: _contrast(config.accent),
      error: const Color(0xFFEF4444),
      onError: Colors.white,
      surface: surface,
      onSurface: text,
    );

    final BorderRadius radius = BorderRadius.circular(config.radius);

    return ThemeData(
      useMaterial3: true,
      brightness: scheme.brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: background,
      canvasColor: background,

      appBarTheme: AppBarTheme(
        backgroundColor: background,
        foregroundColor: text,
        elevation: 0,
        centerTitle: false,
        systemOverlayStyle:
            dark ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
        titleTextStyle: TextStyle(
          color: text,
          fontSize: 19,
          fontWeight: FontWeight.w700,
        ),
      ),

      cardTheme: CardThemeData(
        color: surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: radius,
          side: BorderSide(color: text.withValues(alpha: 0.08)),
        ),
      ),

      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: config.primary,
          foregroundColor: _contrast(config.primary),
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(borderRadius: radius),
          textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
          elevation: 0,
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: text,
          minimumSize: const Size.fromHeight(52),
          side: BorderSide(color: text.withValues(alpha: 0.2)),
          shape: RoundedRectangleBorder(borderRadius: radius),
          textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
        ),
      ),

      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: config.primary),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: dark
            ? Color.alphaBlend(Colors.white.withValues(alpha: 0.04), surface)
            : Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: radius,
          borderSide: BorderSide(color: text.withValues(alpha: 0.12)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: radius,
          borderSide: BorderSide(color: text.withValues(alpha: 0.12)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: radius,
          borderSide: BorderSide(color: config.primary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: radius,
          borderSide: const BorderSide(color: Color(0xFFEF4444)),
        ),
        labelStyle: TextStyle(color: text.withValues(alpha: 0.7)),
        hintStyle: TextStyle(color: text.withValues(alpha: 0.4)),
      ),

      chipTheme: ChipThemeData(
        backgroundColor: text.withValues(alpha: 0.07),
        selectedColor: config.primary.withValues(alpha: 0.22),
        labelStyle: TextStyle(color: text, fontSize: 13),
        side: BorderSide.none,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
      ),

      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        backgroundColor: surface,
        selectedItemColor: config.primary,
        unselectedItemColor: text.withValues(alpha: 0.5),
        type: BottomNavigationBarType.fixed,
        elevation: 8,
      ),

      dividerTheme: DividerThemeData(
        color: text.withValues(alpha: 0.08),
        thickness: 1,
        space: 1,
      ),

      dialogTheme: DialogThemeData(
        backgroundColor: surface,
        shape: RoundedRectangleBorder(borderRadius: radius),
      ),

      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(config.radius + 6)),
        ),
      ),

      snackBarTheme: SnackBarThemeData(
        backgroundColor: surface,
        contentTextStyle: TextStyle(color: text),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: radius),
      ),

      progressIndicatorTheme: ProgressIndicatorThemeData(color: config.primary),

      textTheme: Typography.material2021().white.apply(
            bodyColor: text,
            displayColor: text,
          ),
    );
  }

  /// Elige texto claro u oscuro segun el brillo del fondo, para que siempre
  /// se lea bien independientemente del color que elija el negocio.
  static Color _contrast(Color background) =>
      background.computeLuminance() > 0.5 ? const Color(0xFF14110A) : Colors.white;

  /// Convierte "#RRGGBB" en Color (para los anuncios, que traen su color).
  static Color parseColor(String hex, Color fallback) {
    final String clean = hex.replaceAll('#', '').trim();

    if (clean.length != 6) {
      return fallback;
    }

    final int? value = int.tryParse(clean, radix: 16);

    return value == null ? fallback : Color(0xFF000000 | value);
  }
}
