import 'package:flutter/material.dart';

/// Configuracion que envia el servidor al arrancar la app.
///
/// Todo lo que el negocio configura en el panel (marca, colores, textos,
/// reglas de reserva) llega por aqui, de modo que la app se adapta sin
/// necesidad de publicar una version nueva.
class RemoteConfig {
  const RemoteConfig({
    required this.business,
    required this.theme,
    required this.booking,
    required this.payments,
    required this.loyalty,
    required this.ads,
    required this.app,
    required this.maintenance,
    required this.branches,
    required this.social,
    required this.legal,
  });

  final BusinessConfig business;
  final ThemeConfig theme;
  final BookingConfig booking;
  final PaymentsConfig payments;
  final LoyaltyConfig loyalty;
  final AdsConfig ads;
  final AppConfig app;
  final MaintenanceConfig maintenance;
  final List<Branch> branches;
  final Map<String, String> social;
  final Map<String, String> legal;

  factory RemoteConfig.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic> section(String key) =>
        (json[key] as Map<String, dynamic>?) ?? <String, dynamic>{};

    return RemoteConfig(
      business: BusinessConfig.fromJson(section('business')),
      theme: ThemeConfig.fromJson(section('theme')),
      booking: BookingConfig.fromJson(section('booking')),
      payments: PaymentsConfig.fromJson(section('payments')),
      loyalty: LoyaltyConfig.fromJson(section('loyalty')),
      ads: AdsConfig.fromJson(section('ads')),
      app: AppConfig.fromJson(section('app')),
      maintenance: MaintenanceConfig.fromJson(section('maintenance')),
      branches: ((json['branches'] as List<dynamic>?) ?? <dynamic>[])
          .map((dynamic b) => Branch.fromJson(b as Map<String, dynamic>))
          .toList(),
      social: section('social').map(
        (String k, dynamic v) => MapEntry<String, String>(k, v?.toString() ?? ''),
      ),
      legal: section('legal').map(
        (String k, dynamic v) => MapEntry<String, String>(k, v?.toString() ?? ''),
      ),
    );
  }

  /// Valores de emergencia si el servidor no responde en el primer arranque.
  static RemoteConfig fallback() => RemoteConfig.fromJson(<String, dynamic>{});
}

class BusinessConfig {
  const BusinessConfig({
    required this.name,
    required this.tagline,
    required this.description,
    required this.phone,
    required this.whatsapp,
    required this.email,
    required this.address,
    required this.mapsUrl,
    required this.logoUrl,
    required this.currencySymbol,
    required this.currencyDecimals,
    required this.currencyPosition,
  });

  final String name;
  final String tagline;
  final String description;
  final String phone;
  final String whatsapp;
  final String email;
  final String address;
  final String mapsUrl;
  final String? logoUrl;
  final String currencySymbol;
  final int currencyDecimals;
  final String currencyPosition;

  factory BusinessConfig.fromJson(Map<String, dynamic> json) => BusinessConfig(
        name: json['name'] as String? ?? 'Mi Barberia',
        tagline: json['tagline'] as String? ?? '',
        description: json['description'] as String? ?? '',
        phone: json['phone'] as String? ?? '',
        whatsapp: json['whatsapp'] as String? ?? '',
        email: json['email'] as String? ?? '',
        address: json['address'] as String? ?? '',
        mapsUrl: json['maps_url'] as String? ?? '',
        logoUrl: json['logo_url'] as String?,
        currencySymbol: json['currency_symbol'] as String? ?? '\$',
        currencyDecimals: (json['currency_decimals'] as num?)?.toInt() ?? 2,
        currencyPosition: json['currency_position'] as String? ?? 'before',
      );

  /// Formatea un importe con la moneda que configuro el negocio.
  String money(num amount) {
    final String value = amount.toStringAsFixed(currencyDecimals);

    return currencyPosition == 'after'
        ? '$value $currencySymbol'
        : '$currencySymbol $value';
  }
}

class ThemeConfig {
  const ThemeConfig({
    required this.primary,
    required this.secondary,
    required this.accent,
    required this.background,
    required this.surface,
    required this.text,
    required this.darkMode,
    required this.radius,
  });

  final Color primary;
  final Color secondary;
  final Color accent;
  final Color background;
  final Color surface;
  final Color text;
  final bool darkMode;
  final double radius;

  factory ThemeConfig.fromJson(Map<String, dynamic> json) => ThemeConfig(
        primary: _color(json['primary_color'], const Color(0xFFC9A227)),
        secondary: _color(json['secondary_color'], const Color(0xFF111827)),
        accent: _color(json['accent_color'], const Color(0xFFE11D48)),
        background: _color(json['background_color'], const Color(0xFF0B0F19)),
        surface: _color(json['surface_color'], const Color(0xFF141B2D)),
        text: _color(json['text_color'], const Color(0xFFE5E7EB)),
        darkMode: json['dark_mode'] as bool? ?? true,
        radius: ((json['rounded_corners'] as num?) ?? 16).toDouble(),
      );

  /// Convierte "#RRGGBB" en un Color; si el valor no es valido usa el de reserva.
  static Color _color(dynamic value, Color fallback) {
    if (value is! String) {
      return fallback;
    }

    final String hex = value.replaceAll('#', '').trim();

    if (hex.length != 6) {
      return fallback;
    }

    final int? parsed = int.tryParse(hex, radix: 16);

    return parsed == null ? fallback : Color(0xFF000000 | parsed);
  }
}

class BookingConfig {
  const BookingConfig({
    required this.enabled,
    required this.requireLogin,
    required this.slotIntervalMinutes,
    required this.minHoursBefore,
    required this.maxDaysAhead,
    required this.allowMultipleServices,
    required this.maxServices,
    required this.allowStaffChoice,
    required this.allowNoPreference,
    required this.cancellationHours,
    required this.allowClientCancel,
    required this.customRequestEnabled,
    required this.customRequestLabel,
    required this.termsText,
  });

  final bool enabled;
  final bool requireLogin;
  final int slotIntervalMinutes;
  final int minHoursBefore;
  final int maxDaysAhead;
  final bool allowMultipleServices;
  final int maxServices;
  final bool allowStaffChoice;
  final bool allowNoPreference;
  final int cancellationHours;
  final bool allowClientCancel;
  final bool customRequestEnabled;
  final String customRequestLabel;
  final String termsText;

  factory BookingConfig.fromJson(Map<String, dynamic> json) => BookingConfig(
        enabled: json['enabled'] as bool? ?? true,
        requireLogin: json['require_login'] as bool? ?? false,
        slotIntervalMinutes: (json['slot_interval_minutes'] as num?)?.toInt() ?? 15,
        minHoursBefore: (json['min_hours_before'] as num?)?.toInt() ?? 2,
        maxDaysAhead: (json['max_days_ahead'] as num?)?.toInt() ?? 60,
        allowMultipleServices: json['allow_multiple_services'] as bool? ?? true,
        maxServices: (json['max_services'] as num?)?.toInt() ?? 4,
        allowStaffChoice: json['allow_staff_choice'] as bool? ?? true,
        allowNoPreference: json['allow_no_preference'] as bool? ?? true,
        cancellationHours: (json['cancellation_hours'] as num?)?.toInt() ?? 4,
        allowClientCancel: json['allow_client_cancel'] as bool? ?? true,
        customRequestEnabled: json['custom_request_enabled'] as bool? ?? true,
        customRequestLabel:
            json['custom_request_label'] as String? ?? 'Otro (especifica lo que necesitas)',
        termsText: json['terms_text'] as String? ?? '',
      );
}

class PaymentsConfig {
  const PaymentsConfig({
    required this.enabled,
    required this.transferInstructions,
    required this.requireDeposit,
    required this.depositPercent,
  });

  final bool enabled;
  final String transferInstructions;
  final bool requireDeposit;
  final double depositPercent;

  factory PaymentsConfig.fromJson(Map<String, dynamic> json) => PaymentsConfig(
        enabled: json['enabled'] as bool? ?? true,
        transferInstructions: json['transfer_instructions'] as String? ?? '',
        requireDeposit: json['require_deposit'] as bool? ?? false,
        depositPercent: ((json['deposit_percent'] as num?) ?? 30).toDouble(),
      );
}

class LoyaltyConfig {
  const LoyaltyConfig({required this.enabled, required this.pointsToCurrency});

  final bool enabled;
  final double pointsToCurrency;

  factory LoyaltyConfig.fromJson(Map<String, dynamic> json) => LoyaltyConfig(
        enabled: json['enabled'] as bool? ?? true,
        pointsToCurrency: ((json['points_to_currency'] as num?) ?? 100).toDouble(),
      );
}

class AdsConfig {
  const AdsConfig({required this.enabled, required this.showSplash});

  final bool enabled;
  final bool showSplash;

  factory AdsConfig.fromJson(Map<String, dynamic> json) => AdsConfig(
        enabled: json['enabled'] as bool? ?? true,
        showSplash: json['show_splash'] as bool? ?? true,
      );
}

class AppConfig {
  const AppConfig({
    required this.latestVersion,
    required this.minSupportedVersion,
    required this.updateRequired,
    required this.updateAvailable,
    required this.downloadUrl,
    required this.promoText,
  });

  final String latestVersion;
  final String minSupportedVersion;
  final bool updateRequired;
  final bool updateAvailable;
  final String downloadUrl;
  final String promoText;

  factory AppConfig.fromJson(Map<String, dynamic> json) => AppConfig(
        latestVersion: json['latest_version'] as String? ?? '1.0.0',
        minSupportedVersion: json['min_supported_version'] as String? ?? '1.0.0',
        updateRequired: json['update_required'] as bool? ?? false,
        updateAvailable: json['update_available'] as bool? ?? false,
        downloadUrl: json['download_url'] as String? ?? '',
        promoText: json['promo_text'] as String? ?? '',
      );
}

class MaintenanceConfig {
  const MaintenanceConfig({required this.active, required this.message});

  final bool active;
  final String message;

  factory MaintenanceConfig.fromJson(Map<String, dynamic> json) => MaintenanceConfig(
        active: json['active'] as bool? ?? false,
        message: json['message'] as String? ??
            'Estamos realizando mejoras. Volvemos en unos minutos.',
      );
}

class Branch {
  const Branch({
    required this.id,
    required this.name,
    required this.address,
    required this.city,
    required this.phone,
    required this.mapsUrl,
    required this.isDefault,
  });

  final int id;
  final String name;
  final String address;
  final String city;
  final String phone;
  final String mapsUrl;
  final bool isDefault;

  factory Branch.fromJson(Map<String, dynamic> json) => Branch(
        id: (json['id'] as num?)?.toInt() ?? 0,
        name: json['name'] as String? ?? '',
        address: json['address'] as String? ?? '',
        city: json['city'] as String? ?? '',
        phone: json['phone'] as String? ?? '',
        mapsUrl: json['maps_url'] as String? ?? '',
        isDefault: json['is_default'] as bool? ?? false,
      );
}
