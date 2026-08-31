# Arquitectura

Cómo está construido el sistema y por qué se tomó cada decisión.

---

## Las tres piezas

```
┌────────────────┐   HTML   ┌──────────────────────────────┐
│  Navegador     │ ◀──────▶ │                              │
└────────────────┘          │        Servidor PHP          │
                            │                              │
┌────────────────┐   JSON   │  ┌────────────────────────┐  │
│  App Android   │ ◀──────▶ │  │  Ajustes (93 claves)   │  │
│   (Flutter)    │          │  │  = comportamiento      │  │
└────────────────┘          │  └────────────────────────┘  │
                            │           │                  │
                            │      MySQL · 50 tablas       │
                            └──────────────────────────────┘
```

Web y app comparten **la misma base de datos y las mismas reglas**. Una cita
creada desde el mostrador aparece al instante en el teléfono del cliente, y un
cambio de color en el panel se ve en los dos sitios sin publicar nada.

---

## La idea central: la configuración es el programa

Lo que en otros sistemas se escribe en el código, aquí vive en la tabla
`settings` y se edita con formularios.

Cada ajuste declara **de qué tipo es** (`color`, `bool`, `image`, `select`,
`text`, `int`...). El panel lee ese tipo y pinta el control adecuado: un selector
de color, un interruptor, un campo de subida. Añadir un ajuste nuevo es insertar
una fila; no hay que tocar la vista.

Los valores se cachean en memoria durante la petición, con una sola consulta, y
tienen valores por defecto en código para que el sistema funcione incluso antes
de instalar la base de datos.

La API expone estos ajustes en `/api/v1/config`, que es lo que hace que la app
móvil se adapte sola.

---

## Backend

### Por qué sin framework

El destinatario es un negocio pequeño que probablemente use hosting compartido.
Un framework grande obliga a Composer, a una versión concreta de PHP y a
actualizaciones periódicas. Aquí el sistema **funciona sin ejecutar Composer**:
trae su propio autocargador PSR-4.

A cambio, se implementó solo lo necesario, con las mismas garantías de seguridad
que daría un framework serio.

### Capas

```
public/index.php          Único punto de entrada
        │
    App::handle()         Sesión, ajustes, zona horaria
        │
    Router                Rutas con parámetros y middlewares
        │
    Middlewares           HTTPS · mantenimiento · CSRF · sesión · permisos · límite
        │
    Controllers           Validan la entrada y eligen la respuesta
        │
    Services              Las reglas del negocio viven aquí
        │
    QueryBuilder / PDO    Consultas siempre parametrizadas
```

**Los controladores no tienen reglas de negocio.** Validan, llaman a un servicio
y devuelven. Así la misma regla se aplica igual venga de la web, del panel o de
la app: `BookingService::create()` es el único sitio donde se crea una cita.

### Servicios

| Servicio | Responsabilidad |
|---|---|
| `SettingsService` | Los 93 ajustes, con caché y valores por defecto |
| `AvailabilityService` | Calcula los huecos reales de la agenda |
| `BookingService` | Crea, cambia de estado y reprograma citas |
| `PaymentService` | Pagos, cuentas cifradas y comprobantes |
| `BannerService` | Decide qué anuncio ve cada persona y cuándo |
| `CampaignService` | Público de las campañas y consentimiento |
| `NotificationService` | Encola avisos; no envía nada en línea |
| `QueueWorker` | Procesa la cola desde la tarea programada |
| `MaintenanceService` | Retención, purga real y compactación |
| `MediaService` | Biblioteca de archivos y reutilización |
| `StatsService` | Métricas del panel |

---

## El motor de disponibilidad

Es la pieza con más reglas. Para un día concreto cruza:

```
  Horario de la sucursal
+ Jornada del profesional
− Descanso del mediodía
− Vacaciones y ausencias
− Feriados y cierres
− Citas ya reservadas (con sus márgenes)
− Antelación mínima
= Huecos que se ofrecen al cliente
```

Todas esas reglas salen de la configuración, no del código.

El descanso **parte la jornada en dos tramos** en lugar de restar un hueco, lo
que evita ofrecer horarios que se solapan con la comida. Los márgenes de
preparación y limpieza de cada servicio se suman a la duración, así que dos citas
nunca quedan pegadas sin respiro.

---

## Cómo se evita la doble reserva

Dos clientes pueden pulsar «confirmar» en el mismo segundo. Comprobar la
disponibilidad y después insertar deja una ventana en la que ambos pasan.

La solución tiene tres capas dentro de una transacción:

```php
Database::transaction(function () {
    // 1. Bloqueo pesimista sobre las filas del profesional
    QueryBuilder::table('appointments')
        ->where('staff_id', $staffId)
        ->where('starts_at', '<', $endUtc)
        ->where('ends_at', '>', $startUtc)
        ->forUpdate()          // ← nadie más puede insertar aquí hasta terminar
        ->get();

    // 2. Se vuelve a comprobar el hueco YA bloqueado
    if (!AvailabilityService::isSlotFree(...)) {
        throw new HttpException(409, 'Ese horario acaba de ocuparse.');
    }

    // 3. Inserción
});
```

El cliente que llega segundo recibe un 409 y la app le devuelve al paso del
horario con la lista ya actualizada. Está verificado en las pruebas.

---

## Fechas y zonas horarias

**La base de datos guarda siempre UTC.** La zona horaria del negocio es un ajuste
editable que solo se aplica al mostrar e interpretar fechas.

Esto evita el problema clásico: si el negocio cambia de zona o el país modifica
su horario de verano, las citas ya guardadas siguen apuntando al instante
correcto. La clase `Clock` centraliza toda la conversión.

---

## Publicidad

Un anuncio se define en dos tablas: `banners` (qué se muestra) y
`banner_placements` (dónde y en qué páginas). Un mismo anuncio puede aparecer en
varias ubicaciones a la vez.

En cada petición, `BannerService` filtra por: activo, dentro de fechas, día de la
semana, franja horaria, dispositivo, patrón de página, público objetivo y
**control de frecuencia**.

El control de frecuencia usa `banner_events`, que registra vistas, clics y
cierres por persona. Para visitantes sin cuenta se usa un identificador aleatorio
guardado en la sesión: sirve para no repetir el anuncio y **no identifica a
nadie**.

Las ventanas emergentes se piden de forma asíncrona para no retrasar la carga de
la página, y hay un tope global por visita.

---

## Notificaciones

Nada se envía durante la petición del usuario. Todo entra en
`notification_queue` y lo procesa la tarea programada.

Un servidor de correo lento no debe retrasar nunca una reserva. Además, la cola
permite reintentos con espera creciente y deja rastro de lo que falló.

El trabajador marca cada mensaje como «enviando» **antes** de procesarlo, de
forma atómica, para que dos ejecuciones simultáneas del cron no dupliquen envíos.

---

## Mantenimiento y espacio

Tres mecanismos que responden a «poder eliminar de verdad y optimizar espacio»:

1. **Borrado lógico** (`deleted_at`): lo eliminado desde el panel se oculta pero
   se conserva, por si fue un error o por obligación contable.
2. **Purga definitiva**: las políticas de retención borran físicamente los
   registros pasado el plazo que tú definas. El borrado va **por lotes de 1000**
   para no bloquear la tabla en bases grandes.
3. **Compactación** (`OPTIMIZE TABLE`): devuelve al sistema de archivos el
   espacio que MySQL tenía reservado.

Además se limpian los **archivos huérfanos**: se recorre el disco y se borra lo
que ya no referencia ninguna fila, respetando una hora de gracia por si algo
está a medio subir.

Todo se puede **simular antes de ejecutar**, y cada limpieza queda registrada.

Las tablas sobre las que se puede actuar están en una lista blanca cerrada en el
código: una política mal configurada no puede tocar una tabla arbitraria.

---

## Base de datos

50 tablas en seis migraciones temáticas. Decisiones que conviene conocer:

**Datos congelados en el histórico.** `appointment_services` guarda el nombre y
el precio del servicio tal como estaban al reservar. Si mañana subes el precio,
las citas antiguas siguen mostrando lo que se cobró. Lo mismo con el nombre y
teléfono del cliente en `appointments`.

**Claves foráneas con criterio.** `ON DELETE CASCADE` donde la fila hija no tiene
sentido sin la padre; `SET NULL` donde el histórico debe sobrevivir;
`RESTRICT` donde borrar sería un error.

**Índices pensados para las consultas reales**, sobre todo
`(staff_id, starts_at, ends_at)`, que es el que sostiene el motor de
disponibilidad.

**Migraciones idempotentes** con `CREATE TABLE IF NOT EXISTS`, registradas con su
hash: si un archivo ya aplicado cambia, el sistema avisa en lugar de corromper
el esquema en silencio.

---

## Aplicación móvil

Flutter sin gestor de estado externo: `ChangeNotifier` y `AnimatedBuilder`
bastan para este tamaño y evitan una dependencia más que mantener.

El **tema se construye en tiempo de ejecución** con los colores que llegan de
`/api/v1/config`. Cambiar el color principal en el panel cambia la app en el
siguiente arranque.

La configuración se **cachea en disco**, así que la app abre al instante aunque
no haya red, y se refresca en segundo plano.

El cliente HTTP renueva el token de acceso solo cuando está a punto de caducar y
reintenta **una vez** ante un 401. Si varias peticiones coinciden, comparten el
mismo refresco en lugar de lanzar varios.

---

## Pruebas

- **90 pruebas del núcleo** (`backend/tests/run.php`): contraseñas, cifrado,
  JWT (incluido el ataque `alg: none`), TOTP, validación, constructor de
  consultas frente a inyección, redirecciones abiertas, escape de salida, zonas
  horarias y separador de migraciones.
- **27 pruebas de la API** (`backend/tests/api_test.sh`): recorren el flujo
  completo contra un servidor real, incluida la rotación del token de refresco y
  el rechazo de su reutilización.
- **Pruebas de los modelos de la app** (`mobile/test/`): la frontera entre la API
  y la interfaz, donde un campo ausente rompería la pantalla.
