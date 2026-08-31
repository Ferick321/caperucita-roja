# Guía de instalación

Esta guía te lleva desde un servidor vacío hasta el sistema funcionando.
No necesitas saber programar; sí necesitas acceso a tu hosting.

---

## 1. Comprueba que tu servidor sirve

Necesitas:

- **PHP 8.2 o superior** con estas extensiones: `pdo_mysql`, `mbstring`, `gd`,
  `sodium`, `fileinfo`, `openssl`, `intl`
- **MySQL 8.0** o **MariaDB 10.4** o superior
- **HTTPS** (casi todos los hostings lo dan gratis con Let's Encrypt)
- Poder apuntar el dominio a una carpeta concreta (`backend/public/`)

Para comprobar la versión de PHP en un hosting compartido, crea un archivo
`info.php` con `<?php phpinfo();`, ábrelo en el navegador y **bórralo después**.

---

## 2. Sube los archivos

**Por Git (recomendado):**

```bash
cd /var/www
git clone <url-del-repositorio> mibarberia
cd mibarberia
```

**Por FTP:** sube todo el proyecto a tu carpeta, por ejemplo
`/home/usuario/mibarberia/`.

---

## 3. Crea la base de datos

Desde el panel de tu hosting (cPanel, Plesk) o por línea de comandos:

```sql
CREATE DATABASE estilo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'estilo_app'@'localhost' IDENTIFIED BY 'UNA-CLAVE-LARGA-Y-UNICA';
GRANT ALL PRIVILEGES ON estilo.* TO 'estilo_app'@'localhost';
FLUSH PRIVILEGES;
```

Apunta el nombre de la base, el usuario y la contraseña: los necesitas ahora.

---

## 4. Configura el entorno

```bash
cd backend
cp .env.example .env
```

Genera las claves de seguridad:

```bash
php cli/console.php key:generate
```

Te imprime tres líneas. **Cópialas en tu `.env`**:

```
APP_KEY=base64:...
JWT_SECRET=...
PASSWORD_PEPPER=...
```

> **Importante.** Estas claves se generan **una sola vez**.
> Cambiar `PASSWORD_PEPPER` invalida todas las contraseñas existentes.
> Cambiar `APP_KEY` impide descifrar los datos bancarios ya guardados.
> Guárdalas en un lugar seguro junto con tus copias de seguridad.

Ahora edita el resto del `.env`:

```ini
APP_NAME="Mi Barbería"
APP_URL=https://mibarberia.com
APP_ENV=production
APP_DEBUG=false          # NUNCA true en producción
FORCE_HTTPS=true

DB_HOST=127.0.0.1
DB_DATABASE=estilo
DB_USERNAME=estilo_app
DB_PASSWORD=la-clave-que-creaste

MAIL_TRANSPORT=smtp
MAIL_HOST=smtp.tuproveedor.com
MAIL_PORT=587
MAIL_USERNAME=no-reply@mibarberia.com
MAIL_PASSWORD=la-clave-del-correo
MAIL_FROM_ADDRESS=no-reply@mibarberia.com
MAIL_FROM_NAME="Mi Barbería"
```

---

## 5. Instala

```bash
php cli/console.php instalar --email=tu@correo.com
```

Te pide una contraseña para tu cuenta de administrador. El comando:

1. crea las 50 tablas,
2. carga los 93 ajustes por defecto,
3. crea un catálogo de ejemplo (barbería, peluquería, manicure, pedicure, estética),
4. crea tres profesionales con horario de lunes a sábado,
5. crea los métodos de pago (efectivo, transferencia, tarjeta),
6. crea las secciones de la web, las plantillas de correo y las políticas de limpieza,
7. crea tu cuenta de super administrador.

Comprueba que todo está bien:

```bash
php cli/console.php diagnostico
```

Deben salir todos `[OK]`. Si algo falla, el propio mensaje dice qué corregir.

---

## 6. Configura el servidor web

La raíz del dominio debe apuntar a **`backend/public/`**, nunca a la carpeta del
proyecto. Así el código, la configuración y los archivos subidos quedan fuera
del alcance de internet.

### Apache

```apache
<VirtualHost *:443>
    ServerName mibarberia.com
    DocumentRoot /var/www/mibarberia/backend/public

    <Directory /var/www/mibarberia/backend/public>
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile /ruta/al/certificado.pem
    SSLCertificateKeyFile /ruta/a/la/clave.pem

    ErrorLog  ${APACHE_LOG_DIR}/mibarberia-error.log
    CustomLog ${APACHE_LOG_DIR}/mibarberia-access.log combined
</VirtualHost>

<VirtualHost *:80>
    ServerName mibarberia.com
    Redirect permanent / https://mibarberia.com/
</VirtualHost>
```

Necesitas `mod_rewrite` activo. El archivo `.htaccess` ya viene incluido.

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name mibarberia.com;
    root /var/www/mibarberia/backend/public;
    index index.php;

    ssl_certificate     /ruta/al/certificado.pem;
    ssl_certificate_key /ruta/a/la/clave.pem;

    client_max_body_size 8M;   # margen para los comprobantes

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Nada de esto debe servirse nunca
    location ~ /\.(env|git) { deny all; }
    location ~ /(storage|app|config|database|cli|vendor)/ { deny all; }
}

server {
    listen 80;
    server_name mibarberia.com;
    return 301 https://$server_name$request_uri;
}
```

### Si tu hosting no deja mover la raíz del dominio

El archivo `backend/.htaccess` redirige todo a `public/` automáticamente. Sube
el contenido de `backend/` a tu `public_html` y funcionará, aunque es más seguro
mover la raíz cuando se pueda.

---

## 7. Permisos de carpetas

```bash
cd /var/www/mibarberia/backend
chown -R www-data:www-data storage
chmod -R 750 storage
chmod 640 .env
```

Sustituye `www-data` por el usuario con el que corre tu servidor web
(`apache`, `nginx` o tu usuario en hosting compartido).

---

## 8. Programa la tarea automática

Una línea deja el sistema al día: envía recordatorios, lanza campañas, marca
ausencias y limpia la base cada noche.

```bash
crontab -e
```

Añade:

```cron
*/5 * * * * php /var/www/mibarberia/backend/cli/console.php cron >> /dev/null 2>&1
```

En cPanel: **Cron Jobs → cada 5 minutos** con ese mismo comando.

---

## 9. Personaliza tu negocio

Entra en `https://mibarberia.com/panel` con tu cuenta y sigue la lista de
**«Termina de configurar tu sistema»** que aparece en el resumen:

1. **Ajustes → Negocio**: nombre, teléfono, dirección, moneda, zona horaria.
2. **Ajustes → Apariencia**: sube el logo y elige tus colores.
3. **Servicios**: ajusta precios y duraciones, borra los de ejemplo.
4. **Equipo**: pon a tu gente real y **define su horario** (sin horario no hay citas).
5. **Cuentas y cobros**: carga tus cuentas bancarias para las transferencias.
6. **Publicidad**: crea tu primer anuncio.
7. **Página web**: cambia los textos de la portada.

Todo esto se hace con formularios. No hay que tocar ningún archivo.

---

## 10. Publica la app Android

Sigue **[mobile/README.md](../mobile/README.md)**. Cuando tengas el APK, súbelo
a tu servidor y pega su enlace en **Ajustes → App móvil → Enlace directo al
APK**. El botón «Descargar la app» de la web lo usará automáticamente.

---

## Copias de seguridad

```bash
#!/bin/bash
# guarda como /usr/local/bin/respaldo-barberia.sh
FECHA=$(date +%Y%m%d)
DESTINO=/var/backups/mibarberia

mkdir -p "$DESTINO"

# Base de datos
mysqldump -u estilo_app -p'TU-CLAVE' estilo | gzip > "$DESTINO/bd-$FECHA.sql.gz"

# Archivos subidos y configuración
tar czf "$DESTINO/archivos-$FECHA.tar.gz" \
    -C /var/www/mibarberia/backend storage/uploads .env

# Conserva 30 días
find "$DESTINO" -name '*.gz' -mtime +30 -delete
```

```cron
0 3 * * * /usr/local/bin/respaldo-barberia.sh
```

> Guarda una copia **fuera del servidor**. Un respaldo que vive en la misma
> máquina no te salva si la máquina se pierde.

---

## Actualizar el sistema

```bash
cd /var/www/mibarberia
git pull
cd backend
php cli/console.php migrate     # aplica solo lo nuevo
php cli/console.php diagnostico
```

Las migraciones ya aplicadas no se repiten y tus datos no se tocan.

---

## Problemas frecuentes

**«No se pudo conectar con la base de datos»**
Revisa `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` en el `.env`.
En algunos hostings el host no es `127.0.0.1` sino `localhost` o una dirección
propia. Comprueba con `php cli/console.php diagnostico`.

**Página en blanco o error 500**
Mira `backend/storage/logs/app-AAAA-MM-DD.log`: ahí está el motivo real.
Pon `APP_DEBUG=true` un momento para verlo en pantalla y **vuelve a ponerlo en
false** al terminar.

**«No hay horarios disponibles»**
Casi siempre es que el profesional no tiene horario definido.
Ve a **Equipo → editar → Horario semanal** y marca sus días.

**No llegan los correos**
Comprueba `MAIL_*` en el `.env`. Con `MAIL_TRANSPORT=log` los correos solo se
escriben en el registro y no salen. Prueba el envío desde
**Mantenimiento → Procesar ahora** y revisa el log.

**Las imágenes no se ven**
Comprueba que `storage/uploads` existe y que el servidor web puede escribir en
ella (`chown` y `chmod 750`).

**«El archivo es demasiado grande» al subir un comprobante**
Sube `upload_max_filesize` y `post_max_size` en tu PHP (8M es suficiente) y
`client_max_body_size` en Nginx.
