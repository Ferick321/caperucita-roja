import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/services/appointment_service.dart';
import '../../core/services/auth_service.dart';
import '../../core/services/catalog_service.dart';
import '../../core/services/config_service.dart';
import '../../models/appointment.dart';
import '../../models/catalog.dart';
import '../../models/remote_config.dart';
import '../../widgets/common.dart';
import '../payments/payment_screen.dart';

/// Flujo de reserva en cuatro pasos: servicios, profesional, fecha y confirmacion.
class BookingScreen extends StatefulWidget {
  const BookingScreen({super.key, this.initialServiceId, this.initialCategoryId});

  final int? initialServiceId;
  final int? initialCategoryId;

  @override
  State<BookingScreen> createState() => _BookingScreenState();
}

class _BookingScreenState extends State<BookingScreen> {
  final TextEditingController _notesController = TextEditingController();
  final TextEditingController _customController = TextEditingController();
  final TextEditingController _couponController = TextEditingController();

  int _step = 0;

  List<ServiceCategory> _categories = <ServiceCategory>[];
  List<Service> _services = <Service>[];
  List<StaffMember> _staff = <StaffMember>[];
  List<AvailableDay> _days = <AvailableDay>[];
  List<TimeSlot> _slots = <TimeSlot>[];

  final Set<int> _selectedServices = <int>{};
  int _selectedStaffId = 0;
  String _selectedDate = '';
  String _selectedTime = '';
  int _branchId = 0;

  bool _loading = true;
  bool _loadingDays = false;
  bool _loadingSlots = false;
  bool _submitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _notesController.dispose();
    _customController.dispose();
    _couponController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final RemoteConfig config = ConfigService.instance.config;

      _branchId = config.branches.isEmpty
          ? 0
          : config.branches
              .firstWhere(
                (Branch b) => b.isDefault,
                orElse: () => config.branches.first,
              )
              .id;

      final List<ServiceCategory> categories = await CatalogService.instance.categories();
      final List<Service> services = await CatalogService.instance.services(
        categoryId: widget.initialCategoryId,
      );
      final List<StaffMember> staff = await CatalogService.instance.staff(branchId: _branchId);

      if (!mounted) {
        return;
      }

      setState(() {
        _categories = categories;
        _services = services;
        _staff = staff;
        _loading = false;

        if (widget.initialServiceId != null) {
          _selectedServices.add(widget.initialServiceId!);
        }
      });

      if (_selectedServices.isNotEmpty) {
        await _loadDays();
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

  Future<void> _loadDays() async {
    if (_selectedServices.isEmpty && _customController.text.trim().isEmpty) {
      setState(() => _days = <AvailableDay>[]);

      return;
    }

    setState(() {
      _loadingDays = true;
      _slots = <TimeSlot>[];
      _selectedDate = '';
      _selectedTime = '';
    });

    try {
      final List<AvailableDay> days = await CatalogService.instance.availableDays(
        serviceIds: _selectedServices.toList(),
        branchId: _branchId,
        staffId: _selectedStaffId,
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _days = days;
        _loadingDays = false;
      });

      if (days.isNotEmpty) {
        await _loadSlots(days.first.date);
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _loadingDays = false;
          _error = error.toString();
        });
      }
    }
  }

  Future<void> _loadSlots(String date) async {
    setState(() {
      _loadingSlots = true;
      _selectedDate = date;
      _selectedTime = '';
    });

    try {
      final List<TimeSlot> slots = await CatalogService.instance.slots(
        serviceIds: _selectedServices.toList(),
        branchId: _branchId,
        date: date,
        staffId: _selectedStaffId,
      );

      if (mounted) {
        setState(() {
          _slots = slots;
          _loadingSlots = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _slots = <TimeSlot>[];
          _loadingSlots = false;
        });
      }
    }
  }

  double get _total => _services
      .where((Service s) => _selectedServices.contains(s.id))
      .fold<double>(0, (double sum, Service s) => sum + s.price);

  int get _totalMinutes => _services
      .where((Service s) => _selectedServices.contains(s.id))
      .fold<int>(0, (int sum, Service s) => sum + s.durationMinutes);

  bool get _canContinue => switch (_step) {
        0 => _selectedServices.isNotEmpty || _customController.text.trim().isNotEmpty,
        1 => true,
        2 => _selectedDate.isNotEmpty && _selectedTime.isNotEmpty,
        _ => true,
      };

  @override
  Widget build(BuildContext context) {
    final RemoteConfig config = ConfigService.instance.config;

    if (!config.booking.enabled) {
      return Scaffold(
        appBar: AppBar(title: const Text('Agendar')),
        body: const EmptyState(
          icon: Icons.event_busy_outlined,
          message: 'El agendamiento en linea esta pausado por el momento. '
              'Comunicate con el local para reservar.',
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Agendar cita'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(4),
          child: LinearProgressIndicator(
            value: (_step + 1) / 4,
            minHeight: 4,
            backgroundColor: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.08),
          ),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _services.isEmpty
              ? ErrorState(message: _error!, onRetry: _load)
              : Column(
                  children: <Widget>[
                    Expanded(child: _buildStep(config)),
                    _bottomBar(config),
                  ],
                ),
    );
  }

  Widget _buildStep(RemoteConfig config) => switch (_step) {
        0 => _stepServices(config),
        1 => _stepStaff(config),
        2 => _stepSchedule(config),
        _ => _stepConfirm(config),
      };

  // ---- Paso 1: servicios ------------------------------------------------

  Widget _stepServices(RemoteConfig config) {
    final bool multiple = config.booking.allowMultipleServices;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        const Text(
          'Que servicio necesitas?',
          style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 4),
        Text(
          multiple
              ? 'Puedes elegir hasta ${config.booking.maxServices} servicios.'
              : 'Elige un servicio.',
          style: TextStyle(
            color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.65),
          ),
        ),
        const SizedBox(height: 18),

        for (final ServiceCategory category in _categories)
          ..._categorySection(category, config, multiple: multiple),

        if (config.booking.customRequestEnabled) ...<Widget>[
          const SizedBox(height: 24),
          TextField(
            controller: _customController,
            maxLength: 255,
            decoration: InputDecoration(
              labelText: config.booking.customRequestLabel,
              hintText: 'Ej. peinado para matrimonio, tratamiento a medida...',
              helperText: 'Si no encuentras lo que buscas, describelo y lo coordinamos.',
            ),
            onChanged: (_) => setState(() {}),
          ),
        ],
      ],
    );
  }

  List<Widget> _categorySection(
    ServiceCategory category,
    RemoteConfig config, {
    required bool multiple,
  }) {
    final List<Service> items =
        _services.where((Service s) => s.categoryId == category.id).toList();

    if (items.isEmpty) {
      return <Widget>[];
    }

    return <Widget>[
      Padding(
        padding: const EdgeInsets.only(top: 8, bottom: 8),
        child: Text(
          category.name,
          style: TextStyle(
            color: Theme.of(context).colorScheme.primary,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
      for (final Service service in items)
        Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Card(
            child: CheckboxListTile(
              value: _selectedServices.contains(service.id),
              onChanged: (bool? checked) => _toggleService(service, config, multiple: multiple),
              controlAffinity: ListTileControlAffinity.leading,
              title: Text(service.name, style: const TextStyle(fontWeight: FontWeight.w600)),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  if (service.shortDescription.isNotEmpty)
                    Text(service.shortDescription, maxLines: 2, overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 4),
                  Row(
                    children: <Widget>[
                      Text(
                        config.business.money(service.price),
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.primary,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Text(service.durationLabel),
                      if (service.hasPromo) ...<Widget>[
                        const SizedBox(width: 8),
                        const StatusChip(label: 'Promo', color: Color(0xFFE11D48)),
                      ],
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
    ];
  }

  void _toggleService(Service service, RemoteConfig config, {required bool multiple}) {
    setState(() {
      if (_selectedServices.contains(service.id)) {
        _selectedServices.remove(service.id);
      } else {
        if (!multiple) {
          _selectedServices.clear();
        } else if (_selectedServices.length >= config.booking.maxServices) {
          showMessage(
            context,
            'Puedes elegir hasta ${config.booking.maxServices} servicios.',
            isError: true,
          );

          return;
        }

        _selectedServices.add(service.id);
      }
    });
  }

  // ---- Paso 2: profesional ---------------------------------------------

  Widget _stepStaff(RemoteConfig config) {
    if (!config.booking.allowStaffChoice) {
      return const EmptyState(
        icon: Icons.people_outline,
        message: 'Asignaremos al profesional disponible para tu horario.',
      );
    }

    // Solo se ofrecen los profesionales que prestan todos los servicios elegidos.
    final List<StaffMember> eligible = _staff
        .where((StaffMember member) =>
            _selectedServices.every((int id) => member.canPerform(id)))
        .toList();

    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        const Text(
          'Con quien quieres atenderte?',
          style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 18),

        if (config.booking.allowNoPreference)
          Card(
            child: RadioListTile<int>(
              value: 0,
              groupValue: _selectedStaffId,
              onChanged: _selectStaff,
              title: const Text('Sin preferencia'),
              subtitle: const Text('Te asignamos al primero disponible'),
              secondary: const CircleAvatar(child: Icon(Icons.groups_outlined)),
            ),
          ),

        for (final StaffMember member in eligible)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Card(
              child: RadioListTile<int>(
                value: member.id,
                groupValue: _selectedStaffId,
                onChanged: _selectStaff,
                title: Text(member.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                subtitle: Text(
                  member.ratingCount > 0
                      ? '${member.title} · ${member.rating.toStringAsFixed(1)} (${member.ratingCount})'
                      : member.title,
                ),
                secondary: AvatarCircle(
                  initials: member.initials,
                  photoUrl: member.photoUrl,
                  size: 44,
                ),
              ),
            ),
          ),

        if (eligible.isEmpty && !config.booking.allowNoPreference)
          const Padding(
            padding: EdgeInsets.only(top: 24),
            child: EmptyState(
              icon: Icons.person_search_outlined,
              message: 'Ningun profesional presta todos los servicios elegidos. '
                  'Prueba a reservarlos en citas separadas.',
            ),
          ),
      ],
    );
  }

  void _selectStaff(int? value) {
    setState(() => _selectedStaffId = value ?? 0);
    _loadDays();
  }

  // ---- Paso 3: fecha y hora --------------------------------------------

  Widget _stepSchedule(RemoteConfig config) {
    if (_loadingDays) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_days.isEmpty) {
      return EmptyState(
        icon: Icons.event_busy_outlined,
        message: 'No hay horarios disponibles con esta combinacion. '
            'Prueba con otro profesional o comunicate con el local.',
        actionLabel: 'Volver a consultar',
        onAction: _loadDays,
      );
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        const Text(
          'Cuando te viene bien?',
          style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 16),

        SizedBox(
          height: 92,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: _days.length,
            separatorBuilder: (_, __) => const SizedBox(width: 10),
            itemBuilder: (BuildContext context, int index) {
              final AvailableDay day = _days[index];
              final bool selected = day.date == _selectedDate;

              return GestureDetector(
                onTap: () => _loadSlots(day.date),
                child: Container(
                  width: 86,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: selected
                        ? Theme.of(context).colorScheme.primary.withValues(alpha: 0.16)
                        : Theme.of(context).colorScheme.surface,
                    borderRadius: BorderRadius.circular(config.theme.radius),
                    border: Border.all(
                      color: selected
                          ? Theme.of(context).colorScheme.primary
                          : Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.1),
                    ),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: <Widget>[
                      Text(
                        day.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 11,
                          color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.7),
                        ),
                      ),
                      Text(
                        '${day.dayNumber}',
                        style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700),
                      ),
                      Text(
                        '${day.slots} libres',
                        style: const TextStyle(fontSize: 10, color: Color(0xFF10B981)),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),

        const SizedBox(height: 22),

        if (_loadingSlots)
          const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator()))
        else if (_slots.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: Text('Ese dia se lleno. Elige otra fecha.', textAlign: TextAlign.center),
          )
        else
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: _slots.map((TimeSlot slot) {
              final bool selected = slot.time == _selectedTime;

              return ChoiceChip(
                label: Text(slot.label),
                selected: selected,
                onSelected: (_) => setState(() => _selectedTime = slot.time),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              );
            }).toList(),
          ),
      ],
    );
  }

  // ---- Paso 4: confirmacion --------------------------------------------

  Widget _stepConfirm(RemoteConfig config) {
    final List<Service> chosen =
        _services.where((Service s) => _selectedServices.contains(s.id)).toList();

    final String staffName = _selectedStaffId == 0
        ? 'Sin preferencia'
        : _staff
            .firstWhere(
              (StaffMember m) => m.id == _selectedStaffId,
              orElse: () => const StaffMember(
                id: 0, name: 'Sin preferencia', title: '', bio: '',
                photoUrl: null, rating: 0, ratingCount: 0, serviceIds: <int>[],
              ),
            )
            .name;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        const Text(
          'Confirma tu cita',
          style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 16),

        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: <Widget>[
                for (final Service service in chosen)
                  DetailRow(
                    label: service.name,
                    value: config.business.money(service.price),
                  ),
                if (_customController.text.trim().isNotEmpty)
                  DetailRow(
                    label: 'Peticion especial',
                    value: _customController.text.trim(),
                  ),
                const Divider(height: 24),
                DetailRow(label: 'Fecha', value: _formatDate(_selectedDate)),
                DetailRow(label: 'Hora', value: _selectedTime),
                DetailRow(label: 'Duracion', value: '$_totalMinutes min'),
                DetailRow(label: 'Profesional', value: staffName),
                const Divider(height: 24),
                Row(
                  children: <Widget>[
                    const Expanded(
                      child: Text('Total', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                    ),
                    Text(
                      config.business.money(_total),
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                        color: Theme.of(context).colorScheme.primary,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),

        const SizedBox(height: 16),

        TextField(
          controller: _notesController,
          maxLines: 3,
          maxLength: 1000,
          decoration: const InputDecoration(
            labelText: 'Algo que debamos saber? (opcional)',
            hintText: 'Alergias, referencia de corte, preferencias...',
          ),
        ),

        TextField(
          controller: _couponController,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(
            labelText: 'Cupon de descuento (opcional)',
          ),
        ),

        if (config.booking.termsText.isNotEmpty) ...<Widget>[
          const SizedBox(height: 16),
          Text(
            config.booking.termsText,
            style: TextStyle(
              fontSize: 12,
              color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.6),
            ),
          ),
        ],

        const SizedBox(height: 8),
        Text(
          'Podras cancelar con al menos ${config.booking.cancellationHours} horas de antelacion.',
          style: TextStyle(
            fontSize: 12,
            color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.6),
          ),
        ),
      ],
    );
  }

  // ---- Barra inferior ---------------------------------------------------

  Widget _bottomBar(RemoteConfig config) => SafeArea(
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surface,
            border: Border(
              top: BorderSide(
                color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.08),
              ),
            ),
          ),
          child: Row(
            children: <Widget>[
              if (_step > 0)
                Expanded(
                  child: OutlinedButton(
                    onPressed: _submitting ? null : () => setState(() => _step--),
                    child: const Text('Atras'),
                  ),
                ),
              if (_step > 0) const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    if (_total > 0 && _step < 3)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Text(
                          'Total: ${config.business.money(_total)}',
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                      ),
                    ElevatedButton(
                      onPressed: !_canContinue || _submitting ? null : _next,
                      child: _submitting
                          ? const SizedBox(
                              height: 22,
                              width: 22,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Text(_step == 3 ? 'Confirmar cita' : 'Continuar'),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      );

  Future<void> _next() async {
    if (_step < 3) {
      setState(() => _step++);

      if (_step == 2 && _days.isEmpty) {
        await _loadDays();
      }

      return;
    }

    await _submit();
  }

  Future<void> _submit() async {
    if (!AuthService.instance.isLoggedIn) {
      final Object? result = await Navigator.of(context).pushNamed('/login');

      if (result != true || !AuthService.instance.isLoggedIn) {
        return;
      }
    }

    setState(() => _submitting = true);

    try {
      final Appointment appointment = await AppointmentService.instance.create(
        branchId: _branchId,
        serviceIds: _selectedServices.toList(),
        date: _selectedDate,
        time: _selectedTime,
        staffId: _selectedStaffId,
        notes: _notesController.text.trim(),
        customRequest: _customController.text.trim(),
        couponCode: _couponController.text.trim(),
      );

      if (!mounted) {
        return;
      }

      // Se pasa directo al pago para no perder la intencion del cliente.
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (BuildContext context) => PaymentScreen(
            appointmentId: appointment.id,
            justCreated: true,
          ),
        ),
      );
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      setState(() => _submitting = false);

      // Si el hueco se ocupo mientras decidia, se vuelve al paso del horario.
      if (error.isConflict) {
        setState(() => _step = 2);
        await _loadSlots(_selectedDate);
      }

      showMessage(context, error.message, isError: true);
    } catch (error) {
      if (mounted) {
        setState(() => _submitting = false);
        showMessage(context, 'No pudimos crear la cita. Intentalo de nuevo.', isError: true);
      }
    }
  }

  String _formatDate(String date) {
    final List<String> parts = date.split('-');

    return parts.length == 3 ? '${parts[2]}/${parts[1]}/${parts[0]}' : date;
  }
}
