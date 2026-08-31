import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_exception.dart';
import '../../core/services/appointment_service.dart';
import '../../core/services/config_service.dart';
import '../../models/appointment.dart';
import '../../models/remote_config.dart';
import '../../widgets/common.dart';
import '../payments/payment_screen.dart';

/// Detalle de una cita con sus pagos y las acciones disponibles.
class AppointmentDetailScreen extends StatefulWidget {
  const AppointmentDetailScreen({super.key, required this.appointmentId});

  final int appointmentId;

  @override
  State<AppointmentDetailScreen> createState() => _AppointmentDetailScreenState();
}

class _AppointmentDetailScreenState extends State<AppointmentDetailScreen> {
  Appointment? _appointment;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final Appointment appointment =
          await AppointmentService.instance.detail(widget.appointmentId);

      if (mounted) {
        setState(() {
          _appointment = appointment;
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
    final RemoteConfig config = ConfigService.instance.config;

    return Scaffold(
      appBar: AppBar(title: Text(_appointment?.code ?? 'Cita')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? ErrorState(message: _error!, onRetry: _load)
              : _content(_appointment!, config),
    );
  }

  Widget _content(Appointment appointment, RemoteConfig config) => ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      StatusChip(
                        label: appointment.statusLabel,
                        color: switch (appointment.status) {
                          'confirmed' || 'completed' => const Color(0xFF10B981),
                          'cancelled' || 'no_show' => const Color(0xFFEF4444),
                          'in_progress' => const Color(0xFF3B82F6),
                          _ => const Color(0xFFF59E0B),
                        },
                      ),
                      const Spacer(),
                      StatusChip(
                        label: appointment.paymentLabel,
                        color: appointment.isPaid
                            ? const Color(0xFF10B981)
                            : const Color(0xFF6B7280),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Text(
                    '${appointment.dateLabel} a las ${appointment.timeLabel}',
                    style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 14),
                  if (appointment.staffName.isNotEmpty)
                    DetailRow(label: 'Profesional', value: appointment.staffName),
                  DetailRow(
                    label: 'Duracion',
                    value: '${appointment.durationMinutes} min',
                  ),
                  if (appointment.branchName.isNotEmpty)
                    DetailRow(label: 'Local', value: appointment.branchName),
                  if (appointment.branchAddress.isNotEmpty)
                    DetailRow(label: 'Direccion', value: appointment.branchAddress),
                ],
              ),
            ),
          ),

          const SizedBox(height: 16),

          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                children: <Widget>[
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text('Servicios', style: TextStyle(fontWeight: FontWeight.w700)),
                  ),
                  const SizedBox(height: 8),
                  for (final AppointmentService service in appointment.services)
                    DetailRow(
                      label: service.name,
                      value: config.business.money(service.price),
                    ),
                  if (appointment.customRequest.isNotEmpty)
                    DetailRow(label: 'Peticion especial', value: appointment.customRequest),
                  const Divider(height: 24),
                  DetailRow(label: 'Total', value: config.business.money(appointment.total)),
                  if (appointment.paidAmount > 0)
                    DetailRow(
                      label: 'Pagado',
                      value: config.business.money(appointment.paidAmount),
                    ),
                ],
              ),
            ),
          ),

          if (appointment.payments.isNotEmpty) ...<Widget>[
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    const Text('Pagos', style: TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),
                    for (final Payment payment in appointment.payments)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Row(
                          children: <Widget>[
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: <Widget>[
                                  Text(
                                    config.business.money(payment.amount),
                                    style: const TextStyle(fontWeight: FontWeight.w600),
                                  ),
                                  Text(
                                    '${payment.method} · ${payment.createdAt}',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: Theme.of(context)
                                          .colorScheme
                                          .onSurface
                                          .withValues(alpha: 0.6),
                                    ),
                                  ),
                                  if (payment.rejectionReason.isNotEmpty)
                                    Text(
                                      payment.rejectionReason,
                                      style: const TextStyle(
                                        fontSize: 12,
                                        color: Color(0xFFEF4444),
                                      ),
                                    ),
                                ],
                              ),
                            ),
                            StatusChip(
                              label: payment.statusLabel,
                              color: switch (payment.status) {
                                'approved' => const Color(0xFF10B981),
                                'rejected' => const Color(0xFFEF4444),
                                _ => const Color(0xFFF59E0B),
                              },
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],

          const SizedBox(height: 22),

          if (appointment.pending > 0 && appointment.isUpcoming)
            ElevatedButton.icon(
              onPressed: () async {
                await Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (BuildContext context) =>
                        PaymentScreen(appointmentId: appointment.id),
                  ),
                );
                await _load();
              },
              icon: const Icon(Icons.payments_outlined),
              label: const Text('Pagar o subir comprobante'),
            ),

          if (appointment.status == 'completed') ...<Widget>[
            const SizedBox(height: 10),
            OutlinedButton.icon(
              onPressed: () => _showReviewSheet(appointment),
              icon: const Icon(Icons.star_outline),
              label: const Text('Calificar mi visita'),
            ),
          ],

          if (appointment.canCancel) ...<Widget>[
            const SizedBox(height: 10),
            OutlinedButton.icon(
              onPressed: () => _confirmCancel(appointment, config),
              icon: const Icon(Icons.cancel_outlined),
              label: const Text('Cancelar cita'),
              style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFFEF4444)),
            ),
          ],

          if (appointment.branchAddress.isNotEmpty) ...<Widget>[
            const SizedBox(height: 10),
            TextButton.icon(
              onPressed: () async {
                final Uri uri = Uri.parse(
                  'https://www.google.com/maps/search/?api=1&query='
                  '${Uri.encodeComponent(appointment.branchAddress)}',
                );

                await launchUrl(uri, mode: LaunchMode.externalApplication);
              },
              icon: const Icon(Icons.map_outlined),
              label: const Text('Como llegar'),
            ),
          ],

          const SizedBox(height: 24),
        ],
      );

  Future<void> _confirmCancel(Appointment appointment, RemoteConfig config) async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Cancelar la cita'),
        content: Text(
          'Seguro que quieres cancelar tu cita del ${appointment.dateLabel} '
          'a las ${appointment.timeLabel}?',
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('No, mantenerla'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFEF4444)),
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Si, cancelar'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      await AppointmentService.instance.cancel(appointment.id);

      if (mounted) {
        showMessage(context, 'Tu cita fue cancelada.');
        await _load();
      }
    } on ApiException catch (error) {
      if (mounted) {
        showMessage(context, error.message, isError: true);
      }
    }
  }

  Future<void> _showReviewSheet(Appointment appointment) async {
    int rating = 5;
    final TextEditingController comment = TextEditingController();

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (BuildContext sheetContext) => Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 20,
          bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 20,
        ),
        child: StatefulBuilder(
          builder: (BuildContext context, StateSetter setSheetState) => Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              const Text(
                'Como estuvo tu visita?',
                style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List<Widget>.generate(
                  5,
                  (int index) => IconButton(
                    iconSize: 36,
                    icon: Icon(
                      index < rating ? Icons.star_rounded : Icons.star_border_rounded,
                      color: const Color(0xFFF59E0B),
                    ),
                    onPressed: () => setSheetState(() => rating = index + 1),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: comment,
                maxLines: 4,
                maxLength: 500,
                decoration: const InputDecoration(
                  labelText: 'Cuentanos mas (opcional)',
                ),
              ),
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: () async {
                  Navigator.of(sheetContext).pop();

                  try {
                    await AppointmentService.instance.review(
                      appointment.id,
                      rating: rating,
                      comment: comment.text.trim(),
                    );

                    if (mounted) {
                      showMessage(context, 'Gracias por tu opinion!');
                    }
                  } on ApiException catch (error) {
                    if (mounted) {
                      showMessage(context, error.message, isError: true);
                    }
                  }
                },
                child: const Text('Enviar opinion'),
              ),
            ],
          ),
        ),
      ),
    );

    comment.dispose();
  }
}
