import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_exception.dart';
import '../../core/services/appointment_service.dart';
import '../../core/services/config_service.dart';
import '../../core/services/payment_service.dart';
import '../../models/appointment.dart';
import '../../models/remote_config.dart';
import '../../widgets/common.dart';
import '../home/main_shell.dart';

/// Pago de una cita.
///
/// Si el cliente elige transferencia se le muestran los datos bancarios que
/// el negocio configuro en el panel y puede subir el comprobante desde la
/// galeria o tomandole una foto con la camara.
class PaymentScreen extends StatefulWidget {
  const PaymentScreen({
    super.key,
    required this.appointmentId,
    this.justCreated = false,
  });

  final int appointmentId;
  final bool justCreated;

  @override
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends State<PaymentScreen> {
  final TextEditingController _referenceController = TextEditingController();

  Appointment? _appointment;
  List<PaymentMethod> _methods = <PaymentMethod>[];
  List<BankAccount> _accounts = <BankAccount>[];
  String _transferInstructions = '';

  PaymentMethod? _selectedMethod;
  int _selectedAccountId = 0;
  File? _proofFile;

  bool _loading = true;
  bool _submitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _referenceController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final Appointment appointment =
          await AppointmentService.instance.detail(widget.appointmentId);
      final List<PaymentMethod> methods = await PaymentService.instance.methods();

      List<BankAccount> accounts = <BankAccount>[];
      String instructions = '';

      // Los datos bancarios solo se piden si hay un metodo que los use.
      if (methods.any((PaymentMethod m) => m.showsBankAccounts)) {
        final ({String instructions, List<BankAccount> accounts}) bank =
            await PaymentService.instance.bankAccounts();
        accounts = bank.accounts;
        instructions = bank.instructions;
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _appointment = appointment;
        _methods = methods;
        _accounts = accounts;
        _transferInstructions = instructions;
        _selectedMethod = methods.isEmpty ? null : methods.first;
        _selectedAccountId = accounts.isEmpty ? 0 : accounts.first.id;
        _loading = false;
      });
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

    return PopScope(
      canPop: !widget.justCreated,
      onPopInvokedWithResult: (bool didPop, Object? _) {
        // Tras crear la cita, salir lleva al inicio y no al flujo de reserva.
        if (!didPop && mounted) {
          Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute<void>(
              builder: (BuildContext context) => const MainShell(initialIndex: 2),
            ),
            (Route<dynamic> route) => false,
          );
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(widget.justCreated ? 'Cita registrada' : 'Pago de la cita'),
        ),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? ErrorState(message: _error!, onRetry: _load)
                : _content(config),
      ),
    );
  }

  Widget _content(RemoteConfig config) {
    final Appointment appointment = _appointment!;
    final PaymentMethod? method = _selectedMethod;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        if (widget.justCreated)
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: <Widget>[
                  const Icon(Icons.check_circle_outline, size: 48, color: Color(0xFF10B981)),
                  const SizedBox(height: 12),
                  const Text(
                    'Tu cita quedo registrada',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Codigo ${appointment.code}',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.7),
                    ),
                  ),
                ],
              ),
            ),
          ),

        const SizedBox(height: 16),

        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: <Widget>[
                DetailRow(
                  label: 'Fecha',
                  value: '${appointment.dateLabel} ${appointment.timeLabel}',
                ),
                if (appointment.staffName.isNotEmpty)
                  DetailRow(label: 'Profesional', value: appointment.staffName),
                for (final AppointmentService service in appointment.services)
                  DetailRow(
                    label: service.name,
                    value: config.business.money(service.price),
                  ),
                const Divider(height: 22),
                DetailRow(label: 'Total', value: config.business.money(appointment.total)),
                if (appointment.paidAmount > 0)
                  DetailRow(
                    label: 'Ya pagado',
                    value: config.business.money(appointment.paidAmount),
                  ),
                DetailRow(
                  label: 'Pendiente',
                  value: config.business.money(appointment.pending),
                ),
              ],
            ),
          ),
        ),

        if (appointment.payments.isNotEmpty) ...<Widget>[
          const SizedBox(height: 22),
          const Text('Pagos registrados', style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          for (final Payment payment in appointment.payments) _paymentTile(payment, config),
        ],

        if (appointment.pending <= 0 || appointment.status == 'cancelled')
          Padding(
            padding: const EdgeInsets.only(top: 24),
            child: EmptyState(
              icon: Icons.verified_outlined,
              message: appointment.status == 'cancelled'
                  ? 'Esta cita esta cancelada.'
                  : 'Esta cita ya esta pagada. Nos vemos pronto!',
              actionLabel: 'Ver mis citas',
              onAction: () => Navigator.of(context).pushAndRemoveUntil(
                MaterialPageRoute<void>(
                  builder: (BuildContext context) => const MainShell(initialIndex: 2),
                ),
                (Route<dynamic> route) => false,
              ),
            ),
          )
        else ...<Widget>[
          const SizedBox(height: 26),
          const Text(
            'Como quieres pagar?',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 10),

          for (final PaymentMethod option in _methods)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Card(
                child: RadioListTile<int>(
                  value: option.id,
                  groupValue: method?.id ?? 0,
                  onChanged: (int? _) => setState(() {
                    _selectedMethod = option;
                    _proofFile = null;
                  }),
                  title: Text(option.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      if (option.description.isNotEmpty) Text(option.description),
                      if (option.instructions.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(
                            option.instructions,
                            style: TextStyle(
                              fontSize: 12,
                              color: Theme.of(context)
                                  .colorScheme
                                  .onSurface
                                  .withValues(alpha: 0.6),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            ),

          // Datos bancarios: solo cuando el metodo elegido los requiere.
          if (method != null && method.showsBankAccounts) _bankSection(config),

          if (method != null && method.requiresProof) _proofSection(),

          const SizedBox(height: 24),

          ElevatedButton(
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const SizedBox(
                    height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2))
                : Text(
                    method != null && method.requiresProof
                        ? 'Enviar comprobante'
                        : 'Confirmar forma de pago',
                  ),
          ),

          const SizedBox(height: 10),

          OutlinedButton(
            onPressed: () => Navigator.of(context).pushAndRemoveUntil(
              MaterialPageRoute<void>(
                builder: (BuildContext context) => const MainShell(initialIndex: 2),
              ),
              (Route<dynamic> route) => false,
            ),
            child: const Text('Pagar mas tarde'),
          ),
        ],

        const SizedBox(height: 24),
      ],
    );
  }

  Widget _paymentTile(Payment payment, RemoteConfig config) {
    final Color color = switch (payment.status) {
      'approved' => const Color(0xFF10B981),
      'rejected' => const Color(0xFFEF4444),
      _ => const Color(0xFFF59E0B),
    };

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    config.business.money(payment.amount),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
                StatusChip(label: payment.statusLabel, color: color),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              '${payment.method} · ${payment.createdAt}',
              style: TextStyle(
                fontSize: 12,
                color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.6),
              ),
            ),
            if (payment.rejectionReason.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  payment.rejectionReason,
                  style: const TextStyle(color: Color(0xFFEF4444), fontSize: 13),
                ),
              ),
            if (payment.proofUrls.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Wrap(
                  spacing: 8,
                  children: payment.proofUrls
                      .map((String url) => RemoteImage(
                            url: url,
                            height: 64,
                            width: 64,
                            borderRadius: BorderRadius.circular(8),
                          ))
                      .toList(),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _bankSection(RemoteConfig config) {
    if (_accounts.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 16),
        child: Text(
          'Todavia no hay cuentas publicadas. Comunicate con el local para coordinar el pago.',
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        const SizedBox(height: 18),
        const Text('Datos para la transferencia', style: TextStyle(fontWeight: FontWeight.w700)),

        if (_transferInstructions.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              _transferInstructions,
              style: TextStyle(
                fontSize: 13,
                color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.7),
              ),
            ),
          ),

        const SizedBox(height: 12),

        for (final BankAccount account in _accounts)
          Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                children: <Widget>[
                  if (_accounts.length > 1)
                    RadioListTile<int>(
                      value: account.id,
                      groupValue: _selectedAccountId,
                      onChanged: (int? value) =>
                          setState(() => _selectedAccountId = value ?? 0),
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        account.bankName,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    )
                  else
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        account.bankName,
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                      ),
                    ),
                  DetailRow(label: 'Tipo de cuenta', value: account.accountType),
                  DetailRow(
                    label: 'Numero de cuenta',
                    value: account.accountNumber,
                    trailing: IconButton(
                      icon: const Icon(Icons.copy, size: 18),
                      tooltip: 'Copiar',
                      onPressed: () => _copy(account.accountNumber, 'Numero de cuenta copiado'),
                    ),
                  ),
                  DetailRow(label: 'Titular', value: account.holderName),
                  if (account.holderDocument.isNotEmpty)
                    DetailRow(
                      label: 'Identificacion',
                      value: account.holderDocument,
                      trailing: IconButton(
                        icon: const Icon(Icons.copy, size: 18),
                        tooltip: 'Copiar',
                        onPressed: () =>
                            _copy(account.holderDocument, 'Identificacion copiada'),
                      ),
                    ),
                  if (account.holderEmail.isNotEmpty)
                    DetailRow(label: 'Correo', value: account.holderEmail),
                  if (account.instructions.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(
                        account.instructions,
                        style: TextStyle(
                          fontSize: 12,
                          color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.6),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),

        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(config.theme.radius),
          ),
          child: Row(
            children: <Widget>[
              const Icon(Icons.info_outline, size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Importe a transferir: '
                  '${config.business.money(_appointment!.pending)}',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 16),

        TextField(
          controller: _referenceController,
          decoration: const InputDecoration(
            labelText: 'Numero de comprobante / referencia',
            hintText: 'Ej. 000123456',
          ),
        ),
      ],
    );
  }

  Widget _proofSection() => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const SizedBox(height: 22),
          const Text('Sube tu comprobante', style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text(
            'Toma una foto del comprobante o eligela de tu galeria.',
            style: TextStyle(
              fontSize: 13,
              color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.65),
            ),
          ),
          const SizedBox(height: 12),

          if (_proofFile != null)
            Stack(
              alignment: Alignment.topRight,
              children: <Widget>[
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.file(
                    _proofFile!,
                    height: 220,
                    width: double.infinity,
                    fit: BoxFit.cover,
                  ),
                ),
                IconButton(
                  icon: const CircleAvatar(
                    backgroundColor: Colors.black54,
                    child: Icon(Icons.close, color: Colors.white, size: 18),
                  ),
                  tooltip: 'Quitar la imagen',
                  onPressed: () => setState(() => _proofFile = null),
                ),
              ],
            )
          else
            Row(
              children: <Widget>[
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _pickImage(ImageSource.camera),
                    icon: const Icon(Icons.photo_camera_outlined),
                    label: const Text('Tomar foto'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _pickImage(ImageSource.gallery),
                    icon: const Icon(Icons.photo_library_outlined),
                    label: const Text('Galeria'),
                  ),
                ),
              ],
            ),
        ],
      );

  Future<void> _pickImage(ImageSource source) async {
    try {
      final XFile? picked = await ImagePicker().pickImage(
        source: source,
        // Se reduce antes de subir para no gastar los datos del cliente.
        maxWidth: 1600,
        imageQuality: 82,
      );

      if (picked != null && mounted) {
        setState(() => _proofFile = File(picked.path));
      }
    } catch (_) {
      if (mounted) {
        showMessage(
          context,
          'No pudimos acceder a la camara o la galeria. Revisa los permisos.',
          isError: true,
        );
      }
    }
  }

  Future<void> _copy(String value, String message) async {
    await Clipboard.setData(ClipboardData(text: value));

    if (mounted) {
      showMessage(context, message);
    }
  }

  Future<void> _submit() async {
    final PaymentMethod? method = _selectedMethod;
    final Appointment? appointment = _appointment;

    if (method == null || appointment == null) {
      return;
    }

    if (method.requiresProof && _proofFile == null) {
      showMessage(context, 'Adjunta el comprobante para poder verificar tu pago.', isError: true);

      return;
    }

    setState(() => _submitting = true);

    try {
      final int paymentId = await PaymentService.instance.register(
        appointmentId: appointment.id,
        paymentMethodId: method.id,
        bankAccountId: method.showsBankAccounts ? _selectedAccountId : null,
        amount: appointment.pending,
        reference: _referenceController.text.trim(),
      );

      if (_proofFile != null && paymentId > 0) {
        await PaymentService.instance.uploadProofPhoto(paymentId, _proofFile!);
      }

      if (!mounted) {
        return;
      }

      showMessage(
        context,
        method.requiresVerification
            ? 'Recibimos tu comprobante. Te avisaremos al verificarlo.'
            : 'Registramos tu forma de pago. Te esperamos!',
      );

      await Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (BuildContext context) => const MainShell(initialIndex: 2),
        ),
        (Route<dynamic> route) => false,
      );
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _submitting = false);
        showMessage(context, error.message, isError: true);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _submitting = false);
        showMessage(context, 'No pudimos registrar el pago. Intentalo de nuevo.', isError: true);
      }
    }
  }
}
