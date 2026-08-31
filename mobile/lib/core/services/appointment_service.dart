import '../api/api_client.dart';
import '../../models/appointment.dart';

/// Citas del cliente.
class AppointmentService {
  AppointmentService._();

  static final AppointmentService instance = AppointmentService._();

  Future<List<Appointment>> list({bool past = false, int page = 1}) async {
    final Map<String, dynamic> response = await ApiClient.instance.get(
      '/citas',
      query: <String, dynamic>{'scope': past ? 'past' : 'upcoming', 'page': page},
      authenticated: true,
    );

    return ((response['data'] as List<dynamic>?) ?? <dynamic>[])
        .map((dynamic a) => Appointment.fromJson(a as Map<String, dynamic>))
        .toList();
  }

  Future<Appointment> detail(int id) async {
    final Map<String, dynamic> response =
        await ApiClient.instance.get('/citas/$id', authenticated: true);

    return Appointment.fromJson(
      (response['data'] as Map<String, dynamic>?) ?? <String, dynamic>{},
    );
  }

  Future<Appointment> create({
    required int branchId,
    required List<int> serviceIds,
    required String date,
    required String time,
    int? staffId,
    String notes = '',
    String customRequest = '',
    String couponCode = '',
  }) async {
    final Map<String, dynamic> response = await ApiClient.instance.post(
      '/citas',
      body: <String, dynamic>{
        'branch_id': branchId,
        'service_ids': serviceIds,
        'date': date,
        'time': time,
        if (staffId != null && staffId > 0) 'staff_id': staffId,
        'notes': notes,
        'custom_request': customRequest,
        if (couponCode.isNotEmpty) 'coupon_code': couponCode,
      },
      authenticated: true,
    );

    return Appointment.fromJson(
      (response['data'] as Map<String, dynamic>?) ?? <String, dynamic>{},
    );
  }

  Future<void> cancel(int id, {String reason = ''}) async {
    await ApiClient.instance.post(
      '/citas/$id/cancelar',
      body: <String, dynamic>{'reason': reason},
      authenticated: true,
    );
  }

  Future<void> reschedule(int id, {required String date, required String time, int? staffId}) async {
    await ApiClient.instance.post(
      '/citas/$id/reprogramar',
      body: <String, dynamic>{
        'date': date,
        'time': time,
        if (staffId != null && staffId > 0) 'staff_id': staffId,
      },
      authenticated: true,
    );
  }

  Future<void> review(int id, {required int rating, String comment = ''}) async {
    await ApiClient.instance.post(
      '/citas/$id/resena',
      body: <String, dynamic>{'rating': rating, 'comment': comment},
      authenticated: true,
    );
  }
}
