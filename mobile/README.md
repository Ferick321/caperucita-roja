# Aplicación móvil

Aplicación Android (Flutter) para que tus clientes agenden, paguen y sigan sus
citas. Toda la marca —nombre, logo, colores, textos y reglas de reserva— llega
desde el panel del servidor: **cambiar un ajuste en el panel cambia la app sin
publicar una versión nueva en la tienda**.

## Requisitos

- Flutter 3.27 o superior (`flutter --version`)
- Android Studio o el SDK de Android con `platform-tools`
- El backend ya instalado y accesible por HTTPS

## Puesta en marcha

```bash
cd mobile
flutter pub get
```

Apunta la app a tu servidor. Hay dos formas:

**1. Al compilar (recomendado):**

```bash
flutter run --dart-define=API_BASE_URL=https://mibarberia.com
```

**2. Fijándolo en el código:** edita `defaultBaseUrl` en
`lib/core/config/api_config.dart`.

## Personalizar antes de publicar

| Qué | Dónde |
|---|---|
| Nombre de la app | `android/app/src/main/res/values/strings.xml` |
| Identificador del paquete | `applicationId` y `namespace` en `android/app/build.gradle` |
| Icono | `android/app/src/main/res/mipmap-*/ic_launcher.png` |
| Dirección del servidor | `--dart-define=API_BASE_URL=...` o `api_config.dart` |

El resto (colores, logo, textos, servicios, precios, horarios, publicidad) se
configura desde el panel web y la app lo toma automáticamente.

## Compilar para publicar

```bash
# 1. Genera tu almacén de claves una sola vez
keytool -genkey -v -keystore ~/mi-barberia.jks -keyalg RSA \
        -keysize 2048 -validity 10000 -alias mibarberia

# 2. Copia android/key.properties.example a android/key.properties y complétalo

# 3. APK para instalar directamente (el enlace se publica desde el panel)
flutter build apk --release --dart-define=API_BASE_URL=https://mibarberia.com

# 4. Paquete para Google Play
flutter build appbundle --release --dart-define=API_BASE_URL=https://mibarberia.com
```

El APK queda en `build/app/outputs/flutter-apk/app-release.apk`. Súbelo a tu
servidor y pega su enlace en **Panel → Ajustes → App móvil → Enlace directo al
APK**; el botón de descarga de la web lo usará automáticamente.

## Cómo está organizado

```
lib/
├── main.dart                    Arranque y tema dinámico
├── core/
│   ├── api/                     Cliente HTTP con refresco automático de sesión
│   ├── config/                  Dirección del servidor
│   ├── services/                Configuración, sesión, catálogo, citas, pagos, anuncios
│   ├── storage/                 Tokens en el almacén cifrado del sistema
│   └── theme/                   Construye el tema con los colores del panel
├── models/                      Configuración remota, catálogo, citas, perfil
├── features/
│   ├── auth/                    Acceso y registro
│   ├── home/                    Bienvenida, inicio y navegación
│   ├── booking/                 Reserva en cuatro pasos
│   ├── appointments/            Mis citas y detalle
│   ├── payments/                Datos bancarios y envío del comprobante
│   └── profile/                 Perfil, puntos y preferencias
└── widgets/                     Componentes compartidos y publicidad
```

## Seguridad

- Los tokens se guardan en el **Keystore de Android**, nunca en texto plano.
- El token de acceso dura 15 minutos y se renueva solo; el de refresco **rota
  en cada uso**: si uno ya usado reaparece, el servidor cierra todas las
  sesiones de esa cuenta.
- Solo se permite **tráfico cifrado** (`network_security_config.xml`).
- Las sesiones quedan **excluidas de las copias de seguridad** de Android.
- La cámara solo se usa para el comprobante de pago, y la imagen se reduce en
  el teléfono antes de enviarla.
- Los enlaces de anuncios y botones se abren solo si son `http`/`https`.

## Problemas frecuentes

**"No pudimos conectar"** — comprueba que `API_BASE_URL` apunta al dominio
correcto y que responde: `curl https://tudominio.com/api/v1/config`.

**Probando contra un servidor local** — usa `http://10.0.2.2:8080` en el
emulador y descomenta el bloque `domain-config` de
`network_security_config.xml`. Vuelve a comentarlo antes de publicar.

**La app pide actualizarse** — la versión mínima admitida se fija en
**Panel → Ajustes → App móvil**.
