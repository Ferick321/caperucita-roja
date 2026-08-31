import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/services/appointment_service.dart';
import '../../core/services/auth_service.dart';
import '../../core/services/catalog_service.dart';
import '../../core/services/config_service.dart';
import '../../models/appointment.dart';
import '../../models/catalog.dart';
import '../../models/remote_config.dart';
import '../../widgets/ad_banner.dart';
import '../../widgets/common.dart';
import '../appointments/appointment_detail_screen.dart';
import '../booking/booking_screen.dart';
import 'main_shell.dart';

/// Inicio: proxima cita, servicios destacados, publicidad y accesos rapidos.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<Service> _featured = <Service>[];
  List<ServiceCategory> _categories = <ServiceCategory>[];
  Appointment? _nextAppointment;
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
      final List<Service> featured =
          await CatalogService.instance.services(featured: true);
      final List<ServiceCategory> categories =
          await CatalogService.instance.categories();

      Appointment? next;

      if (AuthService.instance.isLoggedIn) {
        final List<Appointment> upcoming = await AppointmentService.instance.list();
        next = upcoming.isEmpty ? null : upcoming.first;
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _featured = featured;
        _categories = categories;
        _nextAppointment = next;
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
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async {
          await ConfigService.instance.refresh();
          await _load();
        },
        child: CustomScrollView(
          slivers: <Widget>[
            SliverAppBar(
              floating: true,
              title: Row(
                children: <Widget>[
                  if (config.business.logoUrl != null)
                    RemoteImage(
                      url: config.business.logoUrl,
                      height: 32,
                      width: 32,
                      fit: BoxFit.contain,
                    ),
                  if (config.business.logoUrl != null) const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      config.business.name,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
              actions: <Widget>[
                if (config.business.whatsapp.isNotEmpty)
                  IconButton(
                    icon: const Icon(Icons.chat_bubble_outline),
                    tooltip: 'Escribenos por WhatsApp',
                    onPressed: () => _openWhatsApp(config.business.whatsapp),
                  ),
              ],
            ),

            if (_loading)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null)
              SliverFillRemaining(
                hasScrollBody: false,
                child: ErrorState(message: _error!, onRetry: _load),
              )
            else
              SliverList(
                delegate: SliverChildListDelegate(<Widget>[
                  _greeting(theme, config),

                  if (_nextAppointment != null) _nextAppointmentCard(_nextAppointment!, config),

                  if (config.ads.enabled) const AdBannerCard(placement: 'app_home_card'),

                  _sectionTitle('Que necesitas hoy?'),
                  _categoriesRow(),

                  if (_featured.isNotEmpty) ...<Widget>[
                    _sectionTitle('Lo mas pedido'),
                    _featuredList(config),
                  ],

                  _quickActions(config),

                  const SizedBox(height: 28),
                ]),
              ),
          ],
        ),
      ),
    );
  }

  Widget _greeting(ThemeData theme, RemoteConfig config) {
    final String name = AuthService.instance.user?.firstName ?? '';

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            name.isEmpty ? 'Hola' : 'Hola, $name',
            style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 4),
          Text(
            config.business.tagline.isNotEmpty
                ? config.business.tagline
                : 'Reserva tu cita en segundos',
            style: TextStyle(color: theme.colorScheme.onSurface.withValues(alpha: 0.65)),
          ),
          const SizedBox(height: 16),
          ElevatedButton.icon(
            onPressed: () => context.findAncestorStateOfType<MainShellState>()?.goTo(1),
            icon: const Icon(Icons.calendar_month_rounded),
            label: const Text('Agendar una cita'),
          ),
        ],
      ),
    );
  }

  Widget _nextAppointmentCard(Appointment appointment, RemoteConfig config) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
        child: Card(
          child: InkWell(
            borderRadius: BorderRadius.circular(config.theme.radius),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (BuildContext context) =>
                    AppointmentDetailScreen(appointmentId: appointment.id),
              ),
            ),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      const Icon(Icons.event_available_rounded, size: 18),
                      const SizedBox(width: 8),
                      const Expanded(
                        child: Text('Tu proxima cita',
                            style: TextStyle(fontWeight: FontWeight.w600)),
                      ),
                      StatusChip(
                        label: appointment.statusLabel,
                        color: appointment.status == 'confirmed'
                            ? const Color(0xFF10B981)
                            : const Color(0xFFF59E0B),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(
                    '${appointment.dateLabel} a las ${appointment.timeLabel}',
                    style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
                  ),
                  if (appointment.services.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        appointment.services
                            .map((AppointmentService s) => s.name)
                            .join(', '),
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.7),
                        ),
                      ),
                    ),
                  if (appointment.staffName.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(
                        'Con ${appointment.staffName}',
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.6),
                          fontSize: 13,
                        ),
                      ),
                    ),
                  if (!appointment.isPaid && appointment.pending > 0) ...<Widget>[
                    const SizedBox(height: 12),
                    Row(
                      children: <Widget>[
                        const Icon(Icons.payments_outlined, size: 16),
                        const SizedBox(width: 6),
                        Text(
                          'Pendiente: ${config.business.money(appointment.pending)}',
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      );

  Widget _sectionTitle(String title) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 26, 16, 12),
        child: Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
      );

  Widget _categoriesRow() => SizedBox(
        height: 44,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: _categories.length,
          separatorBuilder: (_, __) => const SizedBox(width: 8),
          itemBuilder: (BuildContext context, int index) {
            final ServiceCategory category = _categories[index];

            return ActionChip(
              label: Text(category.name),
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (BuildContext context) =>
                      BookingScreen(initialCategoryId: category.id),
                ),
              ),
            );
          },
        ),
      );

  Widget _featuredList(RemoteConfig config) => SizedBox(
        height: 232,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: _featured.length,
          separatorBuilder: (_, __) => const SizedBox(width: 12),
          itemBuilder: (BuildContext context, int index) {
            final Service service = _featured[index];

            return SizedBox(
              width: 190,
              child: Card(
                child: InkWell(
                  borderRadius: BorderRadius.circular(config.theme.radius),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (BuildContext context) =>
                          BookingScreen(initialServiceId: service.id),
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      RemoteImage(
                        url: service.imageUrl,
                        height: 110,
                        width: double.infinity,
                        borderRadius: BorderRadius.vertical(
                          top: Radius.circular(config.theme.radius),
                        ),
                      ),
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                service.name,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(fontWeight: FontWeight.w600),
                              ),
                              const Spacer(),
                              Row(
                                children: <Widget>[
                                  Text(
                                    config.business.money(service.price),
                                    style: TextStyle(
                                      color: Theme.of(context).colorScheme.primary,
                                      fontWeight: FontWeight.w700,
                                      fontSize: 15,
                                    ),
                                  ),
                                  const Spacer(),
                                  Text(
                                    service.durationLabel,
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: Theme.of(context)
                                          .colorScheme
                                          .onSurface
                                          .withValues(alpha: 0.6),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      );

  Widget _quickActions(RemoteConfig config) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 26, 16, 0),
        child: Card(
          child: Column(
            children: <Widget>[
              if (config.business.phone.isNotEmpty)
                ListTile(
                  leading: const Icon(Icons.phone_outlined),
                  title: const Text('Llamar al local'),
                  subtitle: Text(config.business.phone),
                  onTap: () => _launch('tel:${config.business.phone.replaceAll(RegExp(r'[^0-9+]'), '')}'),
                ),
              if (config.business.mapsUrl.isNotEmpty)
                ListTile(
                  leading: const Icon(Icons.location_on_outlined),
                  title: const Text('Como llegar'),
                  subtitle: Text(
                    config.business.address.isEmpty ? 'Ver en el mapa' : config.business.address,
                  ),
                  onTap: () => _launch(config.business.mapsUrl),
                ),
              if (config.social['instagram']?.isNotEmpty ?? false)
                ListTile(
                  leading: const Icon(Icons.camera_alt_outlined),
                  title: const Text('Siguenos en Instagram'),
                  onTap: () => _launch(config.social['instagram']!),
                ),
            ],
          ),
        ),
      );

  Future<void> _openWhatsApp(String number) =>
      _launch('https://wa.me/${number.replaceAll(RegExp(r'\D'), '')}');

  Future<void> _launch(String url) async {
    final Uri? uri = Uri.tryParse(url);

    if (uri == null) {
      return;
    }

    // Solo se abren esquemas conocidos.
    if (!<String>['http', 'https', 'tel', 'mailto'].contains(uri.scheme)) {
      return;
    }

    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}
