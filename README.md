# Plataforma para barbería, peluquería y estética

Sistema completo de agendamiento con **web pública + panel de administración +
API + aplicación Android**, pensado para que el negocio lo configure entero
desde el panel **sin tocar código**.

```
┌──────────────────┐        ┌─────────────────────────────┐        ┌──────────────────┐
│  Web pública     │        │        Servidor PHP         │        │   App Android    │
│  responsive      │──────▶ │  ┌───────────────────────┐  │ ◀──────│    (Flutter)     │
│  + publicidad    │        │  │ Panel sin código      │  │        │  tema y textos   │
│  + reserva       │        │  │ 93 ajustes editables  │  │        │  desde el panel  │
└──────────────────┘        │  └───────────────────────┘  │        └──────────────────┘
                            │  API REST v1 · 50 tablas    │
                            └─────────────────────────────┘
```

## Qué incluye

**Para tus clientes**
- Página web adaptada a móvil con tus servicios, tu equipo, tu galería y tus opiniones.
- Reserva en cuatro pasos con los horarios libres **reales** de cada profesional.
- Elección de barbero, peluquero, estilista, manicurista o «sin preferencia».
- Campo libre **«Otro: especifica lo que necesitas»** para lo que no está en el catálogo.
- Pago en efectivo, con tarjeta en el local o por **transferencia**: se le muestran tus
  datos bancarios y sube el comprobante desde la galería o **tomándole una foto**.
- Cuenta con historial, puntos de fidelidad y código de referido.
- App Android con lo mismo, más recordatorios y promociones.

**Para ti (panel de administración)**
- **Publicidad**: anuncios en nueve ubicaciones —portada, franja, lateral, ventana al
  iniciar sesión, mientras navega, al intentar salir, bienvenida de la app, tarjeta del
  inicio y pantalla completa—, programados por fecha, día y hora, segmentados por público
  y dispositivo, con límite de vistas por persona y métricas de efectividad.
- **Todo configurable**: nombre, logo, colores, tipografías, textos de cada sección,
  horarios, reglas de reserva, moneda, zona horaria, enlace de descarga de la app y
  plantillas de correo. 93 ajustes, cero código.
- Agenda por profesional, alta de citas desde el mostrador, cobros y verificación de
  comprobantes con visor de imágenes.
- Campañas de correo a tus clientes con público segmentado y baja obligatoria.
- Informes de ingresos, horas pico, origen de las reservas y rendimiento del equipo.
- **Mantenimiento**: ver cuánto ocupa cada tabla, simular la limpieza, purgar de verdad
  lo eliminado, borrar archivos huérfanos y compactar las tablas para liberar espacio.

## Instalación rápida

```bash
# 1. Base de datos
mysql -u root -p -e "CREATE DATABASE estilo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Configuración
cd backend
cp .env.example .env
php cli/console.php key:generate    # pega las tres claves en el .env
nano .env                           # completa los datos de la base de datos

# 3. Instalación (migraciones + datos iniciales + tu cuenta)
php cli/console.php instalar --email=tu@correo.com

# 4. Comprobación
php cli/console.php diagnostico
```

Apunta el servidor web a `backend/public/` y entra en `https://tudominio.com/panel`.

Guía completa: **[docs/INSTALACION.md](docs/INSTALACION.md)**.

## Documentación

| Documento | Para qué |
|---|---|
| [INSTALACION.md](docs/INSTALACION.md) | Instalar en tu servidor paso a paso |
| [MANUAL_ADMIN.md](docs/MANUAL_ADMIN.md) | Usar el panel: publicidad, precios, cobros |
| [ARQUITECTURA.md](docs/ARQUITECTURA.md) | Cómo está construido y por qué |
| [SEGURIDAD.md](docs/SEGURIDAD.md) | Qué protege el sistema y cómo |
| [API.md](docs/API.md) | Endpoints para la app y para integraciones |
| [mobile/README.md](mobile/README.md) | Compilar y publicar la app Android |

## Requisitos

- PHP 8.2 o superior con `pdo_mysql`, `mbstring`, `gd`, `sodium`, `fileinfo`, `openssl`
- MySQL 8.0 o MariaDB 10.4 o superior
- Servidor web (Apache o Nginx) con el dominio apuntando a `backend/public/`
- Certificado HTTPS
- Flutter 3.27 o superior, solo si vas a compilar la app

## Estructura

```
backend/
├── public/          Única carpeta expuesta a internet
├── app/
│   ├── Core/        Enrutador, base de datos, vistas, validación
│   ├── Security/    Contraseñas, cifrado, tokens, subidas, auditoría
│   ├── Services/    Reglas del negocio: reservas, pagos, publicidad, limpieza
│   ├── Controllers/ Web pública · panel · API
│   └── Views/       Plantillas
├── config/          Configuración de arranque (lo demás vive en el panel)
├── database/        50 tablas en 6 migraciones + datos iniciales
├── storage/         Subidas, registros y sesiones (fuera de internet)
├── cli/console.php  Instalación, tareas programadas y mantenimiento
└── tests/           90 pruebas del núcleo + 27 de la API

mobile/              Aplicación Flutter para Android
docs/                Documentación
```

## Tarea programada

Una sola línea deja el sistema al día: envía los recordatorios, lanza las campañas,
marca las ausencias y hace la limpieza nocturna.

```cron
*/5 * * * * php /ruta/al/proyecto/backend/cli/console.php cron >> /dev/null 2>&1
```

## Pruebas

```bash
php backend/tests/run.php        # 90 pruebas del núcleo y la seguridad
bash backend/tests/api_test.sh   # 27 pruebas de la API (con el servidor levantado)
cd mobile && flutter test        # pruebas de los modelos de la app
```

## Licencia

MIT.
