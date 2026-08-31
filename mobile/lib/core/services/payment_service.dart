import 'dart:convert';
import 'dart:io';

import '../api/api_client.dart';
import '../../models/appointment.dart';

/// Pagos y comprobantes desde la app.
class PaymentService {
  PaymentService._();

  static final PaymentService instance = PaymentService._();

  Future<List<PaymentMethod>> methods() async {
    final Map<String, dynamic> response =
        await ApiClient.instance.get('/pagos/metodos', authenticated: true);

    return ((response['data'] as List<dynamic>?) ?? <dynamic>[])
        .map((dynamic m) => PaymentMethod.fromJson(m as Map<String, dynamic>))
        .toList();
  }

  /// Datos bancarios y su texto de instrucciones.
  Future<({String instructions, List<BankAccount> accounts})> bankAccounts() async {
    final Map<String, dynamic> response =
        await ApiClient.instance.get('/pagos/cuentas', authenticated: true);

    final Map<String, dynamic> data =
        (response['data'] as Map<String, dynamic>?) ?? <String, dynamic>{};

    return (
      instructions: data['instructions'] as String? ?? '',
      accounts: ((data['accounts'] as List<dynamic>?) ?? <dynamic>[])
          .map((dynamic a) => BankAccount.fromJson(a as Map<String, dynamic>))
          .toList(),
    );
  }

  /// Registra la intencion de pago y devuelve su identificador.
  Future<int> register({
    required int appointmentId,
    required int paymentMethodId,
    int? bankAccountId,
    double? amount,
    String reference = '',
    String? transferredAt,
  }) async {
    final Map<String, dynamic> response = await ApiClient.instance.post(
      '/pagos',
      body: <String, dynamic>{
        'appointment_id': appointmentId,
        'payment_method_id': paymentMethodId,
        if (bankAccountId != null && bankAccountId > 0) 'bank_account_id': bankAccountId,
        if (amount != null) 'amount': amount,
        'reference': reference,
        if (transferredAt != null) 'transferred_at': transferredAt,
      },
      authenticated: true,
    );

    final Map<String, dynamic> data =
        (response['data'] as Map<String, dynamic>?) ?? <String, dynamic>{};

    return (data['id'] as num?)?.toInt() ?? 0;
  }

  /// Sube el comprobante como archivo adjunto (galeria o explorador).
  Future<String> uploadProofFile(int paymentId, String filePath) async {
    final Map<String, dynamic> response = await ApiClient.instance.uploadFile(
      '/pagos/$paymentId/comprobante',
      field: 'proof',
      filePath: filePath,
    );

    return _proofUrl(response);
  }

  /// Sube una foto tomada con la camara, codificada en base64.
  Future<String> uploadProofPhoto(int paymentId, File photo) async {
    final List<int> bytes = await photo.readAsBytes();

    final Map<String, dynamic> response = await ApiClient.instance.post(
      '/pagos/$paymentId/comprobante',
      body: <String, dynamic>{
        'proof_base64': base64Encode(bytes),
        'proof_name': photo.path.split('/').last,
      },
      authenticated: true,
    );

    return _proofUrl(response);
  }

  String _proofUrl(Map<String, dynamic> response) {
    final Map<String, dynamic> data =
        (response['data'] as Map<String, dynamic>?) ?? <String, dynamic>{};
    final Map<String, dynamic> proof =
        (data['proof'] as Map<String, dynamic>?) ?? <String, dynamic>{};

    return proof['url'] as String? ?? '';
  }
}
