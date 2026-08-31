# API REST v1

Interfaz que consume la aplicación Android y que puedes usar para integraciones
propias.

**Base:** `https://tudominio.com/api/v1`

---

## Convenciones

Toda respuesta correcta:

```json
{ "ok": true, "data": { ... } }
```

Los listados paginados añaden `meta`:

```json
{ "ok": true, "data": [ ... ], "meta": { "total": 42, "page": 1, "per_page": 20, "pages": 3 } }
```

Todo error:

```json
{ "ok": false, "error": { "message": "Correo o contraseña incorrectos.", "code": 401 } }
```

Los errores de validación (422) añaden el detalle por campo:

```json
{
  "ok": false,
  "error": {
    "message": "Correo no es un correo valido.",
    "code": 422,
    "details": { "email": ["Correo no es un correo valido."] }
  }
}
```

Los mensajes vienen **ya redactados en español**: puedes mostrarlos tal cual.

### Cabeceras

| Cabecera | Cuándo | Para qué |
|---|---|---|
| `Content-Type: application/json` | En POST y PUT | Cuerpo en JSON |
| `Authorization: Bearer <token>` | Rutas privadas | Token de acceso |
| `X-App-Version: 1.2.0` | Recomendada | Permite exigir actualización |

### Códigos

| Código | Significado |
|---|---|
| 200 / 201 | Correcto |
| 401 | Falta el token, es inválido o caducó |
| 403 | Sin permiso |
| 404 | No existe o no es tuyo |
| 409 | Conflicto: el horario acaba de ocuparse, el correo ya existe |
| 422 | Datos inválidos (mira `details`) |
| 426 | La app es demasiado antigua |
| 429 | Demasiadas peticiones |
| 503 | Modo mantenimiento |

Las fechas viajan en **UTC** con formato `AAAA-MM-DD HH:MM:SS`. Los campos que
acaban en `_local` vienen ya convertidos a la zona horaria del negocio.

---

## Configuración

### `GET /config`

Público. Es lo primero que pide la app: devuelve marca, colores, textos y reglas.
**Cambiar un ajuste en el panel cambia la app sin publicar una versión nueva.**

```json
{
  "ok": true,
  "data": {
    "business": { "name": "Mi Barbería", "currency_symbol": "$", "timezone": "America/Guayaquil", "...": "" },
    "theme":    { "primary_color": "#c9a227", "dark_mode": true, "rounded_corners": 16, "...": "" },
    "booking":  { "enabled": true, "min_hours_before": 2, "cancellation_hours": 4, "...": "" },
    "payments": { "enabled": true, "transfer_instructions": "..." },
    "loyalty":  { "enabled": true, "points_to_currency": 100 },
    "ads":      { "enabled": true, "show_splash": true },
    "app":      { "latest_version": "1.2.0", "update_required": false, "download_url": "..." },
    "maintenance": { "active": false, "message": "..." },
    "branches": [ { "id": 1, "name": "Local principal", "...": "" } ]
  }
}
```

### `GET /sucursales/{id}/horarios`

Horario semanal de una sucursal.

---

## Catálogo (público)

| Endpoint | Devuelve |
|---|---|
| `GET /categorias` | Categorías activas |
| `GET /servicios` | Servicios reservables. Filtros: `category_id`, `q`, `featured=1` |
| `GET /servicios/{id}` | Detalle con los profesionales que lo prestan |
| `GET /profesionales` | Equipo. Filtro: `branch_id` |
| `GET /galeria` | Fotos publicadas |
| `GET /resenas` | Opiniones aprobadas |
| `GET /preguntas` | Preguntas frecuentes |

---

## Disponibilidad

### `GET /disponibilidad`

Público. Sin `date` devuelve los días con hueco; con `date` los horarios de ese día.

| Parámetro | Obligatorio | Descripción |
|---|---|---|
| `service_ids[]` | sí | Uno o varios servicios |
| `branch_id` | no | Por defecto, la sucursal principal |
| `staff_id` | no | Omitir para «sin preferencia» |
| `date` | no | `AAAA-MM-DD` |

```bash
# Días con hueco
curl "https://tudominio.com/api/v1/disponibilidad?service_ids[]=1&service_ids[]=3"

# Horarios de un día
curl "https://tudominio.com/api/v1/disponibilidad?service_ids[]=1&date=2026-09-15"
```

```json
{
  "ok": true,
  "data": {
    "date": "2026-09-15",
    "duration_minutes": 35,
    "slots": [
      { "time": "09:00", "label": "09:00", "staff_ids": [2, 3] },
      { "time": "09:15", "label": "09:15", "staff_ids": [2] }
    ]
  }
}
```

`staff_ids` dice qué profesionales están libres en ese hueco.

---

## Acceso

### `POST /auth/registro`

```json
{
  "first_name": "Ana",
  "last_name": "Pérez",
  "email": "ana@ejemplo.com",
  "phone": "0999123456",
  "password": "MiClaveSegura#2026",
  "accepts_terms": true,
  "accepts_marketing": true,
  "device_id": "abc123",
  "platform": "android"
}
```

Devuelve el par de tokens y el perfil. Errores: `409` correo ya registrado,
`422` datos inválidos.

### `POST /auth/login`

```json
{ "email": "ana@ejemplo.com", "password": "MiClaveSegura#2026" }
```

```json
{
  "ok": true,
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "a1b2c3...",
    "token_type": "Bearer",
    "expires_in": 900,
    "user": { "id": 42, "first_name": "Ana", "loyalty_points": 150, "...": "" }
  }
}
```

### `POST /auth/refrescar`

```json
{ "refresh_token": "a1b2c3..." }
```

> **El token de refresco rota en cada uso.** Cada llamada devuelve uno nuevo y
> revoca el anterior. Guarda siempre el nuevo.
>
> Si envías un token ya usado, el sistema asume que fue robado y **cierra todas
> las sesiones de esa cuenta**, devolviendo `401`.

### Otros

| Endpoint | Para qué |
|---|---|
| `POST /auth/salir` | Revoca el refresco y desactiva el dispositivo |
| `POST /auth/salir-todo` | Cierra la sesión en todos los dispositivos |
| `POST /auth/recuperar` | Envía el enlace de restablecimiento |
| `POST /dispositivos` | Registra el dispositivo para notificaciones |

---

## Citas (requiere sesión)

### `GET /citas`

`scope=upcoming` (por defecto) o `scope=past`. Paginado.

### `GET /citas/{id}`

Detalle con los pagos y sus comprobantes.

### `POST /citas`

```json
{
  "branch_id": 1,
  "service_ids": [1, 3],
  "date": "2026-09-15",
  "time": "14:30",
  "staff_id": 2,
  "notes": "Prefiero el degradado bajo",
  "custom_request": "",
  "coupon_code": "BIENVENIDA"
}
```

Devuelve `201` con la cita creada. **`409`** si el horario acaba de ocuparse:
vuelve a pedir la disponibilidad y ofrece otro hueco.

### Otros

| Endpoint | Notas |
|---|---|
| `POST /citas/{id}/cancelar` | Respeta la antelación mínima configurada |
| `POST /citas/{id}/reprogramar` | `date`, `time`, `staff_id` opcional |
| `POST /citas/{id}/resena` | `rating` 1-5. Solo en citas completadas |

---

## Pagos (requiere sesión)

### `GET /pagos/metodos`

```json
{
  "ok": true,
  "data": [
    { "id": 1, "code": "efectivo", "name": "Efectivo", "requires_proof": false, "shows_bank_accounts": false },
    { "id": 2, "code": "transferencia", "name": "Transferencia bancaria", "requires_proof": true, "shows_bank_accounts": true }
  ]
}
```

Usa `shows_bank_accounts` para decidir si pides los datos bancarios, y
`requires_proof` para exigir el comprobante.

### `GET /pagos/cuentas`

Datos bancarios **completos**. Solo se entregan a un cliente autenticado.

```json
{
  "ok": true,
  "data": {
    "instructions": "Realiza la transferencia y sube el comprobante...",
    "accounts": [
      {
        "id": 1,
        "bank_name": "Banco Pichincha",
        "account_type": "Ahorros",
        "account_number": "2200123456789",
        "holder_name": "Juan Pérez",
        "holder_document": "0912345678"
      }
    ]
  }
}
```

### `POST /pagos`

```json
{
  "appointment_id": 128,
  "payment_method_id": 2,
  "bank_account_id": 1,
  "amount": 25.00,
  "reference": "000123456",
  "transferred_at": "2026-09-14"
}
```

Devuelve `201` con el `id` del pago, que necesitas para subir el comprobante.

### `POST /pagos/{id}/comprobante`

Dos formas:

**Archivo (multipart/form-data)** — campo `proof`.

**Imagen en base64 (JSON)** — lo que envía la cámara del teléfono:

```json
{ "proof_base64": "iVBORw0KGgoAAAANSUhEUg...", "proof_name": "comprobante.jpg" }
```

Ambas pasan por los mismos controles: tipo real leído del contenido, tamaño
máximo, reconstrucción de la imagen con GD y almacenamiento fuera del directorio
público.

```json
{
  "ok": true,
  "data": {
    "message": "Comprobante recibido. Lo verificaremos en breve.",
    "proof": { "id": 7, "url": "https://.../media/comprobantes/2026/09/ab12...jpg" }
  }
}
```

---

## Perfil (requiere sesión)

| Endpoint | Para qué |
|---|---|
| `GET /perfil` | Datos, puntos, visitas y preferencias |
| `PUT /perfil` | Actualiza solo los campos que envíes |
| `POST /perfil/avatar` | Foto de perfil (multipart, campo `avatar`) |
| `POST /perfil/clave` | Cambia la contraseña. **Invalida todas las sesiones** |
| `POST /perfil/eliminar` | Derecho al olvido. Requiere `password` y `confirm: "ELIMINAR"` |
| `GET /fidelidad` | Puntos y su historial |

---

## Publicidad

### `GET /publicidad?placement=app_home_card`

Público. Ubicaciones: `app_splash`, `app_home_card`, `app_interstitial`.

El servidor decide qué anuncio corresponde a cada persona y cuántas veces puede
verlo; la app solo lo muestra.

### `POST /publicidad/evento`

```json
{ "banner_id": 5, "event": "impression", "placement": "app_home_card" }
```

Eventos: `impression`, `click`, `dismiss`. Devuelve `204`.

---

## Límites de peticiones

| Grupo | Límite |
|---|---|
| Configuración y catálogo | 120 / minuto |
| Disponibilidad | 180 / minuto |
| Inicio de sesión | 20 / 15 minutos |
| Registro | 10 / hora |
| Recuperar contraseña | 6 / hora |
| Crear cita | 20 / hora |
| Subir comprobante | 20 / hora |

Al superarlos llega un `429` con la cabecera `X-RateLimit-Remaining`.

---

## Ejemplo completo

```bash
BASE="https://tudominio.com/api/v1"

# 1. Configuración
curl -s "$BASE/config" | jq '.data.business.name'

# 2. Sesión
TOKEN=$(curl -s -X POST "$BASE/auth/login" \
  -H 'Content-Type: application/json' \
  -H 'X-App-Version: 1.0.0' \
  -d '{"email":"ana@ejemplo.com","password":"MiClaveSegura#2026"}' \
  | jq -r '.data.access_token')

# 3. Primer día y hora libres
DIA=$(curl -s "$BASE/disponibilidad?service_ids[]=1" | jq -r '.data.days[0].date')
HORA=$(curl -s "$BASE/disponibilidad?service_ids[]=1&date=$DIA" | jq -r '.data.slots[0].time')

# 4. Reservar
CITA=$(curl -s -X POST "$BASE/citas" \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d "{\"branch_id\":1,\"service_ids\":[1],\"date\":\"$DIA\",\"time\":\"$HORA\"}")

echo "$CITA" | jq '.data.code'

# 5. Datos bancarios y pago
curl -s "$BASE/pagos/cuentas" -H "Authorization: Bearer $TOKEN" | jq '.data.accounts[0]'
```
