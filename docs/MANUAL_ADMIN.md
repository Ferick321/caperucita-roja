# Manual del panel

Guía práctica para el día a día. Todo se hace con formularios: no hay que
tocar código en ningún momento.

Entra en `https://tudominio.com/panel`.

---

## El resumen

Lo primero que ves al entrar. Arriba aparece **«Termina de configurar tu
sistema»** con lo que falta por ajustar; la lista se va vaciando sola.

Debajo tienes las cifras del día, el gráfico de las últimas dos semanas, la
agenda de hoy y los comprobantes pendientes de verificar. Los números rojos del
menú lateral indican lo que necesita tu atención ahora.

---

## Publicidad

El módulo que más ingresos genera. Un anuncio se compone de **qué se muestra**
y **dónde y cuándo aparece**.

### Crear un anuncio

**Publicidad → + Nuevo anuncio.**

| Campo | Para qué |
|---|---|
| Nombre interno | Solo para que tú lo reconozcas en la lista |
| Título y subtítulo | Lo que lee el cliente |
| Imagen (escritorio / móvil) | Puedes poner una distinta para el celular |
| Texto y enlace del botón | Usa `/agendar` para llevar directo a la reserva |
| Colores | Fondo y texto del anuncio |

### Dónde aparece

Marca todas las que quieras:

| Ubicación | Dónde se ve |
|---|---|
| Portada principal | Bloque grande en el inicio de la web |
| Franja bajo el menú | Banda delgada, ideal para ofertas cortas |
| Columna lateral | Al lado del contenido |
| **Ventana al iniciar sesión** | Se abre justo cuando el cliente entra |
| **Ventana mientras navega** | Aparece tras unos segundos navegando |
| **Aviso al intentar salir** | Cuando mueve el ratón fuera de la página |
| Bienvenida de la app | Al abrir la aplicación móvil |
| Tarjeta en el inicio de la app | Dentro del listado de inicio |
| Pantalla completa en la app | Entre secciones de la app |

En **«Páginas donde aplica»** puedes limitarlo: `*` para toda la web, o
`/servicios*, /agendar` para páginas concretas.

### Cuándo aparece

Fechas de inicio y fin, días de la semana y franja horaria. Ejemplo: una promoción
de martes a jueves de 10:00 a 16:00 para llenar las horas flojas.

### A quién

- **Todo el mundo**
- **Visitantes sin cuenta** — para invitarles a registrarse
- **Clientes registrados**
- **Clientes nuevos** (aún sin visitas)
- **Clientes inactivos** — los que hace tiempo que no vienen

Y por dispositivo: todos, computadora, navegador móvil o app.

### Que no moleste

Esto es lo que diferencia un anuncio efectivo de uno que espanta:

- **Vistas máximas por persona**: `3` significa que nadie lo verá una cuarta vez.
- **Horas entre apariciones**: `24` lo muestra como mucho una vez al día.
- **Retraso antes de aparecer**: deja al visitante mirar antes de interrumpirle.
- **Cerrarse solo tras N segundos**.
- **Prioridad**: si dos anuncios compiten por el mismo sitio, gana el de número mayor.

Además, el sistema respeta un tope global de ventanas por visita, configurable en
**Ajustes → Publicidad**, y no vuelve a mostrar un anuncio que la persona cerró.

### Ver si funciona

Cada anuncio muestra vistas, clics, cierres y **efectividad** (porcentaje de clics).
Si un anuncio tiene muchas vistas y ningún clic, cambia el texto o la imagen.
**Reiniciar vistas** borra el historial y vuelve a mostrarlo a todos.

---

## Ajustes: la marca y las reglas

**Ajustes** tiene trece pestañas. Cada campo trae su explicación debajo.

| Pestaña | Lo más importante |
|---|---|
| **Negocio** | Nombre, teléfono, WhatsApp, dirección, moneda, zona horaria, impuesto |
| **Apariencia** | Logo, colores, tipografías, redondeo. **Cambia la web y la app a la vez** |
| **Reservas** | Antelación mínima, días de anticipación, si confirmas tú o es automático |
| **Pagos** | Si pides abono, si exiges comprobante en transferencias |
| **Publicidad** | Interruptor general y tope de ventanas por visita |
| **App móvil** | Enlace de descarga, versión mínima admitida |
| **Avisos** | Cuándo se envían los recordatorios y las peticiones de reseña |
| **Fidelidad** | Puntos por compra y su equivalencia en dinero |
| **Buscadores** | Título y descripción para Google |
| **Redes sociales** | Enlaces a tus perfiles |
| **Legal** | Privacidad, términos, aviso de cookies |
| **Notificaciones push** | Clave del servicio de mensajería |
| **Sistema** | Modo mantenimiento y limpieza automática |

> **Modo mantenimiento**: cierra la web al público mostrando un aviso, mientras
> tú y tu equipo seguís trabajando con normalidad en el panel.

### Plantillas de correo

**Ajustes → Plantillas de correo.** Son los mensajes que reciben tus clientes.
Puedes cambiar el texto usando las variables entre llaves, que se sustituyen
solas: `{cliente}`, `{fecha}`, `{hora}`, `{profesional}`, `{servicios}`,
`{total}`, `{codigo}`, `{negocio}`.

---

## Servicios y precios

**Servicios** lista tu catálogo. Cada servicio tiene:

- **Duración**: cuánto ocupa en la agenda.
- **Preparación / limpieza**: minutos extra que se reservan pero no se cobran.
  Útil para no encadenar citas sin respiro.
- **Precio** y **precio promocional** con fechas: la promoción entra y sale sola.
- **Abono para reservar**: fijo o porcentaje, si quieres asegurar la cita.
- **Quién puede prestarlo**: si no marcas a nadie, cualquiera puede.

**Categorías** agrupa tu oferta: barbería, peluquería, manicure, pedicure,
estética o las que necesites. Aparecen como filtros en la web y en la app.

---

## Equipo y horarios

**Equipo → + Nuevo profesional.** Tras guardar, la ficha te deja definir:

**Horario semanal.** Marca los días que trabaja, su entrada, su salida y la pausa
del mediodía. **Sin horario no aparecen huecos libres**: es la causa número uno
de «no hay disponibilidad».

**Vacaciones y ausencias.** Bloquea rangos de fechas. Si hay citas ya agendadas
en ese periodo, el sistema te avisa para que las reprogrames.

**Acceso al panel.** Si le pones un correo, puedes crearle una cuenta para que
vea su propia agenda. Se genera una contraseña temporal que debe cambiar.

---

## Sucursales, horarios y feriados

**Sucursales** es donde defines tus locales. Es el módulo que manda sobre la
agenda: **si el local está cerrado, no se ofrece ninguna cita a esa hora**,
aunque el profesional esté libre.

De cada sucursal defines su dirección, teléfono, zona horaria y foto. Al crearla
se le pone un horario de lunes a sábado que puedes ajustar.

### Horario de atención

Para cada día indicas a qué hora abres, a qué hora cierras y, si quieres, un
descanso (a esa hora no se ofrecen citas). Marca **Cerrado** en los días que no
abres.

El sistema no acepta una hora de cierre anterior a la de apertura: te avisaría
en vez de dejarte el día sin turnos sin darte cuenta.

### Feriados y días cerrados

Vacaciones, feriados o cualquier día suelto en que no atiendes. En cuanto
guardas el cierre, esas fechas dejan de aceptar citas en la web y en la app.

---

## Cupones y descuentos

**Cupones** son códigos que el cliente escribe al reservar. Puedes hacerlos:

- **En porcentaje** (10% de descuento) o **de monto fijo** (5 de descuento).
- **Con tope**: un descuento máximo en dinero, para que un porcentaje alto no se
  te vaya de las manos.
- **Limitados por fecha**, por número total de usos o por usos de cada cliente.
- **Solo para un servicio** concreto.
- **Solo para quien viene por primera vez.**

En la ficha de cada cupón ves cuántas veces se ha usado y quién lo canjeó.

---

## Citas

**Agenda del día** muestra una columna por profesional. **Citas** es el listado
con filtros por estado, profesional y fechas.

En la ficha de una cita puedes confirmarla, marcarla en curso, completarla,
marcar que no asistió, cancelarla con motivo, reprogramarla y registrar el cobro.

**+ Nueva cita** sirve para el mostrador y el teléfono: escribes el nombre,
eliges servicios y el sistema te muestra los horarios libres.

---

## Cobros y comprobantes

### Tus cuentas bancarias

**Cuentas y cobros → Añadir una cuenta.** Rellena banco, tipo, número, titular e
identificación. Esto es exactamente lo que ve el cliente cuando elige
**Transferencia**, tanto en la web como en la app.

> El número de cuenta se guarda **cifrado** en la base de datos.

### Verificar un comprobante

**Pagos** se abre en «Por verificar». Cada tarjeta muestra el importe, el cliente,
la referencia y la imagen del comprobante (tócala para ampliarla).

- **Aprobar** → la cita pasa a confirmada y el cliente recibe el aviso.
- **Rechazar** → escribe el motivo; el cliente lo verá y podrá subir otro.

Si un mismo comprobante se usa en dos citas, el sistema lo marca con un aviso.

### Métodos de pago

En la misma pantalla ajustas efectivo, transferencia y tarjeta: si aparecen al
reservar, si muestran los datos bancarios, si exigen comprobante y si necesitan
tu aprobación.

---

## Clientes

Listado con segmentos: nuevos, frecuentes, inactivos, los que aceptan publicidad
y los bloqueados. En cada ficha ves su historial, sus pagos y sus puntos, y
puedes añadir **notas internas** (preferencias, alergias) que solo ve tu equipo.

**Exportar CSV** descarga tu base de clientes para Excel.

---

## Suscriptores

**Suscriptores** es la lista de quien aceptó recibir tu publicidad, desde la web,
la app o apuntado a mano en el local. Es la lista a la que llegan tus campañas.

Puedes añadir a alguien a mano (asegúrate de que te dio permiso), darlo de baja
sin borrarlo (queda constancia de que pidió la baja) o borrarlo del todo.
**Descargar CSV** te da la lista para usarla en otra herramienta.

---

## Lista de espera

Clientes que querían una hora ocupada y pidieron aviso si se libera. Cuando les
llames, márcalos como **Avisado**; si reservan, como **Reservó**.

---

## Campañas

**Campañas → + Nueva campaña.** Elige el canal, el público y escribe el mensaje.
Al guardar, el sistema calcula cuánta gente lo recibirá.

Solo se contacta a quienes **dieron su consentimiento**, y todo mensaje lleva
enlace de baja: es un requisito legal y el sistema no lo deja saltar.

Públicos disponibles: todos, clientes nuevos, inactivos, frecuentes y
cumpleañeros de hoy.

Para enviar, escribe `ENVIAR` y confirma. Los mensajes salen progresivamente.
Puedes **programarla** poniendo fecha y hora.

---

## Página web

**Página web** edita las secciones de tu portada: título, subtítulo, texto,
imágenes y botones. Puedes desactivar las que no quieras.

**Galería**: sube tus mejores trabajos. Es lo que más convence a un cliente nuevo.

**Reseñas**: las opiniones **no se publican hasta que las apruebas**. Puedes
responder públicamente a cada una, que es de lo que más confianza genera.

**Preguntas frecuentes** y **Mensajes** completan la sección.

---

## Informes

Ingresos por día, ticket medio, clientes nuevos, porcentaje que repite, servicios
más vendidos, rendimiento del equipo, **horas con más demanda** (útil para ajustar
turnos) y de dónde vienen las reservas (web, app, mostrador, teléfono).

**Exportar CSV** descarga el detalle para tu contabilidad.

---

## Mantenimiento

**Mantenimiento** es donde liberas espacio de verdad.

Arriba ves cuánto ocupa la base de datos, cuánto los archivos subidos, cuántos
avisos hay en cola y cuántos registros están marcados como eliminados.

### Antes de borrar, simula

**Simular limpieza** te muestra exactamente qué se borraría, sin borrar nada.

### Ejecutar la limpieza

Marca lo que quieras y escribe `LIMPIAR`:

- **Aplicar políticas de retención**: borra registros antiguos según los días que
  configures abajo.
- **Purgar lo marcado como eliminado**: lo que borraste desde el panel desaparece
  de verdad de la base de datos.
- **Eliminar archivos huérfanos**: imágenes que ya no usa ningún registro.
- **Compactar las tablas**: devuelve al servidor el espacio liberado.
- **Borrar registros antiguos** de la aplicación.

### Tablas y espacio

**Mantenimiento → Tablas y espacio** te muestra, una por una, cuánto ocupa cada
parte del sistema y cuántos registros tiene.

- **Compactar** devuelve al servidor el espacio que quedó libre después de
  borrar. No toca tus datos, así que puedes usarlo siempre que quieras.
- **Vaciar** borra todo el contenido de esa tabla y **no tiene vuelta atrás**.
  Para evitar accidentes tienes que escribir el nombre exacto de la tabla.

Solo se pueden vaciar tablas de registro (historial de acciones, intentos de
acceso, estadísticas, cola de avisos…). Tus servicios, tu equipo, tus
sucursales, tus clientes y tus ajustes **no aparecen ahí a propósito**:
vaciarlos dejaría el sistema inservible.

### Archivos subidos

**Mantenimiento → Archivos subidos** lista todo lo que se ha subido (fotos,
comprobantes, imágenes de la web) agrupado por carpeta, con su tamaño.

Los marcados como **Sin usar** ya no aparecen en ninguna ficha, así que puedes
borrarlos para liberar espacio sin romper nada.

### Copias de seguridad

**Mantenimiento → Copias de seguridad** genera un archivo con toda tu
información, que puedes descargar y volver a importar si algo sale mal.

**Hazte una copia antes de vaciar o limpiar cualquier cosa**, y guárdala en tu
computadora: si el servidor falla, las copias que estén dentro de él se pierden
igual.

### Limpieza automática (políticas de retención)

**Mantenimiento → Limpieza automática** define cuánto tiempo se conserva cada
tipo de dato antes de borrarse solo: intentos de acceso, eventos de publicidad,
avisos enviados, auditoría, comprobantes...

Puedes **crear tus propias reglas, cambiarlas, apagarlas o eliminarlas**. Cada
regla necesita tres cosas: qué datos se limpian, con qué fecha se miden
(casi siempre `created_at`) y cuántos días se guardan.

Ajusta los días según tus obligaciones contables. Si dejas activa la **limpieza
automática** (Ajustes → Sistema), esto se ejecuta solo cada madrugada.

### Auditoría

**Auditoría** registra quién hizo qué, cuándo y desde dónde. **Historial de
accesos** muestra los inicios de sesión correctos y fallidos: si ves muchos
fallidos seguidos desde la misma dirección, alguien está intentando entrar.

---

## Eliminar un cliente por completo

Si un cliente pide que borres sus datos:

**Clientes → su ficha → Zona de riesgo → Eliminar datos personales.**
Escribe `ELIMINAR` y confirma.

Se borran su cuenta, su foto y sus comprobantes. Su historial de citas se
conserva de forma **anónima**, porque forma parte de tu contabilidad.

---

## Usuarios del panel

**Usuarios del panel** es donde das de alta a quien puede entrar a administrar
y con qué permisos.

El sistema se protege solo de los errores más caros:

- **No puedes darle a nadie más poder del que tú tienes.** Un administrador no
  puede crear ni ascender a un super administrador.
- **No puedes suspenderte ni eliminarte a ti mismo**, ni cambiarte tu propio rol.
- Un administrador **no puede tocar a un super administrador.**
- Al cambiar la contraseña de alguien **se cierran sus sesiones abiertas**,
  también las de la app móvil.

### Permisos del equipo

| Rol | Qué puede hacer |
|---|---|
| **Super administrador** | Todo, incluida la seguridad y el mantenimiento |
| **Administrador** | Todo el negocio |
| **Recepción** | Agenda, clientes y cobros del día a día |
| **Profesional** | Solo su propia agenda |
| **Cliente** | Sus citas desde la web y la app |

---

## Recomendaciones

- Activa la **verificación en dos pasos** en tu cuenta de super administrador.
- Revisa los comprobantes **cada día**: un cliente esperando confirmación es un
  cliente que puede irse a otro sitio.
- Responde a las reseñas, también a las malas.
- Mira los **informes** una vez por semana: las horas flojas se llenan con una
  promoción bien puesta.
- Haz **copias de seguridad** y guárdalas fuera del servidor.
