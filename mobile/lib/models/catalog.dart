/// Modelos del catalogo: categorias, servicios y profesionales.

class ServiceCategory {
  const ServiceCategory({
    required this.id,
    required this.name,
    required this.description,
    required this.color,
    required this.imageUrl,
  });

  final int id;
  final String name;
  final String description;
  final String color;
  final String? imageUrl;

  factory ServiceCategory.fromJson(Map<String, dynamic> json) => ServiceCategory(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String? ?? '',
        description: json['description'] as String? ?? '',
        color: json['color'] as String? ?? '#8b5cf6',
        imageUrl: json['image_url'] as String?,
      );
}

class Service {
  const Service({
    required this.id,
    required this.categoryId,
    required this.name,
    required this.shortDescription,
    required this.description,
    required this.imageUrl,
    required this.durationMinutes,
    required this.price,
    required this.basePrice,
    required this.hasPromo,
    required this.depositRequired,
    required this.loyaltyPoints,
    required this.isFeatured,
  });

  final int id;
  final int categoryId;
  final String name;
  final String shortDescription;
  final String description;
  final String? imageUrl;
  final int durationMinutes;
  final double price;
  final double basePrice;
  final bool hasPromo;
  final bool depositRequired;
  final int loyaltyPoints;
  final bool isFeatured;

  factory Service.fromJson(Map<String, dynamic> json) => Service(
        id: (json['id'] as num).toInt(),
        categoryId: (json['category_id'] as num?)?.toInt() ?? 0,
        name: json['name'] as String? ?? '',
        shortDescription: json['short_description'] as String? ?? '',
        description: json['description'] as String? ?? '',
        imageUrl: json['image_url'] as String?,
        durationMinutes: (json['duration_minutes'] as num?)?.toInt() ?? 30,
        price: ((json['price'] as num?) ?? 0).toDouble(),
        basePrice: ((json['base_price'] as num?) ?? 0).toDouble(),
        hasPromo: json['has_promo'] as bool? ?? false,
        depositRequired: json['deposit_required'] as bool? ?? false,
        loyaltyPoints: (json['loyalty_points'] as num?)?.toInt() ?? 0,
        isFeatured: json['is_featured'] as bool? ?? false,
      );

  String get durationLabel => durationMinutes < 60
      ? '$durationMinutes min'
      : durationMinutes % 60 == 0
          ? '${durationMinutes ~/ 60} h'
          : '${durationMinutes ~/ 60} h ${durationMinutes % 60} min';
}

class StaffMember {
  const StaffMember({
    required this.id,
    required this.name,
    required this.title,
    required this.bio,
    required this.photoUrl,
    required this.rating,
    required this.ratingCount,
    required this.serviceIds,
  });

  final int id;
  final String name;
  final String title;
  final String bio;
  final String? photoUrl;
  final double rating;
  final int ratingCount;
  final List<int> serviceIds;

  factory StaffMember.fromJson(Map<String, dynamic> json) => StaffMember(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String? ?? '',
        title: json['title'] as String? ?? '',
        bio: json['bio'] as String? ?? '',
        photoUrl: json['photo_url'] as String?,
        rating: ((json['rating'] as num?) ?? 0).toDouble(),
        ratingCount: (json['rating_count'] as num?)?.toInt() ?? 0,
        serviceIds: ((json['service_ids'] as List<dynamic>?) ?? <dynamic>[])
            .map((dynamic e) => (e as num).toInt())
            .toList(),
      );

  /// Iniciales para el avatar cuando no hay foto.
  String get initials {
    final List<String> parts = name.trim().split(RegExp(r'\s+'));

    return parts
        .take(2)
        .map((String p) => p.isEmpty ? '' : p[0].toUpperCase())
        .join();
  }

  bool canPerform(int serviceId) =>
      serviceIds.isEmpty || serviceIds.contains(serviceId);
}

/// Un dia con huecos libres.
class AvailableDay {
  const AvailableDay({required this.date, required this.label, required this.slots});

  final String date;
  final String label;
  final int slots;

  factory AvailableDay.fromJson(Map<String, dynamic> json) => AvailableDay(
        date: json['date'] as String? ?? '',
        label: json['label'] as String? ?? '',
        slots: (json['slots'] as num?)?.toInt() ?? 0,
      );

  int get dayNumber {
    final List<String> parts = date.split('-');

    return parts.length == 3 ? int.tryParse(parts[2]) ?? 0 : 0;
  }
}

/// Un horario concreto disponible.
class TimeSlot {
  const TimeSlot({required this.time, required this.label, required this.staffIds});

  final String time;
  final String label;
  final List<int> staffIds;

  factory TimeSlot.fromJson(Map<String, dynamic> json) => TimeSlot(
        time: json['time'] as String? ?? '',
        label: json['label'] as String? ?? '',
        staffIds: ((json['staff_ids'] as List<dynamic>?) ?? <dynamic>[])
            .map((dynamic e) => (e as num).toInt())
            .toList(),
      );
}
