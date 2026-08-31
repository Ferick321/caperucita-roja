import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_exception.dart';
import '../../core/services/auth_service.dart';
import '../../core/services/config_service.dart';
import '../../models/remote_config.dart';
import '../../models/user_profile.dart';
import '../../widgets/common.dart';

/// Perfil del cliente: datos, puntos, preferencias y ajustes de la cuenta.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _saving = false;

  @override
  Widget build(BuildContext context) {
    final UserProfile? user = AuthService.instance.user;
    final RemoteConfig config = ConfigService.instance.config;

    if (user == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Mi perfil')),
        body: EmptyState(
          icon: Icons.person_outline,
          message: 'Inicia sesion para ver tu perfil, tus puntos y tus citas.',
          actionLabel: 'Ingresar',
          onAction: () async {
            await Navigator.of(context).pushNamed('/login');
            setState(() {});
          },
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Mi perfil')),
      body: RefreshIndicator(
        onRefresh: () async {
          await AuthService.instance.refreshProfile();
          setState(() {});
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: <Widget>[
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Row(
                  children: <Widget>[
                    AvatarCircle(
                      initials: user.initials,
                      photoUrl: user.avatarUrl,
                      size: 62,
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            user.fullName,
                            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                          ),
                          Text(
                            user.email,
                            style: TextStyle(
                              fontSize: 13,
                              color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.65),
                            ),
                          ),
                          if (user.phone.isNotEmpty)
                            Text(
                              user.phone,
                              style: TextStyle(
                                fontSize: 13,
                                color: Theme.of(context)
                                    .colorScheme
                                    .onSurface
                                    .withValues(alpha: 0.65),
                              ),
                            ),
                        ],
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.edit_outlined),
                      tooltip: 'Editar mis datos',
                      onPressed: () => _editProfile(user),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 14),

            Row(
              children: <Widget>[
                Expanded(
                  child: _statCard('Visitas', '${user.totalVisits}', Icons.event_available_outlined),
                ),
                if (config.loyalty.enabled) ...<Widget>[
                  const SizedBox(width: 12),
                  Expanded(
                    child: _statCard(
                      'Puntos',
                      '${user.loyaltyPoints}',
                      Icons.stars_outlined,
                      subtitle: config.business.money(user.loyaltyValue),
                    ),
                  ),
                ],
              ],
            ),

            if (user.referralCode.isNotEmpty) ...<Widget>[
              const SizedBox(height: 14),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.card_giftcard_outlined),
                  title: const Text('Tu codigo de referido'),
                  subtitle: Text(
                    user.referralCode,
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.5,
                    ),
                  ),
                  trailing: IconButton(
                    icon: const Icon(Icons.copy),
                    tooltip: 'Copiar',
                    onPressed: () async {
                      await Clipboard.setData(ClipboardData(text: user.referralCode));

                      if (mounted) {
                        showMessage(context, 'Codigo copiado');
                      }
                    },
                  ),
                ),
              ),
            ],

            const SizedBox(height: 20),

            Card(
              child: Column(
                children: <Widget>[
                  SwitchListTile(
                    value: user.acceptsMarketing,
                    onChanged: _saving
                        ? null
                        : (bool value) => _updatePreference('accepts_marketing', value),
                    title: const Text('Recibir promociones'),
                    subtitle: const Text('Ofertas y novedades del salon'),
                    secondary: const Icon(Icons.campaign_outlined),
                  ),
                  const Divider(height: 1),
                  SwitchListTile(
                    value: user.acceptsPush,
                    onChanged: _saving
                        ? null
                        : (bool value) => _updatePreference('accepts_push', value),
                    title: const Text('Avisos en la app'),
                    subtitle: const Text('Recordatorios y cambios de tus citas'),
                    secondary: const Icon(Icons.notifications_outlined),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            Card(
              child: Column(
                children: <Widget>[
                  ListTile(
                    leading: const Icon(Icons.lock_outline),
                    title: const Text('Cambiar contrasena'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: _changePassword,
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.privacy_tip_outlined),
                    title: const Text('Politica de privacidad'),
                    trailing: const Icon(Icons.open_in_new, size: 18),
                    onTap: () => _openUrl(config.legal['privacy_url'] ?? ''),
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.description_outlined),
                    title: const Text('Terminos y condiciones'),
                    trailing: const Icon(Icons.open_in_new, size: 18),
                    onTap: () => _openUrl(config.legal['terms_url'] ?? ''),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            OutlinedButton.icon(
              onPressed: _logout,
              icon: const Icon(Icons.logout),
              label: const Text('Cerrar sesion'),
            ),

            const SizedBox(height: 10),

            TextButton(
              onPressed: _deleteAccount,
              style: TextButton.styleFrom(foregroundColor: const Color(0xFFEF4444)),
              child: const Text('Eliminar mi cuenta'),
            ),

            const SizedBox(height: 20),

            Center(
              child: Text(
                '${config.business.name} · version ${config.app.latestVersion}',
                style: TextStyle(
                  fontSize: 12,
                  color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.45),
                ),
              ),
            ),

            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _statCard(String label, String value, IconData icon, {String? subtitle}) => Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Icon(icon, color: Theme.of(context).colorScheme.primary),
              const SizedBox(height: 10),
              Text(value, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w700)),
              Text(
                label,
                style: TextStyle(
                  fontSize: 13,
                  color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.65),
                ),
              ),
              if (subtitle != null)
                Text(
                  subtitle,
                  style: TextStyle(
                    fontSize: 12,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                ),
            ],
          ),
        ),
      );

  Future<void> _updatePreference(String key, bool value) async {
    setState(() => _saving = true);

    try {
      await AuthService.instance.updateProfile(<String, dynamic>{key: value});
    } on ApiException catch (error) {
      if (mounted) {
        showMessage(context, error.message, isError: true);
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _editProfile(UserProfile user) async {
    final TextEditingController firstName = TextEditingController(text: user.firstName);
    final TextEditingController lastName = TextEditingController(text: user.lastName);
    final TextEditingController phone = TextEditingController(text: user.phone);

    final bool? save = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (BuildContext sheetContext) => Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 20,
          bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 20,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            const Text('Mis datos', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700)),
            const SizedBox(height: 16),
            TextField(
              controller: firstName,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Nombre'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: lastName,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Apellido'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(labelText: 'Telefono'),
            ),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: () => Navigator.of(sheetContext).pop(true),
              child: const Text('Guardar cambios'),
            ),
          ],
        ),
      ),
    );

    if (save == true) {
      try {
        await AuthService.instance.updateProfile(<String, dynamic>{
          'first_name': firstName.text.trim(),
          'last_name': lastName.text.trim(),
          'phone': phone.text.trim(),
        });

        if (mounted) {
          showMessage(context, 'Datos actualizados');
          setState(() {});
        }
      } on ApiException catch (error) {
        if (mounted) {
          showMessage(context, error.message, isError: true);
        }
      }
    }

    firstName.dispose();
    lastName.dispose();
    phone.dispose();
  }

  Future<void> _changePassword() async {
    final TextEditingController current = TextEditingController();
    final TextEditingController fresh = TextEditingController();

    final bool? save = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Cambiar contrasena'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            TextField(
              controller: current,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Contrasena actual'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: fresh,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'Nueva contrasena',
                helperText: 'Minimo 10 caracteres',
              ),
            ),
          ],
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Cambiar'),
          ),
        ],
      ),
    );

    if (save == true) {
      try {
        await AuthService.instance.changePassword(
          currentPassword: current.text,
          newPassword: fresh.text,
        );

        if (mounted) {
          showMessage(context, 'Contrasena actualizada. Vuelve a iniciar sesion.');
          await Navigator.of(context).pushNamedAndRemoveUntil(
            '/login',
            (Route<dynamic> route) => false,
          );
        }
      } on ApiException catch (error) {
        if (mounted) {
          showMessage(context, error.message, isError: true);
        }
      }
    }

    current.dispose();
    fresh.dispose();
  }

  Future<void> _logout() async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Cerrar sesion'),
        content: const Text('Seguro que quieres salir de tu cuenta?'),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Salir'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await AuthService.instance.logout();

      if (mounted) {
        setState(() {});
      }
    }
  }

  Future<void> _deleteAccount() async {
    final TextEditingController password = TextEditingController();

    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Eliminar mi cuenta'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Text(
              'Se eliminaran tus datos personales, tu foto y tus comprobantes de forma '
              'definitiva. Tu historial de citas se conserva de forma anonima por motivos '
              'contables. Esta accion no se puede deshacer.',
            ),
            const SizedBox(height: 16),
            TextField(
              controller: password,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Confirma tu contrasena'),
            ),
          ],
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFEF4444)),
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Eliminar'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      try {
        await AuthService.instance.deleteAccount(password.text);

        if (mounted) {
          await Navigator.of(context).pushNamedAndRemoveUntil(
            '/login',
            (Route<dynamic> route) => false,
          );
        }
      } on ApiException catch (error) {
        if (mounted) {
          showMessage(context, error.message, isError: true);
        }
      }
    }

    password.dispose();
  }

  Future<void> _openUrl(String url) async {
    final Uri? uri = Uri.tryParse(url);

    if (uri != null && <String>['http', 'https'].contains(uri.scheme)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }
}
