import 'package:estilo_app/models/appointment.dart';
import 'package:estilo_app/models/catalog.dart';
import 'package:estilo_app/models/remote_config.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Pruebas de los modelos que traducen la respuesta del servidor.
///
/// Son la frontera entre la API y la interfaz: si el servidor devuelve un
/// campo vacio o ausente, la app no debe romperse.
void main() {
  group('RemoteConfig', () {
    test('usa valores por defecto cuando el servidor no envia nada', () {
      final RemoteConfig config = RemoteConfig.fallback();

      expect(config.business.name, isNotEmpty);
      expect(config.booking.enabled, isTrue);
      expect(config.branches, isEmpty);
      expect(config.theme.primary, isA<Color>());
    });

    test('convierte los colores hexadecimales del panel', () {
      final RemoteConfig config = RemoteConfig.fromJson(<String, dynamic>{
        'theme': <String, dynamic>{
          'primary_color': '#FF0000',
          'rounded_corners': 24,
        },
      });

      expect(config.theme.primary, const Color(0xFFFF0000));
      expect(config.theme.radius, 24);
    });

    test('un color invalido no rompe la app', () {
      final RemoteConfig config = RemoteConfig.fromJson(<String, dynamic>{
        'theme': <String, dynamic>{'primary_color': 'no-es-un-color'},
      });

      expect(config.theme.primary, const Color(0xFFC9A227));
    });

    test('formatea el importe con la moneda del negocio', () {
      final RemoteConfig config = RemoteConfig.fromJson(<String, dynamic>{
        'business': <String, dynamic>{
          'currency_symbol': 'S/',
          'currency_decimals': 2,
          'currency_position': 'after',
        },
      });

      expect(config.business.money(12.5), '12.50 S/');
    });
  });

  group('Service', () {
    test('presenta la duracion en formato legible', () {
      Service build(int minutes) => Service.fromJson(<String, dynamic>{
            'id': 1,
            'duration_minutes': minutes,
          });

      expect(build(30).durationLabel, '30 min');
      expect(build(60).durationLabel, '1 h');
      expect(build(90).durationLabel, '1 h 30 min');
    });
  });

  group('StaffMember', () {
    test('calcula las iniciales para el avatar', () {
      final StaffMember member = StaffMember.fromJson(<String, dynamic>{
        'id': 1,
        'name': 'Ana Maria Perez',
      });

      expect(member.initials, 'AM');
    });

    test('sin servicios asignados puede atender cualquiera', () {
      final StaffMember member =
          StaffMember.fromJson(<String, dynamic>{'id': 1, 'name': 'Luis'});

      expect(member.canPerform(99), isTrue);
    });

    test('con servicios asignados solo atiende los suyos', () {
      final StaffMember member = StaffMember.fromJson(<String, dynamic>{
        'id': 1,
        'name': 'Luis',
        'service_ids': <int>[3, 7],
      });

      expect(member.canPerform(3), isTrue);
      expect(member.canPerform(4), isFalse);
    });
  });

  group('Appointment', () {
    Appointment build(Map<String, dynamic> extra) => Appointment.fromJson(<String, dynamic>{
          'id': 10,
          'code': 'CT-ABC123',
          'status': 'confirmed',
          'payment_status': 'unpaid',
          'starts_at_local': '2026-09-15 14:30',
          'total': 25.0,
          'paid_amount': 0.0,
          ...extra,
        });

    test('separa fecha y hora para mostrarlas', () {
      final Appointment appointment = build(<String, dynamic>{});

      expect(appointment.dateLabel, '15/09/2026');
      expect(appointment.timeLabel, '14:30');
    });

    test('calcula lo que falta por pagar', () {
      expect(build(<String, dynamic>{'paid_amount': 10.0}).pending, 15.0);
      expect(build(<String, dynamic>{'paid_amount': 25.0}).pending, 0.0);
      // Un pago de mas nunca deja un pendiente negativo.
      expect(build(<String, dynamic>{'paid_amount': 40.0}).pending, 0.0);
    });

    test('reconoce las citas activas', () {
      expect(build(<String, dynamic>{'status': 'pending'}).isUpcoming, isTrue);
      expect(build(<String, dynamic>{'status': 'confirmed'}).isUpcoming, isTrue);
      expect(build(<String, dynamic>{'status': 'cancelled'}).isUpcoming, isFalse);
      expect(build(<String, dynamic>{'status': 'completed'}).isUpcoming, isFalse);
    });

    test('traduce el estado del pago', () {
      expect(build(<String, dynamic>{'payment_status': 'paid'}).paymentLabel, 'Pagada');
      expect(
        build(<String, dynamic>{'payment_status': 'awaiting_verification'}).paymentLabel,
        'Verificando pago',
      );
    });
  });

  group('AppBanner', () {
    test('sin enlace no muestra boton', () {
      final AppBanner banner = AppBanner.fromJson(<String, dynamic>{
        'id': 1,
        'title': 'Promo',
        'cta_label': 'Reservar',
      });

      expect(banner.hasAction, isFalse);
    });

    test('con etiqueta y enlace muestra boton', () {
      final AppBanner banner = AppBanner.fromJson(<String, dynamic>{
        'id': 1,
        'title': 'Promo',
        'cta_label': 'Reservar',
        'cta_url': '/agendar',
      });

      expect(banner.hasAction, isTrue);
    });
  });
}
