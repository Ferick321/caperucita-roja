import 'package:flutter/material.dart';

import '../../core/services/appointment_service.dart';
import '../../core/services/auth_service.dart';
import '../../core/services/config_service.dart';
import '../../models/appointment.dart';
import '../../widgets/common.dart';
import '../home/main_shell.dart';
import 'appointment_detail_screen.dart';

/// Listado de citas del cliente: proximas e historial.
class AppointmentsScreen extends StatefulWidget {
  const AppointmentsScreen({super.key});

  @override
  State<AppointmentsScreen> createState() => _AppointmentsScreenState();
}

class _AppointmentsScreenState extends State<AppointmentsScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 2, vsync: this);

  List<Appointment> _upcoming = <Appointment>[];
  List<Appointment> _past = <Appointment>[];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    if (!AuthService.instance.isLoggedIn) {
      setState(() => _loading = false);

      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final List<Appointment> upcoming = await AppointmentService.instance.list();
      final List<Appointment> past = await AppointmentService.instance.list(past: true);

      if (mounted) {
        setState(() {
          _upcoming = upcoming;
          _past = past;
          _loading = false;
        });
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = error.toString();
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!AuthService.instance.isLoggedIn) {
      return Scaffold(
        appBar: AppBar(title: const Text('Mis citas')),
        body: EmptyState(
          icon: Icons.lock_outline,
          message: 'Inicia sesion para ver tus citas y tus comprobantes.',
          actionLabel: 'Ingresar',
          onAction: () async {
            await Navigator.of(context).pushNamed('/login');
            await _load();
          },
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mis citas'),
        bottom: TabBar(
          controller: _tabs,
          tabs: const <Widget>[
            Tab(text: 'Proximas'),
            Tab(text: 'Historial'),
          ],
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? ErrorState(message: _error!, onRetry: _load)
              : TabBarView(
                  controller: _tabs,
                  children: <Widget>[
                    _list(_upcoming, empty: 'No tienes citas programadas.'),
                    _list(_past, empty: 'Aun no tienes visitas registradas.'),
                  ],
                ),
    );
  }

  Widget _list(List<Appointment> items, {required String empty}) {
    if (items.isEmpty) {
      return EmptyState(
        icon: Icons.event_note_outlined,
        message: empty,
        actionLabel: 'Agendar una cita',
        onAction: () => context.findAncestorStateOfType<MainShellState>()?.goTo(1),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: items.length,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (BuildContext context, int index) => _card(items[index]),
      ),
    );
  }

  Widget _card(Appointment appointment) {
    final Color statusColor = switch (appointment.status) {
      'confirmed' => const Color(0xFF10B981),
      'completed' => const Color(0xFF10B981),
      'cancelled' => const Color(0xFFEF4444),
      'no_show' => const Color(0xFFEF4444),
      'in_progress' => const Color(0xFF3B82F6),
      _ => const Color(0xFFF59E0B),
    };

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(ConfigService.instance.config.theme.radius),
        onTap: () async {
          await Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (BuildContext context) =>
                  AppointmentDetailScreen(appointmentId: appointment.id),
            ),
          );
          await _load();
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  StatusChip(label: appointment.statusLabel, color: statusColor),
                  const Spacer(),
                  Text(
                    appointment.code,
                    style: TextStyle(
                      fontSize: 12,
                      color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.5),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 12),

              Text(
                '${appointment.dateLabel} · ${appointment.timeLabel}',
                style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
              ),

              if (appointment.services.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    appointment.services.map((AppointmentService s) => s.name).join(', '),
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.72),
                    ),
                  ),
                ),

              if (appointment.staffName.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Text(
                    'Con ${appointment.staffName}',
                    style: TextStyle(
                      fontSize: 13,
                      color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.6),
                    ),
                  ),
                ),

              const SizedBox(height: 12),

              Row(
                children: <Widget>[
                  Text(
                    ConfigService.instance.money(appointment.total),
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                  ),
                  const Spacer(),
                  StatusChip(
                    label: appointment.paymentLabel,
                    color: appointment.isPaid
                        ? const Color(0xFF10B981)
                        : appointment.isAwaitingVerification
                            ? const Color(0xFFF59E0B)
                            : const Color(0xFF6B7280),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
