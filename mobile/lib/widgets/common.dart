import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

/// Widgets reutilizables en toda la aplicacion.

/// Estado vacio con icono, mensaje y accion opcional.
class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.icon,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(icon, size: 56, color: theme.colorScheme.onSurface.withValues(alpha: 0.3)),
            const SizedBox(height: 16),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: theme.colorScheme.onSurface.withValues(alpha: 0.65),
                fontSize: 15,
              ),
            ),
            if (actionLabel != null && onAction != null) ...<Widget>[
              const SizedBox(height: 24),
              SizedBox(
                width: 220,
                child: ElevatedButton(onPressed: onAction, child: Text(actionLabel!)),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Aviso de error con opcion de reintentar.
class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.message, this.onRetry});

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => EmptyState(
        icon: Icons.wifi_off_rounded,
        message: message,
        actionLabel: onRetry == null ? null : 'Reintentar',
        onAction: onRetry,
      );
}

/// Imagen remota con marcador de posicion y respaldo si falla.
class RemoteImage extends StatelessWidget {
  const RemoteImage({
    super.key,
    required this.url,
    this.height,
    this.width,
    this.fit = BoxFit.cover,
    this.borderRadius,
  });

  final String? url;
  final double? height;
  final double? width;
  final BoxFit fit;
  final BorderRadius? borderRadius;

  @override
  Widget build(BuildContext context) {
    final Color placeholder =
        Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.07);

    Widget content;

    if (url == null || url!.isEmpty) {
      content = Container(
        height: height,
        width: width,
        color: placeholder,
        child: Icon(
          Icons.image_outlined,
          color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.25),
        ),
      );
    } else {
      content = CachedNetworkImage(
        imageUrl: url!,
        height: height,
        width: width,
        fit: fit,
        placeholder: (BuildContext context, String _) =>
            Container(height: height, width: width, color: placeholder),
        errorWidget: (BuildContext context, String _, Object __) => Container(
          height: height,
          width: width,
          color: placeholder,
          child: Icon(
            Icons.broken_image_outlined,
            color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.25),
          ),
        ),
      );
    }

    return borderRadius == null
        ? content
        : ClipRRect(borderRadius: borderRadius!, child: content);
  }
}

/// Avatar circular con iniciales cuando no hay foto.
class AvatarCircle extends StatelessWidget {
  const AvatarCircle({
    super.key,
    required this.initials,
    this.photoUrl,
    this.size = 48,
    this.color,
  });

  final String initials;
  final String? photoUrl;
  final double size;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final Color base = color ?? Theme.of(context).colorScheme.primary;

    if (photoUrl != null && photoUrl!.isNotEmpty) {
      return ClipOval(
        child: RemoteImage(url: photoUrl, height: size, width: size),
      );
    }

    return Container(
      height: size,
      width: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: base.withValues(alpha: 0.18),
      ),
      alignment: Alignment.center,
      child: Text(
        initials,
        style: TextStyle(
          color: base,
          fontWeight: FontWeight.w700,
          fontSize: size * 0.36,
        ),
      ),
    );
  }
}

/// Etiqueta de estado con color.
class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 5),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.16),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Text(
          label,
          style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600),
        ),
      );
}

/// Fila de una lista de datos (etiqueta a la izquierda, valor a la derecha).
class DetailRow extends StatelessWidget {
  const DetailRow({super.key, required this.label, required this.value, this.trailing});

  final String label;
  final String value;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Expanded(
            flex: 4,
            child: Text(
              label,
              style: TextStyle(
                color: theme.colorScheme.onSurface.withValues(alpha: 0.6),
                fontSize: 14,
              ),
            ),
          ),
          Expanded(
            flex: 6,
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
            ),
          ),
          if (trailing != null) ...<Widget>[const SizedBox(width: 8), trailing!],
        ],
      ),
    );
  }
}

/// Muestra un mensaje breve en la parte inferior.
void showMessage(BuildContext context, String message, {bool isError = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError
            ? const Color(0xFF7F1D1D)
            : Theme.of(context).colorScheme.surface,
        duration: Duration(seconds: isError ? 5 : 3),
      ),
    );
}
