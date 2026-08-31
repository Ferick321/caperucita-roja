import '../api/api_client.dart';
import '../../models/catalog.dart';

/// Consultas al catalogo y a la disponibilidad.
class CatalogService {
  CatalogService._();

  static final CatalogService instance = CatalogService._();

  Future<List<ServiceCategory>> categories() async {
    final Map<String, dynamic> response =
        await ApiClient.instance.get('/categorias');

    return ((response['data'] as List<dynamic>?) ?? <dynamic>[])
        .map((dynamic c) => ServiceCategory.fromJson(c as Map<String, dynamic>))
        .toList();
  }

  Future<List<Service>> services({int? categoryId, String? search, bool featured = false}) async {
    final Map<String, dynamic> response = await ApiClient.instance.get(
      '/servicios',
      query: <String, dynamic>{
        if (categoryId != null && categoryId > 0) 'category_id': categoryId,
        if (search != null && search.isNotEmpty) 'q': search,
        if (featured) 'featured': 1,
      },
    );

    return ((response['data'] as List<dynamic>?) ?? <dynamic>[])
        .map((dynamic s) => Service.fromJson(s as Map<String, dynamic>))
        .toList();
  }

  Future<List<StaffMember>> staff({int? branchId}) async {
    final Map<String, dynamic> response = await ApiClient.instance.get(
      '/profesionales',
      query: <String, dynamic>{if (branchId != null && branchId > 0) 'branch_id': branchId},
    );

    return ((response['data'] as List<dynamic>?) ?? <dynamic>[])
        .map((dynamic s) => StaffMember.fromJson(s as Map<String, dynamic>))
        .toList();
  }

  Future<List<String>> galleryImages() async {
    final Map<String, dynamic> response = await ApiClient.instance.get('/galeria');

    return ((response['data'] as List<dynamic>?) ?? <dynamic>[])
        .map((dynamic g) => (g as Map<String, dynamic>)['image_url'] as String? ?? '')
        .where((String url) => url.isNotEmpty)
        .toList();
  }

  /// Dias con al menos un hueco libre.
  Future<List<AvailableDay>> availableDays({
    required List<int> serviceIds,
    required int branchId,
    int? staffId,
  }) async {
    final Map<String, dynamic> response = await ApiClient.instance.get(
      '/disponibilidad',
      query: <String, dynamic>{
        'service_ids': serviceIds,
        'branch_id': branchId,
        if (staffId != null && staffId > 0) 'staff_id': staffId,
      },
    );

    final Map<String, dynamic> data =
        (response['data'] as Map<String, dynamic>?) ?? <String, dynamic>{};

    return ((data['days'] as List<dynamic>?) ?? <dynamic>[])
        .map((dynamic d) => AvailableDay.fromJson(d as Map<String, dynamic>))
        .toList();
  }

  /// Horarios libres de un dia concreto.
  Future<List<TimeSlot>> slots({
    required List<int> serviceIds,
    required int branchId,
    required String date,
    int? staffId,
  }) async {
    final Map<String, dynamic> response = await ApiClient.instance.get(
      '/disponibilidad',
      query: <String, dynamic>{
        'service_ids': serviceIds,
        'branch_id': branchId,
        'date': date,
        if (staffId != null && staffId > 0) 'staff_id': staffId,
      },
    );

    final Map<String, dynamic> data =
        (response['data'] as Map<String, dynamic>?) ?? <String, dynamic>{};

    return ((data['slots'] as List<dynamic>?) ?? <dynamic>[])
        .map((dynamic s) => TimeSlot.fromJson(s as Map<String, dynamic>))
        .toList();
  }
}
