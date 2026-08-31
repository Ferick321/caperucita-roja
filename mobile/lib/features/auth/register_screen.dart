import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_exception.dart';
import '../../core/services/auth_service.dart';
import '../../core/services/config_service.dart';

/// Registro de un cliente nuevo.
class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _firstName = TextEditingController();
  final TextEditingController _lastName = TextEditingController();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _password = TextEditingController();
  final TextEditingController _passwordConfirm = TextEditingController();

  bool _obscure = true;
  bool _acceptsTerms = false;
  bool _acceptsMarketing = true;
  bool _loading = false;
  String? _error;

  /// Errores por campo que devuelve el servidor.
  Map<String, String> _fieldErrors = <String, String>{};

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    _passwordConfirm.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Crear cuenta')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text(
                  'Reserva mas rapido y acumula puntos en cada visita.',
                  style: TextStyle(color: theme.colorScheme.onSurface.withValues(alpha: 0.7)),
                ),

                const SizedBox(height: 22),

                if (_error != null)
                  Container(
                    margin: const EdgeInsets.only(bottom: 16),
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: theme.colorScheme.error.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(_error!, style: TextStyle(color: theme.colorScheme.error)),
                  ),

                TextFormField(
                  controller: _firstName,
                  textCapitalization: TextCapitalization.words,
                  decoration: InputDecoration(
                    labelText: 'Nombre *',
                    prefixIcon: const Icon(Icons.person_outline),
                    errorText: _fieldErrors['first_name'],
                  ),
                  validator: (String? v) =>
                      (v == null || v.trim().length < 2) ? 'Escribe tu nombre' : null,
                ),

                const SizedBox(height: 14),

                TextFormField(
                  controller: _lastName,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(
                    labelText: 'Apellido',
                    prefixIcon: Icon(Icons.badge_outlined),
                  ),
                ),

                const SizedBox(height: 14),

                TextFormField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: InputDecoration(
                    labelText: 'Correo electronico *',
                    prefixIcon: const Icon(Icons.mail_outline),
                    errorText: _fieldErrors['email'],
                  ),
                  validator: (String? v) =>
                      (v == null || !v.contains('@')) ? 'Escribe un correo valido' : null,
                ),

                const SizedBox(height: 14),

                TextFormField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  decoration: InputDecoration(
                    labelText: 'Telefono / WhatsApp *',
                    prefixIcon: const Icon(Icons.phone_outlined),
                    helperText: 'Lo usamos para avisarte de tu cita.',
                    errorText: _fieldErrors['phone'],
                  ),
                  validator: (String? v) => (v == null || v.replaceAll(RegExp(r'\D'), '').length < 7)
                      ? 'Escribe un telefono valido'
                      : null,
                ),

                const SizedBox(height: 14),

                TextFormField(
                  controller: _password,
                  obscureText: _obscure,
                  decoration: InputDecoration(
                    labelText: 'Contrasena *',
                    prefixIcon: const Icon(Icons.lock_outline),
                    helperText: 'Minimo 10 caracteres con mayusculas, numeros o simbolos.',
                    errorText: _fieldErrors['password'],
                    suffixIcon: IconButton(
                      icon: Icon(_obscure ? Icons.visibility_off : Icons.visibility),
                      onPressed: () => setState(() => _obscure = !_obscure),
                    ),
                  ),
                  validator: (String? v) =>
                      (v == null || v.length < 10) ? 'Usa al menos 10 caracteres' : null,
                ),

                const SizedBox(height: 14),

                TextFormField(
                  controller: _passwordConfirm,
                  obscureText: _obscure,
                  decoration: const InputDecoration(
                    labelText: 'Repite la contrasena *',
                    prefixIcon: Icon(Icons.lock_outline),
                  ),
                  validator: (String? v) =>
                      v != _password.text ? 'Las contrasenas no coinciden' : null,
                ),

                const SizedBox(height: 20),

                CheckboxListTile(
                  value: _acceptsTerms,
                  onChanged: (bool? v) => setState(() => _acceptsTerms = v ?? false),
                  controlAffinity: ListTileControlAffinity.leading,
                  contentPadding: EdgeInsets.zero,
                  title: Wrap(
                    children: <Widget>[
                      const Text('Acepto los '),
                      GestureDetector(
                        onTap: () => _openLegal('terms_url'),
                        child: Text(
                          'terminos',
                          style: TextStyle(
                            color: theme.colorScheme.primary,
                            decoration: TextDecoration.underline,
                          ),
                        ),
                      ),
                      const Text(' y la '),
                      GestureDetector(
                        onTap: () => _openLegal('privacy_url'),
                        child: Text(
                          'politica de privacidad',
                          style: TextStyle(
                            color: theme.colorScheme.primary,
                            decoration: TextDecoration.underline,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                CheckboxListTile(
                  value: _acceptsMarketing,
                  onChanged: (bool? v) => setState(() => _acceptsMarketing = v ?? false),
                  controlAffinity: ListTileControlAffinity.leading,
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Quiero recibir promociones y novedades'),
                  subtitle: const Text('Puedes darte de baja cuando quieras.'),
                ),

                const SizedBox(height: 20),

                ElevatedButton(
                  onPressed: _loading ? null : _submit,
                  child: _loading
                      ? const SizedBox(
                          height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Crear mi cuenta'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _openLegal(String key) async {
    final String url = ConfigService.instance.config.legal[key] ?? '';
    final Uri? uri = Uri.tryParse(url);

    if (uri != null && <String>['http', 'https'].contains(uri.scheme)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  Future<void> _submit() async {
    setState(() => _fieldErrors = <String, String>{});

    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    if (!_acceptsTerms) {
      setState(() => _error = 'Debes aceptar los terminos para crear tu cuenta.');

      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await AuthService.instance.register(
        firstName: _firstName.text.trim(),
        lastName: _lastName.text.trim(),
        email: _email.text.trim(),
        phone: _phone.text.trim(),
        password: _password.text,
        acceptsMarketing: _acceptsMarketing,
      );

      if (mounted) {
        // Se cierran registro y login de una vez.
        Navigator.of(context)
          ..pop(true)
          ..pop(true);
      }
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      // Los errores por campo se marcan en el formulario.
      final Map<String, String> errors = <String, String>{};

      for (final String field in <String>['first_name', 'email', 'phone', 'password']) {
        final String? message = error.errorFor(field);

        if (message != null) {
          errors[field] = message;
        }
      }

      setState(() {
        _fieldErrors = errors;
        _error = errors.isEmpty ? error.message : null;
        _loading = false;
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'No pudimos conectar. Revisa tu conexion.';
          _loading = false;
        });
      }
    }
  }
}
