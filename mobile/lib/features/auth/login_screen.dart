import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/services/auth_service.dart';
import '../../widgets/common.dart';
import 'register_screen.dart';

/// Acceso del cliente.
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();

  bool _obscure = true;
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Ingresar')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                const SizedBox(height: 12),

                const Text(
                  'Bienvenido de vuelta',
                  style: TextStyle(fontSize: 26, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 6),
                Text(
                  'Consulta tus citas, tus puntos y tus comprobantes.',
                  style: TextStyle(color: theme.colorScheme.onSurface.withValues(alpha: 0.65)),
                ),

                const SizedBox(height: 28),

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
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  autofillHints: const <String>[AutofillHints.email],
                  decoration: const InputDecoration(
                    labelText: 'Correo electronico',
                    prefixIcon: Icon(Icons.mail_outline),
                  ),
                  validator: (String? value) =>
                      (value == null || !value.contains('@')) ? 'Escribe un correo valido' : null,
                ),

                const SizedBox(height: 14),

                TextFormField(
                  controller: _passwordController,
                  obscureText: _obscure,
                  textInputAction: TextInputAction.done,
                  autofillHints: const <String>[AutofillHints.password],
                  decoration: InputDecoration(
                    labelText: 'Contrasena',
                    prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(
                      icon: Icon(_obscure ? Icons.visibility_off : Icons.visibility),
                      tooltip: _obscure ? 'Mostrar' : 'Ocultar',
                      onPressed: () => setState(() => _obscure = !_obscure),
                    ),
                  ),
                  validator: (String? value) =>
                      (value == null || value.isEmpty) ? 'Escribe tu contrasena' : null,
                  onFieldSubmitted: (_) => _submit(),
                ),

                const SizedBox(height: 24),

                ElevatedButton(
                  onPressed: _loading ? null : _submit,
                  child: _loading
                      ? const SizedBox(
                          height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Ingresar'),
                ),

                const SizedBox(height: 12),

                TextButton(
                  onPressed: _loading ? null : _forgotPassword,
                  child: const Text('Olvidaste tu contrasena?'),
                ),

                const Divider(height: 36),

                OutlinedButton(
                  onPressed: _loading
                      ? null
                      : () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (BuildContext context) => const RegisterScreen(),
                            ),
                          ),
                  child: const Text('Crear una cuenta nueva'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await AuthService.instance.login(
        email: _emailController.text.trim(),
        password: _passwordController.text,
      );

      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'No pudimos conectar. Revisa tu conexion.';
          _loading = false;
        });
      }
    }
  }

  Future<void> _forgotPassword() async {
    final TextEditingController controller =
        TextEditingController(text: _emailController.text.trim());

    final bool? send = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Recuperar contrasena'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Text('Te enviaremos un enlace para crear una contrasena nueva.'),
            const SizedBox(height: 16),
            TextField(
              controller: controller,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(labelText: 'Tu correo'),
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
            child: const Text('Enviar'),
          ),
        ],
      ),
    );

    if (send != true) {
      return;
    }

    try {
      await AuthService.instance.forgotPassword(controller.text.trim());

      if (mounted) {
        showMessage(
          context,
          'Si el correo esta registrado, te enviamos las instrucciones.',
        );
      }
    } catch (_) {
      if (mounted) {
        showMessage(context, 'No pudimos enviar el correo. Intentalo mas tarde.', isError: true);
      }
    } finally {
      controller.dispose();
    }
  }
}
