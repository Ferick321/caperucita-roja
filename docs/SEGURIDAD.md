# Seguridad

Qué protege el sistema, cómo lo hace y qué te toca a ti.

---

## Contraseñas

Se guardan con **Argon2id** (bcrypt si el servidor no lo trae), el algoritmo
recomendado hoy, con parámetros configurables en el `.env`.

Además se aplica un **pepper**: un secreto que vive en el `.env` y **no** en la
base de datos. Si alguien roba un volcado de la tabla de usuarios, los hashes le
sirven de poco sin ese secreto. Se mezcla con HMAC-SHA256 para no chocar con el
límite de 72 bytes de bcrypt.

La política exige al menos 10 caracteres combinando tres de estas cuatro
familias: minúsculas, mayúsculas, números y símbolos. Además se rechazan las
contraseñas predecibles aunque cumplan el formato.

Cuando cambias los parámetros de coste, las contraseñas se rehashean solas en el
siguiente inicio de sesión, sin pedir nada al usuario.

---

## Acceso

**Limitación de intentos en dos niveles**: por dirección IP (20 intentos cada 15
minutos) y por cuenta (8 intentos). Así un atacante no puede probar miles de
contraseñas, ni bloquear a un cliente concreto desde muchas direcciones.

**Bloqueo temporal**: tras 10 fallos la cuenta queda bloqueada 30 minutos.

**Retardo progresivo**: cada fallo hace esperar un poco más, hasta 2 segundos.

**Mensajes idénticos**: «Correo o contraseña incorrectos» sale igual exista o no
la cuenta, y la comprobación tarda lo mismo. Así no se puede averiguar qué
correos están registrados.

**Verificación en dos pasos (TOTP)** compatible con Google Authenticator, Authy
y 1Password, con códigos de respaldo de un solo uso.

---

## Sesiones del panel y la web

- Cookie `HttpOnly` (JavaScript no puede leerla), `SameSite` y `Secure` bajo HTTPS.
- El identificador **se regenera** al iniciar sesión y cada 20 minutos.
- **Caducidad doble**: por inactividad (2 h) y absoluta (12 h).
- **Huella de navegador**: la sesión queda atada al agente de usuario y,
  opcionalmente, al bloque de red. Si la cookie se usa desde otro sitio, se anula.
- Los archivos de sesión viven **fuera del directorio público**.

---

## Sesiones de la app móvil

Token de acceso **JWT HS256 de 15 minutos** y token de refresco de 30 días.

El refresco **rota en cada uso**: cada renovación emite uno nuevo y revoca el
anterior. Si un token ya usado vuelve a aparecer, el sistema asume que fue
robado y **cierra todas las sesiones de esa cuenta**.

En la verificación del JWT solo se acepta el algoritmo `HS256`, lo que bloquea
el clásico ataque `alg: none`, y la firma se compara en tiempo constante.

Al cambiar la contraseña se marca la cuenta con `tokens_valid_after`, lo que
invalida al instante todos los tokens emitidos antes.

En el teléfono los tokens viven en el **Keystore de Android**, nunca en texto
plano, y quedan excluidos de las copias de seguridad del sistema.

---

## Inyección SQL

**Todas** las consultas usan sentencias preparadas con parámetros ligados, con la
emulación de PDO **desactivada** (`ATTR_EMULATE_PREPARES => false`), que es lo
que garantiza que el motor separe de verdad el código de los datos.

Los identificadores (tabla, columna, dirección de orden) no pueden ir como
parámetro, así que se validan contra la expresión `^[A-Za-z_][A-Za-z0-9_]*$` y se
citan. Un nombre de tabla como `users; DROP TABLE x` lanza una excepción antes de
llegar a la base de datos.

Los operadores de comparación se limitan a una lista cerrada. En las búsquedas,
los comodines `%` y `_` que escriba el usuario se escapan.

Un `DELETE` sin condiciones está bloqueado por código: es la clase de error que
borra una tabla entera por descuido.

---

## Cross-site scripting (XSS)

Todo lo que se imprime en una vista pasa por `e()`, que escapa para contexto
HTML. Los helpers `e_attr()`, `e_js()` y `e_url()` cubren atributos, bloques de
script y enlaces.

`e_url()` solo deja pasar `http`, `https`, `mailto` y `tel`: un `javascript:` o
un `data:text/html` se convierten en `#`.

Encima de eso hay una **política de contenido (CSP) con nonce**: cada respuesta
lleva un valor aleatorio y solo se ejecutan los `<script>` que lo llevan. Un
script inyectado no se ejecuta aunque logre colarse en el HTML.

Los textos legales y las campañas admiten formato básico, pero se limpian con
lista blanca de etiquetas y se eliminan los atributos de evento (`onclick`) y los
esquemas peligrosos.

---

## Cross-site request forgery (CSRF)

Doble comprobación en toda petición que modifique datos:

1. **Token sincronizado** en sesión, presente en cada formulario y comparado en
   tiempo constante.
2. **Verificación del origen** mediante las cabeceras `Origin` y `Referer`.

El token se rota al iniciar sesión.

---

## Subida de archivos

Es el punto por donde entra el código malicioso en la mayoría de sistemas web.
Aquí pasa por siete controles:

1. Se comprueba que llegó por una petición HTTP real (`is_uploaded_file`).
2. Tamaño máximo configurable (5 MB por defecto).
3. **El tipo se lee del contenido**, no del nombre ni de lo que diga el navegador.
4. La extensión se deriva del tipo real, nunca de la que envió el cliente.
5. **Las imágenes se reconstruyen píxel a píxel con GD**. El archivo resultante
   contiene solo datos de imagen: desaparecen los metadatos EXIF (incluida la
   geolocalización) y cualquier código incrustado en una imagen «políglota».
6. Nombre aleatorio y almacenamiento **fuera del directorio público**: los
   archivos se sirven por un controlador que valida la ruta y el permiso.
7. Permisos `0640` en los archivos y `0750` en las carpetas.

Los PDF no se pueden reconstruir con GD, así que se validan por estructura y se
rechazan los que contengan JavaScript, acciones automáticas o archivos
incrustados, que es el vector habitual de PDF maliciosos.

También hay límite de píxeles para frenar las «bombas de descompresión».

---

## Acceso a los comprobantes

Un comprobante de transferencia contiene datos bancarios del cliente. Solo
pueden verlo el personal y el propio cliente que lo subió; la comprobación se
hace en cada petición, y esas respuestas nunca se cachean.

---

## Datos bancarios

Los números de cuenta que cargas en el panel se guardan **cifrados** con
XChaCha20-Poly1305 (libsodium), un cifrado autenticado: si alguien modifica el
dato en la base, el descifrado falla en lugar de devolver basura.

La clave sale de `APP_KEY`, que vive en el `.env`. En los listados los números se
muestran enmascarados y solo se revelan completos al cliente que va a transferir.

---

## Cabeceras de respuesta

| Cabecera | Qué evita |
|---|---|
| `Content-Security-Policy` con nonce | Ejecución de scripts inyectados |
| `X-Content-Type-Options: nosniff` | Que el navegador adivine el tipo |
| `X-Frame-Options: DENY` | Clickjacking |
| `Strict-Transport-Security` | Degradación a HTTP |
| `Referrer-Policy` | Fuga de URLs a terceros |
| `Permissions-Policy` | Uso no consentido de cámara, micrófono, ubicación |
| `Cross-Origin-Opener-Policy` | Ataques entre ventanas |

---

## Limitación de peticiones

Cada ruta sensible tiene su propio límite con ventana deslizante: acceso,
registro, recuperación de contraseña, agendamiento, subida de comprobantes y
toda la API. Se persiste en base de datos, así que funciona en hosting
compartido sin necesidad de Redis.

La dirección IP solo se toma de `X-Forwarded-For` si has declarado un proxy de
confianza; de lo contrario cualquiera podría falsear su IP para saltarse el
límite.

---

## Formularios públicos

Campo trampa invisible que un humano nunca rellena, y comprobación del tiempo
de cumplimentación: un envío en menos de dos segundos es un bot.

---

## Permisos

Cinco roles con permisos por módulo y acción, guardados en base de datos para
que puedas reasignarlos desde el panel. Cada ruta del panel declara el permiso
que exige, y el menú solo muestra lo que cada persona puede usar.

Opcionalmente puedes restringir el panel a direcciones IP concretas con
`ADMIN_IP_ALLOWLIST`.

---

## Auditoría

Toda acción sensible queda registrada: quién, qué, cuándo, desde qué dirección y
con qué valores antes y después. Los intentos de acceso, correctos y fallidos, se
guardan aparte.

Los registros **enmascaran automáticamente** contraseñas, tokens, números de
cuenta y cualquier campo cuyo nombre sugiera un secreto.

---

## Privacidad de los datos

Cada canal de comunicación tiene su propio consentimiento, con fecha y dirección
IP de cuando se dio. Toda campaña lleva enlace de baja obligatorio.

El **derecho al olvido** está implementado: elimina la cuenta, la foto y los
comprobantes, y anonimiza el historial de citas, que se conserva por obligación
contable pero sin datos identificables.

Las políticas de retención definen cuánto se guarda cada tipo de dato antes de
borrarse definitivamente.

---

## Errores

En producción los errores 5xx muestran un mensaje genérico. Los detalles van al
registro. Los mensajes de conexión a la base de datos, que traen credenciales,
**nunca** se propagan al usuario.

---

## Lo que te toca a ti

Ningún sistema es seguro si el entorno no lo es:

- [ ] **HTTPS** con certificado válido y `FORCE_HTTPS=true`.
- [ ] `APP_DEBUG=false` en producción. Sin excepciones.
- [ ] Las tres claves (`APP_KEY`, `JWT_SECRET`, `PASSWORD_PEPPER`) generadas y
      guardadas en lugar seguro.
- [ ] La raíz del dominio apuntando a `backend/public/`, nunca a la carpeta del proyecto.
- [ ] Permisos: `chmod 640 .env` y `chmod -R 750 storage`.
- [ ] Usuario de base de datos con permisos solo sobre su base.
- [ ] PHP y el sistema operativo **actualizados**.
- [ ] Verificación en dos pasos activada en tu cuenta de administrador.
- [ ] Copias de seguridad automáticas guardadas **fuera del servidor**.
- [ ] Revisar el historial de accesos de vez en cuando.

---

## Si algo pasa

1. Activa el **modo mantenimiento** (Ajustes → Sistema).
2. Mira **Auditoría** e **Historial de accesos** para ver qué ocurrió.
3. Cambia todas las claves del `.env`.
4. Fuerza el cambio de contraseña del personal:
   `php cli/console.php usuario:clave --email=...`
5. Revisa `backend/storage/logs/`.
6. Restaura desde la copia de seguridad limpia más reciente si hace falta.
