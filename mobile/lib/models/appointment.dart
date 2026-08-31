/// Cita del cliente y los modelos de pago asociados.

class Appointment {
  const Appointment({
    required this.id,
    required this.code,
    required this.status,
    required this.statusLabel,
    required this.paymentStatus,
    required this.startsAtLocal,
    required this.durationMinutes,
    required this.staffName,
    required this.staffPhotoUrl,
    required this.branchName,
    required this.branchAddress,
    required this.services,
    required this.customRequest,
    required this.total,
    required this.paidAmount,
    required this.canCancel,
    required this.payments,
  });

  final int id;
  final String code;
  final String status;
  final String statusLabel;
  final String paymentStatus;
  final String startsAtLocal;
  final int durationMinutes;
  final String staffName;
  final String? staffPhotoUrl;
  final String branchName;
  final String branchAddress;
  final List<AppointmentService> services;
  final String customRequest;
  final double total;
  final double paidAmount;
  final bool canCancel;
  final List<Payment> payments;

  factory Appointment.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> staff =
        (json['staff'] as Map<String, dynamic>?) ?? <String, dynamic>{};
    final Map<String, dynamic> branch =
        (json['branch'] as Map<String, dynamic>?) ?? <String, dynamic>{};

    return Appointment(
      id: (json['id'] as num).toInt(),
      code: json['code'] as String? ?? '',
      status: json['status'] as String? ?? 'pending',
      statusLabel: json['status_label'] as String? ?? '',
      paymentStatus: json['payment_status'] as String? ?? 'unpaid',
      startsAtLocal: json['starts_at_local'] as String? ?? '',
      durationMinutes: (json['duration_minutes'] as num?)?.toInt() ?? 0,
      staffName: staff['name'] as String? ?? '',
      staffPhotoUrl: staff['photo_url'] as String?,
      branchName: branch['name'] as String? ?? '',
      branchAddress: branch['address'] as String? ?? '',
      services: ((json['services'] as List<dynamic>?) ?? <dynamic>[])
          .map((dynamic s) => AppointmentService.fromJson(s as Map<String, dynamic>))
          .toList(),
      customRequest: json['custom_request'] as String? ?? '',
      total: ((json['total'] as num?) ?? 0).toDouble(),
      paidAmount: ((json['paid_amount'] as num?) ?? 0).toDouble(),
      canCancel: json['can_cancel'] as bool? ?? false,
      payments: ((json['payments'] as List<dynamic>?) ?? <dynamic>[])
          .map((dynamic p) => Payment.fromJson(p as Map<String, dynamic>))
          .toList(),
    );
  }

  double get pending => (total - paidAmount).clamp(0, double.infinity);

  bool get isUpcoming =>
      status == 'pending' || status == 'confirmed' || status == 'in_progress';

  bool get isPaid => paymentStatus == 'paid';

  bool get isAwaitingVerification => paymentStatus == 'awaiting_verification';

  String get paymentLabel => switch (paymentStatus) {
        'paid' => 'Pagada',
        'deposit_paid' => 'Abono pagado',
        'awaiting_verification' => 'Verificando pago',
        'refunded' => 'Reembolsada',
        _ => 'Sin pagar',
      };

  /// Fecha en formato dd/mm/aaaa a partir de "aaaa-mm-dd HH:MM".
  String get dateLabel {
    final List<String> parts = startsAtLocal.split(' ');

    if (parts.isEmpty) {
      return startsAtLocal;
    }

    final List<String> date = parts[0].split('-');

    return date.length == 3 ? '${date[2]}/${date[1]}/${date[0]}' : parts[0];
  }

  String get timeLabel {
    final List<String> parts = startsAtLocal.split(' ');

    return parts.length > 1 ? parts[1] : '';
  }
}

class AppointmentService {
  const AppointmentService({
    required this.name,
    required this.price,
    required this.durationMinutes,
  });

  final String name;
  final double price;
  final int durationMinutes;

  factory AppointmentService.fromJson(Map<String, dynamic> json) => AppointmentService(
        name: json['name'] as String? ?? '',
        price: ((json['price'] as num?) ?? 0).toDouble(),
        durationMinutes: (json['duration_minutes'] as num?)?.toInt() ?? 0,
      );
}

class PaymentMethod {
  const PaymentMethod({
    required this.id,
    required this.code,
    required this.name,
    required this.description,
    required this.instructions,
    required this.requiresProof,
    required this.showsBankAccounts,
    required this.requiresVerification,
  });

  final int id;
  final String code;
  final String name;
  final String description;
  final String instructions;
  final bool requiresProof;
  final bool showsBankAccounts;
  final bool requiresVerification;

  factory PaymentMethod.fromJson(Map<String, dynamic> json) => PaymentMethod(
        id: (json['id'] as num).toInt(),
        code: json['code'] as String? ?? '',
        name: json['name'] as String? ?? '',
        description: json['description'] as String? ?? '',
        instructions: json['instructions'] as String? ?? '',
        requiresProof: json['requires_proof'] as bool? ?? false,
        showsBankAccounts: json['shows_bank_accounts'] as bool? ?? false,
        requiresVerification: json['requires_verification'] as bool? ?? false,
      );
}

class BankAccount {
  const BankAccount({
    required this.id,
    required this.bankName,
    required this.accountType,
    required this.accountNumber,
    required this.holderName,
    required this.holderDocument,
    required this.holderEmail,
    required this.instructions,
  });

  final int id;
  final String bankName;
  final String accountType;
  final String accountNumber;
  final String holderName;
  final String holderDocument;
  final String holderEmail;
  final String instructions;

  factory BankAccount.fromJson(Map<String, dynamic> json) => BankAccount(
        id: (json['id'] as num).toInt(),
        bankName: json['bank_name'] as String? ?? '',
        accountType: json['account_type'] as String? ?? '',
        accountNumber: json['account_number'] as String? ?? '',
        holderName: json['holder_name'] as String? ?? '',
        holderDocument: json['holder_document'] as String? ?? '',
        holderEmail: json['holder_email'] as String? ?? '',
        instructions: json['instructions'] as String? ?? '',
      );
}

class Payment {
  const Payment({
    required this.id,
    required this.amount,
    required this.method,
    required this.status,
    required this.reference,
    required this.rejectionReason,
    required this.createdAt,
    required this.proofUrls,
  });

  final int id;
  final double amount;
  final String method;
  final String status;
  final String reference;
  final String rejectionReason;
  final String createdAt;
  final List<String> proofUrls;

  factory Payment.fromJson(Map<String, dynamic> json) => Payment(
        id: (json['id'] as num).toInt(),
        amount: ((json['amount'] as num?) ?? 0).toDouble(),
        method: json['method'] as String? ?? '',
        status: json['status'] as String? ?? '',
        reference: json['reference'] as String? ?? '',
        rejectionReason: json['rejection_reason'] as String? ?? '',
        createdAt: json['created_at'] as String? ?? '',
        proofUrls: ((json['proofs'] as List<dynamic>?) ?? <dynamic>[])
            .map((dynamic p) => (p as Map<String, dynamic>)['url'] as String? ?? '')
            .where((String url) => url.isNotEmpty)
            .toList(),
      );

  String get statusLabel => switch (status) {
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'awaiting_verification' => 'En verificacion',
        'refunded' => 'Reembolsado',
        _ => 'Registrado',
      };
}
