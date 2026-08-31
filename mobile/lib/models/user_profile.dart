/// Perfil del cliente autenticado.
class UserProfile {
  const UserProfile({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.email,
    required this.phone,
    required this.avatarUrl,
    required this.loyaltyPoints,
    required this.loyaltyValue,
    required this.totalVisits,
    required this.referralCode,
    required this.acceptsMarketing,
    required this.acceptsPush,
  });

  final int id;
  final String firstName;
  final String lastName;
  final String email;
  final String phone;
  final String? avatarUrl;
  final int loyaltyPoints;
  final double loyaltyValue;
  final int totalVisits;
  final String referralCode;
  final bool acceptsMarketing;
  final bool acceptsPush;

  factory UserProfile.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> preferences =
        (json['preferences'] as Map<String, dynamic>?) ?? <String, dynamic>{};

    return UserProfile(
      id: (json['id'] as num?)?.toInt() ?? 0,
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      phone: json['phone'] as String? ?? '',
      avatarUrl: json['avatar_url'] as String?,
      loyaltyPoints: (json['loyalty_points'] as num?)?.toInt() ?? 0,
      loyaltyValue: ((json['loyalty_value'] as num?) ?? 0).toDouble(),
      totalVisits: (json['total_visits'] as num?)?.toInt() ?? 0,
      referralCode: json['referral_code'] as String? ?? '',
      acceptsMarketing: (preferences['accepts_marketing'] as bool?) ??
          (json['accepts_marketing'] as bool? ?? false),
      acceptsPush: preferences['accepts_push'] as bool? ?? true,
    );
  }

  String get fullName => '$firstName $lastName'.trim();

  String get initials {
    final String a = firstName.isEmpty ? '' : firstName[0].toUpperCase();
    final String b = lastName.isEmpty ? '' : lastName[0].toUpperCase();

    return (a + b).isEmpty ? '?' : a + b;
  }
}

/// Anuncio que envia el servidor para mostrar dentro de la app.
class AppBanner {
  const AppBanner({
    required this.id,
    required this.title,
    required this.subtitle,
    required this.body,
    required this.imageUrl,
    required this.ctaLabel,
    required this.ctaUrl,
    required this.backgroundColor,
    required this.textColor,
    required this.delaySeconds,
    required this.autoCloseSeconds,
    required this.isDismissible,
    required this.placement,
  });

  final int id;
  final String title;
  final String subtitle;
  final String body;
  final String imageUrl;
  final String ctaLabel;
  final String ctaUrl;
  final String backgroundColor;
  final String textColor;
  final int delaySeconds;
  final int autoCloseSeconds;
  final bool isDismissible;
  final String placement;

  factory AppBanner.fromJson(Map<String, dynamic> json) => AppBanner(
        id: (json['id'] as num).toInt(),
        title: json['title'] as String? ?? '',
        subtitle: json['subtitle'] as String? ?? '',
        body: json['body'] as String? ?? '',
        imageUrl: json['image_url'] as String? ?? '',
        ctaLabel: json['cta_label'] as String? ?? '',
        ctaUrl: json['cta_url'] as String? ?? '',
        backgroundColor: json['background_color'] as String? ?? '#141b2d',
        textColor: json['text_color'] as String? ?? '#ffffff',
        delaySeconds: (json['delay_seconds'] as num?)?.toInt() ?? 0,
        autoCloseSeconds: (json['auto_close_seconds'] as num?)?.toInt() ?? 0,
        isDismissible: json['is_dismissible'] as bool? ?? true,
        placement: json['placement'] as String? ?? '',
      );

  bool get hasAction => ctaLabel.isNotEmpty && ctaUrl.isNotEmpty;
}
