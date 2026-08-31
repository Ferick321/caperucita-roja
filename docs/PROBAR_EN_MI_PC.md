# Probar el sistema en tu computadora

Guía paso a paso para verlo funcionando en tu PC antes de subirlo a internet.
Está escrita para Windows con **XAMPP** y **Visual Studio Code**. Si usas Mac o
Linux, los comandos son los mismos cambiando las rutas.

Tiempo estimado: **15 minutos**.

---

## Lo que necesitas instalar

| Programa | Para qué | Dónde |
|---|---|---|
| **XAMPP** (PHP 8.2+) | Servidor web y base de datos | https://www.apachefriends.org |
| **Visual Studio Code** | Editar el código | https://code.visualstudio.com |
| **Git** | Descargar el proyecto | https://git-scm.com/download/win |

> Al instalar XAMPP te bastan **Apache** y **MySQL**. Comprueba que trae PHP 8.2
> o superior: abre `C:\xampp\php\php.exe -v` en una terminal.

---

## Paso 1 — Descargar el proyecto

Abre **PowerShell** o la terminal de Windows:

```powershell
cd C:\xampp\htdocs
git clone https://github.com/Ferick321/caperucita-roja.git mibarberia
cd mibarberia
```

Si prefieres no usar Git, descarga el ZIP desde GitHub y descomprímelo en
`C:\xampp\htdocs\mibarberia`.

---

## Paso 2 — Abrirlo en Visual Studio Code

```powershell
code .
```

O bien: **Visual Studio Code → Archivo → Abrir carpeta →** `C:\xampp\htdocs\mibarberia`.

**Extensiones recomendadas** (icono de bloques en la barra izquierda):

- **PHP Intelephense** — autocompletado de PHP
- **Flutter** y **Dart** — solo si vas a tocar la app móvil
- **MySQL** (de Weijan Chen) — ver la base de datos sin salir del editor

---

## Paso 3 — Encender Apache y MySQL

Abre el **Panel de control de XAMPP** y pulsa **Start** en:

- ✅ **Apache**
- ✅ **MySQL**

Los dos deben quedar en verde.

> **Si Apache no arranca**, casi siempre es que el puerto 80 está ocupado (Skype,
> IIS o Windows). Pulsa **Config → httpd.conf**, cambia `Listen 80` por
> `Listen 8080` y `ServerName localhost:80` por `ServerName localhost:8080`.
> A partir de ahí, entra por `http://localhost:8080` en lugar de `http://localhost`.

---

## Paso 4 — Crear la base de datos

Entra en **http://localhost/phpmyadmin**.

1. Pulsa **Nueva** en la columna izquierda.
2. Nombre: `estilo`
3. Cotejamiento: **`utf8mb4_unicode_ci`** (búscalo en la lista, es importante
   para que se guarden bien las tildes y las eñes).
4. Pulsa **Crear**.

Ahora importa los datos:

5. Con la base `estilo` seleccionada, pulsa la pestaña **Importar**.
6. **Seleccionar archivo** → busca
   `C:\xampp\htdocs\mibarberia\backend\database\exportacion\estilo_base_de_datos.sql`
7. Pulsa **Continuar** (abajo del todo).

Debe salir «La importación se ejecutó correctamente». Verás **51 tablas** en la
columna izquierda.

---

## Paso 5 — Configurar el sistema

En Visual Studio Code, abre la terminal integrada: **Terminal → Nueva terminal**
(o `Ctrl + Ñ`).

```powershell
cd backend
copy .env.example .env
```

Genera tus claves de seguridad:

```powershell
C:\xampp\php\php.exe cli\console.php key:generate
```

Te imprime tres líneas parecidas a estas:

```
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
JWT_SECRET=yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy
PASSWORD_PEPPER=zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz
```

**Cópialas.** Ahora abre el archivo `backend/.env` en Visual Studio Code y déjalo
así (pega tus tres claves donde corresponde):

```ini
APP_NAME="Mi Barbería"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/mibarberia/backend/public

APP_KEY=base64:pega-aqui-tu-clave
JWT_SECRET=pega-aqui-tu-secreto
PASSWORD_PEPPER=pega-aqui-tu-pepper

FORCE_HTTPS=false
SESSION_FORCE_SECURE=false
LOG_LEVEL=debug

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=estilo
DB_USERNAME=root
DB_PASSWORD=

MAIL_TRANSPORT=log
```

> `DB_USERNAME=root` y `DB_PASSWORD=` vacío son los valores por defecto de XAMPP.
> `MAIL_TRANSPORT=log` hace que los correos se escriban en
> `backend/storage/logs/` en lugar de enviarse: perfecto para probar.
>
> `APP_DEBUG=true` está bien **solo mientras pruebas en tu PC**. Cuando lo subas
> a internet tiene que ser `false`.

**Guarda el archivo** (`Ctrl + S`).

---

## Paso 6 — Poner tu contraseña de administrador

La contraseña que viene en el `.sql` no te sirve: está cifrada con una clave
distinta de la tuya. Créala ahora:

```powershell
C:\xampp\php\php.exe cli\console.php usuario:clave --email=admin@mibarberia.com --password=MiClave#2026
```

Debe responder: `Contrasena actualizada.`

---

## Paso 7 — Comprobar que todo está bien

```powershell
C:\xampp\php\php.exe cli\console.php diagnostico
```

Deberías ver algo así:

```
  [OK] PHP 8.2 o superior
  [OK] Extension pdo_mysql
  [OK] Extension gd (imagenes)
  [OK] Extension sodium (cifrado)
  [OK] APP_KEY configurada
  [OK] Conexion a la base de datos
  ...
```

Es normal que salgan en `!!` estas dos mientras pruebas en local:
`Modo depuracion apagado` y `HTTPS forzado`. **Todo lo demás debe estar en `[OK]`.**

> **Si falla `Extension sodium` o `Extension gd`**: abre `C:\xampp\php\php.ini`,
> busca `;extension=sodium` y `;extension=gd`, quítales el punto y coma del
> principio, guarda y reinicia Apache desde el panel de XAMPP.

---

## Paso 8 — Abrirlo en el navegador

Entra en:

**http://localhost/mibarberia/backend/public**

Deberías ver la página web con el catálogo de servicios de ejemplo.

Y el panel en:

**http://localhost/mibarberia/backend/public/panel**

- Usuario: `admin@mibarberia.com`
- Contraseña: la que pusiste en el paso 6

---

### Alternativa más limpia: servidor de PHP directo

Si prefieres una URL corta (`http://localhost:8000`) y no depender de Apache:

```powershell
cd C:\xampp\htdocs\mibarberia\backend
C:\xampp\php\php.exe -S localhost:8000 -t public public/index.php
```

Cambia en el `.env`: `APP_URL=http://localhost:8000` y abre
**http://localhost:8000**. Deja esa terminal abierta mientras pruebas.

---

## Paso 9 — Recorrido de prueba

Sigue este guion para comprobar que todo funciona de punta a punta.

### A. El panel

1. Entra en `/panel`. Verás el **resumen** con la lista «Termina de configurar
   tu sistema».
2. **Ajustes → Negocio**: cambia el nombre por el de tu barbería, pon tu teléfono
   y tu dirección. **Guardar**.
3. **Ajustes → Apariencia**: cambia el «Color principal» por otro (prueba con
   `#e11d48`, un rojo). **Guardar**.
4. Abre la web pública en otra pestaña y **recarga**: los botones y los acentos
   ya salen del color nuevo. *Eso es «configurable sin tocar código».*

### B. Publicidad

5. **Publicidad → + Nuevo anuncio**.
6. Rellena:
   - Nombre interno: `Promo de prueba`
   - Título: `20% de descuento esta semana`
   - Subtítulo: `Solo hasta el domingo`
   - Texto del botón: `Reservar ahora`
   - Enlace del botón: `/agendar`
7. En **«Donde se muestra»** marca **Portada principal** y **Ventana mientras navega**.
8. En **«Frecuencia»** pon *Retraso antes de aparecer* = `5` segundos.
9. **Guardar**.
10. Ve a la web pública, recarga y espera 5 segundos: aparece la ventana.
    Ciérrala y recarga: **no vuelve a salir**, porque el sistema recuerda que la
    cerraste.
11. Vuelve a **Publicidad**: ya verás las **vistas** contabilizadas.

### C. Cuenta bancaria

12. **Cuentas y cobros → Añadir una cuenta**. Pon un banco, un número inventado
    (por ejemplo `2200123456789`), tu nombre y tu cédula. **Guardar**.
13. Comprueba que quedó cifrada: en phpMyAdmin abre la tabla `bank_accounts`.
    La columna `account_number_enc` es un texto ilegible que empieza por `enc.v1:`
    — el número real no está en claro en la base de datos.

### D. Reservar como cliente

14. Ve a la web pública → **Agendar cita**.
15. Elige un servicio: aparecen los días con hueco y los horarios reales.
16. Elige día y hora, rellena nombre y teléfono, marca **Transferencia bancaria**
    y confirma.
17. En la pantalla de confirmación **ya te salen los datos bancarios** que
    cargaste en el paso 12.

### E. Verificar el pago

18. Vuelve al panel: en **Citas** está la cita nueva; en **Pagos** verás el pago
    esperando verificación.
19. Prueba a **Aprobar**: la cita pasa automáticamente a *Confirmada*.

### F. Que los correos «se envían»

20. Abre `backend/storage/logs/app-2026-XX-XX.log` en Visual Studio Code.
    Verás la línea `Correo simulado (transporte "log")` con el mensaje que
    habría recibido el cliente. Cuando configures SMTP de verdad, saldrán por
    correo real.

### G. Mantenimiento

21. **Mantenimiento**: ves cuánto ocupa cada tabla.
22. **Simular limpieza**: te muestra qué se borraría **sin borrar nada**.
23. Si quieres, vuelve atrás y ejecuta la limpieza escribiendo `LIMPIAR`.

---

## Paso 10 — Las pruebas automáticas

Para comprobar que el núcleo y la seguridad funcionan:

```powershell
cd C:\xampp\htdocs\mibarberia\backend
C:\xampp\php\php.exe tests\run.php
```

Termina con `RESULTADO: 90 correctas, 0 fallidas`.

Prueban las contraseñas, el cifrado de los datos bancarios, los tokens de la
app (incluido el intento de falsificarlos), la protección contra inyección SQL,
el escape de las páginas y las redirecciones.

---

## Paso 11 — Probar la app móvil (opcional)

Solo si quieres ver la app. Necesitas **Flutter 3.27+** instalado.

```powershell
cd C:\xampp\htdocs\mibarberia\mobile
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

> `10.0.2.2` es la dirección con la que el emulador de Android ve tu PC.
> Si usas un teléfono real conectado por USB, pon la IP de tu computadora
> (la ves con `ipconfig`), por ejemplo `http://192.168.1.50:8000`.
>
> Para que funcione sin HTTPS en pruebas, abre
> `mobile/android/app/src/main/res/xml/network_security_config.xml` y quita los
> `<!--` y `-->` del bloque `domain-config`. **Vuelve a ponerlos antes de publicar.**

Verás que la app toma **tu logo, tus colores y tus textos** del panel.

---

## Problemas frecuentes

**«No se pudo conectar con la base de datos»**
MySQL no está encendido en XAMPP, o el `.env` tiene mal `DB_DATABASE`,
`DB_USERNAME` o `DB_PASSWORD`. Comprueba con `php cli\console.php diagnostico`.

**Página en blanco o error 500**
Con `APP_DEBUG=true` el error sale en pantalla. Si no, míralo en
`backend/storage/logs/app-AAAA-MM-DD.log`.

**Los estilos no cargan (la web se ve sin diseño)**
Falta activar `mod_rewrite` en Apache: abre `C:\xampp\apache\conf\httpd.conf`,
busca `#LoadModule rewrite_module`, quítale la `#`, guarda y reinicia Apache.

**«No hay horarios disponibles»**
El profesional no tiene horario. **Equipo → editar → Horario semanal** y marca
sus días. (Los tres de ejemplo ya vienen con horario de lunes a sábado.)

**No se suben las imágenes**
Abre `C:\xampp\php\php.ini` y sube estos valores:
```ini
upload_max_filesize = 8M
post_max_size = 8M
```
Reinicia Apache.

**«Call to undefined function sodium_crypto_secretbox»**
Falta activar la extensión: en `php.ini` quita el `;` de `;extension=sodium`,
guarda y reinicia Apache.

---

## Cuando esté listo para internet

Cuando termines de probar y quieras subirlo a tu hosting, sigue
**[INSTALACION.md](INSTALACION.md)**. Los cambios principales son:

- `APP_ENV=production` y **`APP_DEBUG=false`**
- `FORCE_HTTPS=true` y `SESSION_FORCE_SECURE=true`
- `APP_URL` con tu dominio real
- `MAIL_TRANSPORT=smtp` con los datos de tu correo
- Generar **claves nuevas** con `key:generate` (las de pruebas no se reutilizan)
- La raíz del dominio apuntando a `backend/public/`
- Programar la tarea del cron
