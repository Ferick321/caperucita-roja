<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Clock;
use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Url;
use App\Security\Crypto;
use App\Security\Hash;

/**
 * Datos iniciales.
 *
 * Deja el sistema utilizable desde el primer minuto: roles y permisos,
 * ajustes por defecto, secciones de la web, catalogo de ejemplo, metodos de
 * pago, plantillas de aviso y politicas de retencion.
 *
 * Es idempotente: se puede volver a ejecutar sin duplicar nada.
 */
final class InitialSeeder
{
    /** @var list<string> */
    private array $messages = [];

    /** @return list<string> */
    public function run(string $adminEmail, string $adminPassword): array
    {
        Database::instance()->transaction(function () use ($adminEmail, $adminPassword): void {
            $this->seedRolesAndPermissions();
            $this->seedSettings();
            $this->seedBranch();
            $this->seedCatalog();
            $this->seedStaff();
            $this->seedPaymentMethods();
            $this->seedContentBlocks();
            $this->seedNotificationTemplates();
            $this->seedRetentionPolicies();
            $this->seedFaqs();
            $this->seedAdmin($adminEmail, $adminPassword);
        });

        return $this->messages;
    }

    private function note(string $message): void
    {
        $this->messages[] = '  ' . $message;
    }

    private function now(): string
    {
        return Clock::nowUtc();
    }

    // ---- Roles y permisos ----------------------------------------------

    private function seedRolesAndPermissions(): void
    {
        $roles = [
            ['super_admin', 'Super administrador', 'Control total del sistema.', 10],
            ['admin', 'Administrador', 'Gestiona el negocio completo salvo la seguridad avanzada.', 20],
            ['manager', 'Recepcion', 'Agenda, clientes y cobros del dia a dia.', 30],
            ['staff', 'Profesional', 'Ve y atiende su propia agenda.', 40],
            ['client', 'Cliente', 'Agenda y consulta sus citas.', 90],
        ];

        foreach ($roles as [$slug, $name, $description, $priority]) {
            if (QueryBuilder::table('roles')->where('slug', $slug)->exists()) {
                continue;
            }

            QueryBuilder::table('roles')->insert([
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'is_system' => 1,
                'priority' => $priority,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }

        $modules = [
            'panel' => ['ver' => 'Entrar al panel'],
            'citas' => [
                'ver' => 'Ver la agenda', 'crear' => 'Crear citas', 'editar' => 'Editar citas',
                'cancelar' => 'Cancelar citas', 'eliminar' => 'Eliminar citas',
            ],
            'clientes' => [
                'ver' => 'Ver clientes', 'crear' => 'Crear clientes', 'editar' => 'Editar clientes',
                'eliminar' => 'Eliminar clientes', 'exportar' => 'Exportar la base de clientes',
            ],
            'servicios' => ['ver' => 'Ver el catalogo', 'editar' => 'Editar el catalogo'],
            'personal' => ['ver' => 'Ver el equipo', 'editar' => 'Editar el equipo', 'horarios' => 'Gestionar horarios'],
            'pagos' => ['ver' => 'Ver pagos', 'verificar' => 'Verificar comprobantes', 'cuentas' => 'Editar cuentas bancarias'],
            'publicidad' => ['ver' => 'Ver la publicidad', 'editar' => 'Crear y editar anuncios'],
            'campanas' => ['ver' => 'Ver campanas', 'enviar' => 'Enviar campanas'],
            'contenido' => ['ver' => 'Ver el contenido web', 'editar' => 'Editar la pagina web'],
            'ajustes' => ['ver' => 'Ver los ajustes', 'editar' => 'Cambiar los ajustes'],
            'reportes' => ['ver' => 'Ver informes'],
            'sistema' => ['mantenimiento' => 'Ejecutar mantenimiento', 'auditoria' => 'Ver la auditoria'],
        ];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action => $label) {
                $slug = $module . '.' . $action;

                if (QueryBuilder::table('permissions')->where('slug', $slug)->exists()) {
                    continue;
                }

                QueryBuilder::table('permissions')->insert([
                    'slug' => $slug,
                    'module' => $module,
                    'name' => $label,
                    'created_at' => $this->now(),
                ]);
            }
        }

        // Reparto de permisos por rol. El super administrador los tiene todos
        // de forma implicita, por eso no aparece aqui.
        $assignments = [
            'admin' => ['*todos*'],
            'manager' => [
                'panel.ver', 'citas.ver', 'citas.crear', 'citas.editar', 'citas.cancelar',
                'clientes.ver', 'clientes.crear', 'clientes.editar',
                'servicios.ver', 'personal.ver', 'pagos.ver', 'pagos.verificar',
                'publicidad.ver', 'campanas.ver', 'reportes.ver',
            ],
            'staff' => ['panel.ver', 'citas.ver', 'citas.editar', 'clientes.ver', 'servicios.ver'],
            'client' => [],
        ];

        $allPermissions = QueryBuilder::table('permissions')->select(['id', 'slug'])->get();
        $permissionIds = [];

        foreach ($allPermissions as $permission) {
            $permissionIds[(string) $permission['slug']] = (int) $permission['id'];
        }

        foreach ($assignments as $roleSlug => $slugs) {
            $roleId = QueryBuilder::table('roles')->where('slug', $roleSlug)->value('id');

            if ($roleId === null) {
                continue;
            }

            $roleId = (int) $roleId;

            // No se sobrescribe un reparto que el administrador ya haya ajustado.
            if (QueryBuilder::table('role_permissions')->where('role_id', $roleId)->exists()) {
                continue;
            }

            $targets = $slugs === ['*todos*'] ? array_keys($permissionIds) : $slugs;

            foreach ($targets as $slug) {
                if (!isset($permissionIds[$slug])) {
                    continue;
                }

                Database::instance()->statement(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:r, :p)',
                    ['r' => $roleId, 'p' => $permissionIds[$slug]]
                );
            }
        }

        $this->note('Roles y permisos listos.');
    }

    // ---- Ajustes --------------------------------------------------------

    private function seedSettings(): void
    {
        /**
         * clave => [valor, tipo, etiqueta, ayuda, publico, cifrado]
         */
        $settings = [
            // Negocio
            'business.name' => ['Mi Barberia & Estilo', 'string', 'Nombre del negocio', 'Aparece en la web, la app y los correos.', 1, 0],
            'business.tagline' => ['Tu mejor version empieza aqui', 'string', 'Lema', 'Frase corta bajo el nombre.', 1, 0],
            'business.description' => ['Barberia, peluqueria y estetica. Cortes, color, barba, manicure y pedicure con cita previa.', 'text', 'Descripcion', 'Texto de presentacion del negocio.', 1, 0],
            'business.phone' => ['', 'string', 'Telefono', 'Numero visible para los clientes.', 1, 0],
            'business.whatsapp' => ['', 'string', 'WhatsApp', 'Numero con codigo de pais, sin signos.', 1, 0],
            'business.email' => ['', 'email', 'Correo de contacto', '', 1, 0],
            'business.address' => ['', 'string', 'Direccion', '', 1, 0],
            'business.city' => ['', 'string', 'Ciudad', '', 1, 0],
            'business.maps_url' => ['', 'url', 'Enlace de Google Maps', 'Para el boton "Como llegar".', 1, 0],
            'business.logo' => ['', 'image', 'Logotipo', 'PNG o WEBP con fondo transparente.', 1, 0],
            'business.favicon' => ['', 'image', 'Icono del navegador', '', 1, 0],
            'business.timezone' => ['America/Guayaquil', 'string', 'Zona horaria', 'Determina los horarios de la agenda.', 1, 0],
            'business.currency' => ['USD', 'string', 'Moneda', 'Codigo de 3 letras.', 1, 0],
            'business.currency_symbol' => ['$', 'string', 'Simbolo de moneda', '', 1, 0],
            'business.currency_position' => ['before', 'select', 'Posicion del simbolo', '', 1, 0],
            'business.currency_decimals' => ['2', 'int', 'Decimales', '', 1, 0],
            'business.tax_percent' => ['0', 'float', 'Impuesto (%)', 'Se suma al total de la cita. 0 para no aplicar.', 1, 0],

            // Apariencia
            'theme.primary_color' => ['#c9a227', 'color', 'Color principal', 'Botones y acentos.', 1, 0],
            'theme.secondary_color' => ['#111827', 'color', 'Color secundario', '', 1, 0],
            'theme.accent_color' => ['#e11d48', 'color', 'Color de realce', 'Ofertas y avisos.', 1, 0],
            'theme.background_color' => ['#0b0f19', 'color', 'Fondo', '', 1, 0],
            'theme.surface_color' => ['#141b2d', 'color', 'Superficie de tarjetas', '', 1, 0],
            'theme.text_color' => ['#e5e7eb', 'color', 'Color del texto', '', 1, 0],
            'theme.font_heading' => ['Poppins', 'string', 'Tipografia de titulos', '', 1, 0],
            'theme.font_body' => ['Inter', 'string', 'Tipografia del texto', '', 1, 0],
            'theme.dark_mode' => ['1', 'bool', 'Tema oscuro', 'Aplica a la web y a la app.', 1, 0],
            'theme.rounded_corners' => ['16', 'int', 'Redondeo de esquinas (px)', '', 1, 0],

            // Reservas
            'booking.enabled' => ['1', 'bool', 'Agendamiento en linea activo', 'Desactivalo para pausar las reservas.', 1, 0],
            'booking.require_login' => ['0', 'bool', 'Exigir cuenta para reservar', 'Si esta apagado se admiten reservas de invitado.', 1, 0],
            'booking.slot_interval_minutes' => ['15', 'int', 'Intervalo entre horarios (min)', '', 1, 0],
            'booking.min_hours_before' => ['2', 'int', 'Antelacion minima (horas)', '', 1, 0],
            'booking.max_days_ahead' => ['60', 'int', 'Dias maximos de anticipacion', '', 1, 0],
            'booking.allow_multiple_services' => ['1', 'bool', 'Permitir varios servicios por cita', '', 1, 0],
            'booking.max_services_per_appointment' => ['4', 'int', 'Servicios maximos por cita', '', 1, 0],
            'booking.auto_confirm' => ['0', 'bool', 'Confirmar automaticamente', 'Si no, las citas quedan pendientes de tu aprobacion.', 1, 0],
            'booking.allow_staff_choice' => ['1', 'bool', 'El cliente elige profesional', '', 1, 0],
            'booking.allow_no_preference' => ['1', 'bool', 'Ofrecer "sin preferencia"', '', 1, 0],
            'booking.cancellation_hours' => ['4', 'int', 'Antelacion para cancelar (horas)', '', 1, 0],
            'booking.allow_client_cancel' => ['1', 'bool', 'El cliente puede cancelar', '', 1, 0],
            'booking.allow_client_reschedule' => ['1', 'bool', 'El cliente puede reprogramar', '', 1, 0],
            'booking.max_active_per_client' => ['3', 'int', 'Citas activas por cliente', '', 1, 0],
            'booking.custom_request_enabled' => ['1', 'bool', 'Permitir peticion libre', 'Campo "Otro: especifica lo que necesitas".', 1, 0],
            'booking.custom_request_label' => ['Otro (especifica lo que necesitas)', 'string', 'Texto de la peticion libre', '', 1, 0],
            'booking.custom_request_minutes' => ['30', 'int', 'Duracion de una peticion libre (min)', '', 1, 0],
            'booking.terms_text' => ['', 'text', 'Condiciones al reservar', 'Se muestra antes de confirmar.', 1, 0],

            // Pagos
            'payments.enabled' => ['1', 'bool', 'Cobros activos', '', 1, 0],
            'payments.require_deposit' => ['0', 'bool', 'Exigir abono para reservar', '', 1, 0],
            'payments.deposit_percent' => ['30', 'float', 'Porcentaje de abono', '', 1, 0],
            'payments.proof_required_for_transfer' => ['1', 'bool', 'Exigir comprobante en transferencias', '', 1, 0],
            'payments.transfer_instructions' => ['Realiza la transferencia y sube el comprobante para confirmar tu cita.', 'text', 'Instrucciones de transferencia', 'Texto que ve el cliente al elegir transferencia.', 1, 0],

            // Publicidad
            'ads.enabled' => ['1', 'bool', 'Publicidad activa', 'Interruptor general de banners y ventanas.', 1, 0],
            'ads.show_on_login' => ['1', 'bool', 'Mostrar al iniciar sesion', '', 1, 0],
            'ads.show_while_browsing' => ['1', 'bool', 'Mostrar mientras navega', '', 1, 0],
            'ads.show_on_exit' => ['1', 'bool', 'Mostrar al intentar salir', '', 1, 0],
            'ads.browsing_delay_seconds' => ['45', 'int', 'Segundos antes de la ventana', '', 1, 0],
            'ads.max_popups_per_session' => ['2', 'int', 'Maximo de ventanas por visita', 'Evita saturar al visitante.', 1, 0],
            'ads.respect_do_not_track' => ['1', 'bool', 'Respetar "no rastrear"', '', 1, 0],
            'ads.inactive_days' => ['60', 'int', 'Dias para considerar cliente inactivo', '', 1, 0],

            // App movil
            'app.download_url_android' => ['', 'url', 'Enlace de descarga (Android)', 'Google Play o enlace directo.', 1, 0],
            'app.download_url_ios' => ['', 'url', 'Enlace de descarga (iOS)', '', 1, 0],
            'app.apk_direct_url' => ['', 'url', 'Enlace directo al APK', 'Para instalar sin tienda.', 1, 0],
            'app.latest_version' => ['1.0.0', 'string', 'Ultima version publicada', '', 1, 0],
            'app.min_supported_version' => ['1.0.0', 'string', 'Version minima admitida', 'Por debajo se pide actualizar.', 1, 0],
            'app.force_update' => ['0', 'bool', 'Forzar actualizacion', '', 1, 0],
            'app.show_splash_ad' => ['1', 'bool', 'Anuncio en la bienvenida de la app', '', 1, 0],
            'app.promo_text' => ['Descarga la app y agenda en segundos', 'string', 'Texto promocional de la app', '', 1, 0],

            // Avisos
            'notifications.confirm_email' => ['1', 'bool', 'Correo al agendar', '', 0, 0],
            'notifications.reminder_enabled' => ['1', 'bool', 'Recordatorio de cita', '', 0, 0],
            'notifications.reminder_hours_before' => ['24', 'int', 'Horas antes del recordatorio', '', 0, 0],
            'notifications.followup_enabled' => ['1', 'bool', 'Mensaje de seguimiento', '', 0, 0],
            'notifications.review_request_enabled' => ['1', 'bool', 'Pedir resena tras la visita', '', 0, 0],
            'notifications.review_request_hours_after' => ['3', 'int', 'Horas despues para pedir resena', '', 0, 0],

            // Fidelidad
            'loyalty.enabled' => ['1', 'bool', 'Programa de puntos activo', '', 1, 0],
            'loyalty.points_per_currency' => ['1', 'float', 'Puntos por unidad gastada', '', 1, 0],
            'loyalty.points_to_currency' => ['100', 'float', 'Puntos que equivalen a 1 unidad', '', 1, 0],
            'loyalty.welcome_points' => ['50', 'int', 'Puntos de bienvenida', '', 1, 0],
            'loyalty.referral_points' => ['100', 'int', 'Puntos por recomendar', '', 1, 0],

            // Posicionamiento
            'seo.meta_title' => ['', 'string', 'Titulo para buscadores', '', 1, 0],
            'seo.meta_description' => ['', 'text', 'Descripcion para buscadores', 'Hasta 160 caracteres.', 1, 0],
            'seo.og_image' => ['', 'image', 'Imagen al compartir', '1200x630 px.', 1, 0],
            'seo.google_analytics_id' => ['', 'string', 'ID de Google Analytics', '', 0, 0],
            'seo.facebook_pixel_id' => ['', 'string', 'ID del pixel de Facebook', '', 0, 0],

            // Redes sociales
            'social.facebook' => ['', 'url', 'Facebook', '', 1, 0],
            'social.instagram' => ['', 'url', 'Instagram', '', 1, 0],
            'social.tiktok' => ['', 'url', 'TikTok', '', 1, 0],
            'social.youtube' => ['', 'url', 'YouTube', '', 1, 0],

            // Legal
            'legal.privacy_policy' => ['', 'html', 'Politica de privacidad', '', 1, 0],
            'legal.terms' => ['', 'html', 'Terminos y condiciones', '', 1, 0],
            'legal.show_cookie_banner' => ['1', 'bool', 'Aviso de cookies', '', 1, 0],

            // Integraciones (valores sensibles: se guardan cifrados)
            'push.fcm_server_key' => ['', 'string', 'Clave del servidor FCM', 'Para las notificaciones push de la app.', 0, 1],

            // Sistema
            'system.maintenance_mode' => ['0', 'bool', 'Modo mantenimiento', 'Cierra la web al publico; el panel sigue activo.', 0, 0],
            'system.maintenance_message' => ['Estamos realizando mejoras. Volvemos en unos minutos.', 'text', 'Mensaje de mantenimiento', '', 1, 0],
            'system.auto_purge_enabled' => ['1', 'bool', 'Limpieza automatica', 'Aplica las politicas de retencion cada noche.', 0, 0],
        ];

        $order = 0;

        foreach ($settings as $key => [$value, $type, $label, $help, $isPublic, $encrypted]) {
            $order++;

            if (QueryBuilder::table('settings')->where('setting_key', $key)->exists()) {
                continue;
            }

            QueryBuilder::table('settings')->insert([
                'group_name' => explode('.', $key)[0],
                'setting_key' => $key,
                'setting_value' => $encrypted && $value !== '' ? Crypto::encrypt((string) $value) : $value,
                'value_type' => $type,
                'label' => $label,
                'help_text' => $help,
                'options_json' => $key === 'business.currency_position'
                    ? json_encode(['before' => 'Antes del importe ($ 10)', 'after' => 'Despues del importe (10 $)'])
                    : null,
                'is_public' => $isPublic,
                'is_encrypted' => $encrypted,
                'sort_order' => $order,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }

        $this->note('Ajustes por defecto cargados (' . count($settings) . ' claves).');
    }

    // ---- Sucursal y horarios -------------------------------------------

    private function seedBranch(): void
    {
        if (QueryBuilder::table('branches')->exists()) {
            return;
        }

        $branchId = QueryBuilder::table('branches')->insert([
            'name' => 'Local principal',
            'slug' => 'local-principal',
            'address' => '',
            'city' => '',
            'timezone' => 'America/Guayaquil',
            'is_active' => 1,
            'is_default' => 1,
            'sort_order' => 0,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);

        // Lunes a viernes 9-19, sabado 9-17, domingo cerrado.
        $hours = [
            0 => ['09:00:00', '17:00:00', 1],
            1 => ['09:00:00', '19:00:00', 0],
            2 => ['09:00:00', '19:00:00', 0],
            3 => ['09:00:00', '19:00:00', 0],
            4 => ['09:00:00', '19:00:00', 0],
            5 => ['09:00:00', '20:00:00', 0],
            6 => ['09:00:00', '17:00:00', 0],
        ];

        foreach ($hours as $weekday => [$open, $close, $closed]) {
            QueryBuilder::table('branch_hours')->insert([
                'branch_id' => $branchId,
                'weekday' => $weekday,
                'opens_at' => $open,
                'closes_at' => $close,
                'is_closed' => $closed,
            ]);
        }

        for ($i = 1; $i <= 3; $i++) {
            QueryBuilder::table('resources')->insert([
                'branch_id' => $branchId,
                'name' => 'Estacion ' . $i,
                'type' => 'estacion',
                'capacity' => 1,
                'is_active' => 1,
                'created_at' => $this->now(),
            ]);
        }

        $this->note('Sucursal principal y horario semanal creados.');
    }

    // ---- Catalogo -------------------------------------------------------

    private function seedCatalog(): void
    {
        if (QueryBuilder::table('service_categories')->exists()) {
            return;
        }

        $categories = [
            ['Barberia', 'barberia', 'Cortes clasicos y modernos, barba y afeitado.', 'scissors', '#c9a227'],
            ['Peluqueria', 'peluqueria', 'Corte, peinado, color y tratamientos.', 'sparkles', '#8b5cf6'],
            ['Manicure', 'manicure', 'Cuidado y decoracion de unias de las manos.', 'hand', '#ec4899'],
            ['Pedicure', 'pedicure', 'Cuidado completo de los pies.', 'foot', '#14b8a6'],
            ['Estetica', 'estetica', 'Faciales, cejas, pestanas y depilacion.', 'face', '#f97316'],
            ['Otros servicios', 'otros', 'Solicita algo que no esta en la lista.', 'plus', '#64748b'],
        ];

        $categoryIds = [];
        $order = 0;

        foreach ($categories as [$name, $slug, $description, $icon, $color]) {
            $categoryIds[$slug] = QueryBuilder::table('service_categories')->insert([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'icon' => $icon,
                'color' => $color,
                'is_active' => 1,
                'show_on_home' => 1,
                'sort_order' => $order++,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }

        $services = [
            ['barberia', 'Corte de cabello clasico', 30, 10.00, 'Corte a tijera y maquina con lavado.'],
            ['barberia', 'Corte + barba', 50, 16.00, 'Corte completo con perfilado y arreglo de barba.'],
            ['barberia', 'Afeitado clasico', 30, 8.00, 'Toalla caliente, navaja y balsamo.'],
            ['barberia', 'Corte infantil', 25, 8.00, 'Para ninos hasta 12 anos.'],
            ['peluqueria', 'Corte y peinado', 45, 15.00, 'Corte, lavado y secado con peinado.'],
            ['peluqueria', 'Color completo', 90, 40.00, 'Tinte de raiz a puntas.'],
            ['peluqueria', 'Mechas / balayage', 150, 75.00, 'Tecnica de iluminacion personalizada.'],
            ['peluqueria', 'Tratamiento de hidratacion', 60, 25.00, 'Reparacion profunda para cabello danado.'],
            ['manicure', 'Manicure clasica', 40, 12.00, 'Limado, cuticula y esmalte.'],
            ['manicure', 'Unias acrilicas', 90, 30.00, 'Aplicacion completa con diseno.'],
            ['pedicure', 'Pedicure spa', 60, 18.00, 'Exfoliacion, masaje y esmalte.'],
            ['estetica', 'Limpieza facial profunda', 60, 28.00, 'Higiene facial con extraccion.'],
            ['estetica', 'Diseno de cejas', 25, 10.00, 'Perfilado y depilacion.'],
            ['estetica', 'Lifting de pestanas', 60, 30.00, 'Curvatura y tinte.'],
        ];

        $order = 0;

        foreach ($services as [$categorySlug, $name, $duration, $price, $description]) {
            QueryBuilder::table('services')->insert([
                'category_id' => $categoryIds[$categorySlug],
                'name' => $name,
                'slug' => Url::slug($name),
                'short_description' => $description,
                'duration_minutes' => $duration,
                'buffer_after_minutes' => 5,
                'price' => $price,
                'loyalty_points' => (int) round($price),
                'is_active' => 1,
                'is_featured' => $order < 4 ? 1 : 0,
                'bookable_online' => 1,
                'sort_order' => $order++,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }

        $this->note('Catalogo de ejemplo creado (' . count($categories) . ' categorias, ' . count($services) . ' servicios).');
    }

    // ---- Equipo de ejemplo ------------------------------------------------

    /**
     * Crea tres profesionales con jornada completa.
     *
     * Sin personal con horario no existe disponibilidad, asi que el sistema
     * arranca con un equipo de muestra que el administrador puede renombrar,
     * editar o eliminar desde el panel.
     */
    private function seedStaff(): void
    {
        if (QueryBuilder::table('staff')->exists()) {
            return;
        }

        $branchId = (int) (QueryBuilder::table('branches')->orderBy('id')->value('id') ?? 0);

        if ($branchId === 0) {
            return;
        }

        $team = [
            ['Profesional 1', 'Barbero', '#0ea5e9', ['barberia', 'otros']],
            ['Profesional 2', 'Estilista', '#8b5cf6', ['peluqueria', 'estetica', 'otros']],
            ['Profesional 3', 'Manicurista', '#ec4899', ['manicure', 'pedicure', 'otros']],
        ];

        $order = 0;

        foreach ($team as [$name, $title, $color, $categorySlugs]) {
            $staffId = QueryBuilder::table('staff')->insert([
                'branch_id' => $branchId,
                'display_name' => $name,
                'slug' => Url::slug($name . '-' . $title),
                'title' => $title,
                'bio' => 'Edita esta ficha desde el panel para contar la experiencia de tu equipo.',
                'color' => $color,
                'accepts_online' => 1,
                'is_active' => 1,
                'show_on_web' => 1,
                'sort_order' => $order++,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);

            // Servicios que presta, segun las categorias asignadas.
            $serviceIds = QueryBuilder::table('services')
                ->join('service_categories', 'service_categories.id', '=', 'services.category_id')
                ->whereIn('service_categories.slug', $categorySlugs)
                ->pluck('services.id');

            foreach ($serviceIds as $serviceId) {
                Database::instance()->statement(
                    'INSERT IGNORE INTO staff_services (staff_id, service_id) VALUES (:s, :v)',
                    ['s' => $staffId, 'v' => (int) $serviceId]
                );
            }

            // Jornada: lunes a sabado, con pausa al mediodia.
            foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
                QueryBuilder::table('staff_schedules')->insert([
                    'staff_id' => $staffId,
                    'weekday' => $weekday,
                    'starts_at' => '09:00:00',
                    'ends_at' => $weekday === 6 ? '17:00:00' : '19:00:00',
                    'break_start' => '13:00:00',
                    'break_end' => '14:00:00',
                    'is_active' => 1,
                ]);
            }
        }

        $this->note('Equipo de ejemplo creado (3 profesionales con horario de lunes a sabado).');
    }

    // ---- Metodos de pago -------------------------------------------------

    private function seedPaymentMethods(): void
    {
        if (QueryBuilder::table('payment_methods')->exists()) {
            return;
        }

        $methods = [
            [
                'code' => 'efectivo',
                'name' => 'Efectivo',
                'description' => 'Paga en el local al momento de tu cita.',
                'instructions' => 'Acercate unos minutos antes y paga en caja. Aceptamos billetes y monedas.',
                'icon' => 'cash',
                'requires_proof' => 0,
                'shows_bank_accounts' => 0,
                'requires_verification' => 0,
                'sort_order' => 0,
            ],
            [
                'code' => 'transferencia',
                'name' => 'Transferencia bancaria',
                'description' => 'Transfiere a nuestra cuenta y sube el comprobante.',
                'instructions' => 'Realiza la transferencia a cualquiera de las cuentas indicadas y sube la '
                    . 'foto o el archivo del comprobante. Verificamos el pago y confirmamos tu cita.',
                'icon' => 'bank',
                'requires_proof' => 1,
                'shows_bank_accounts' => 1,
                'requires_verification' => 1,
                'sort_order' => 1,
            ],
            [
                'code' => 'tarjeta_local',
                'name' => 'Tarjeta en el local',
                'description' => 'Paga con tarjeta al llegar.',
                'instructions' => 'Contamos con datafono para debito y credito.',
                'icon' => 'card',
                'requires_proof' => 0,
                'shows_bank_accounts' => 0,
                'requires_verification' => 0,
                'sort_order' => 2,
            ],
        ];

        foreach ($methods as $method) {
            QueryBuilder::table('payment_methods')->insert($method + [
                'is_online' => 1,
                'is_active' => 1,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }

        $this->note('Metodos de pago creados (efectivo, transferencia, tarjeta).');
    }

    // ---- Contenido de la web ---------------------------------------------

    private function seedContentBlocks(): void
    {
        if (QueryBuilder::table('content_blocks')->exists()) {
            return;
        }

        $blocks = [
            [
                'block_key' => 'hero',
                'section_type' => 'hero',
                'title' => 'Tu estilo, en las mejores manos',
                'subtitle' => 'Barberia, peluqueria y estetica con cita previa. Sin filas, sin esperas.',
                'body' => '',
                'cta_label' => 'Agendar mi cita',
                'cta_url' => '/agendar',
                'cta_secondary_label' => 'Descargar la app',
                'cta_secondary_url' => '/app',
                'sort_order' => 0,
            ],
            [
                'block_key' => 'app_promo',
                'section_type' => 'app_promo',
                'title' => 'Lleva tu barberia en el bolsillo',
                'subtitle' => 'Agenda, reprograma y paga desde el celular. Recibe recordatorios y promociones.',
                'body' => 'Con la app puedes elegir tu profesional favorito, ver los horarios libres en tiempo '
                    . 'real, subir tu comprobante de pago y acumular puntos en cada visita.',
                'cta_label' => 'Descargar para Android',
                'cta_url' => '/app',
                'sort_order' => 10,
            ],
            [
                'block_key' => 'services_intro',
                'section_type' => 'services',
                'title' => 'Nuestros servicios',
                'subtitle' => 'Elige lo que necesitas; si no lo encuentras, cuentanos y lo resolvemos.',
                'sort_order' => 20,
            ],
            [
                'block_key' => 'team_intro',
                'section_type' => 'team',
                'title' => 'Nuestro equipo',
                'subtitle' => 'Profesionales con anos de experiencia listos para atenderte.',
                'sort_order' => 30,
            ],
            [
                'block_key' => 'gallery_intro',
                'section_type' => 'gallery',
                'title' => 'Nuestro trabajo',
                'subtitle' => 'Algunos de los resultados que nos enorgullecen.',
                'sort_order' => 40,
            ],
            [
                'block_key' => 'reviews_intro',
                'section_type' => 'reviews',
                'title' => 'Lo que dicen nuestros clientes',
                'subtitle' => '',
                'sort_order' => 50,
            ],
            [
                'block_key' => 'about',
                'section_type' => 'about',
                'title' => 'Sobre nosotros',
                'subtitle' => 'Mas que un corte: una experiencia',
                'body' => 'Somos un espacio dedicado al cuidado personal donde cada detalle cuenta. '
                    . 'Trabajamos con productos de primera calidad y un equipo que se capacita constantemente '
                    . 'para ofrecerte exactamente lo que buscas.',
                'sort_order' => 60,
            ],
            [
                'block_key' => 'contact',
                'section_type' => 'contact',
                'title' => 'Visitanos',
                'subtitle' => 'Estamos para atenderte',
                'sort_order' => 70,
            ],
        ];

        foreach ($blocks as $block) {
            QueryBuilder::table('content_blocks')->insert($block + [
                'is_active' => 1,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }

        $this->note('Secciones de la pagina web creadas (editables desde el panel).');
    }

    // ---- Plantillas de aviso --------------------------------------------

    private function seedNotificationTemplates(): void
    {
        if (QueryBuilder::table('notification_templates')->exists()) {
            return;
        }

        $vars = '{cliente},{codigo},{fecha},{hora},{fecha_hora},{profesional},{servicios},{total},{negocio},{telefono},{direccion},{url_sitio},{url_cita}';

        $templates = [
            ['cita_recibida', 'Solicitud de cita recibida', 'Recibimos tu solicitud de cita {codigo}'],
            ['cita_confirmada', 'Cita confirmada', 'Tu cita {codigo} esta confirmada'],
            ['cita_cancelada', 'Cita cancelada', 'Tu cita {codigo} fue cancelada'],
            ['cita_reprogramada', 'Cita reprogramada', 'Tu cita {codigo} cambio de horario'],
            ['recordatorio_cita', 'Recordatorio de cita', 'Recordatorio: tu cita es el {fecha} a las {hora}'],
            ['cita_completada', 'Gracias por tu visita', 'Gracias por visitarnos, {cliente}'],
            ['solicitar_resena', 'Solicitud de resena', 'Como estuvo tu visita a {negocio}?'],
            ['pago_aprobado', 'Pago aprobado', 'Confirmamos tu pago de la cita {codigo}'],
            ['pago_rechazado', 'Pago rechazado', 'Necesitamos revisar el pago de tu cita {codigo}'],
            ['bienvenida', 'Bienvenida al registrarse', 'Bienvenido a {negocio}'],
        ];

        foreach ($templates as [$key, $name, $subject]) {
            QueryBuilder::table('notification_templates')->insert([
                'template_key' => $key,
                'channel' => 'email',
                'name' => $name,
                'subject' => $subject,
                'body' => '<p>Hola <strong>{cliente}</strong>,</p><p>Tu cita <strong>{codigo}</strong> es el '
                    . '<strong>{fecha_hora}</strong> con {profesional}.</p><p>Servicios: {servicios}<br>'
                    . 'Total: {total}</p><p><a href="{url_cita}">Ver mi cita</a></p>'
                    . '<p>{negocio} &middot; {direccion} &middot; {telefono}</p>',
                'available_vars' => $vars,
                'is_active' => 1,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }

        $this->note('Plantillas de correo creadas (editables desde el panel).');
    }

    // ---- Politicas de retencion ------------------------------------------

    private function seedRetentionPolicies(): void
    {
        if (QueryBuilder::table('retention_policies')->exists()) {
            return;
        }

        $policies = [
            ['registro_accesos', 'Intentos de acceso', 'Historial de inicios de sesion correctos y fallidos.', 'login_attempts', 'created_at', 90, '', 0],
            ['limites_peticiones', 'Contadores de limite', 'Ventanas del limitador de peticiones ya vencidas.', 'rate_limits', 'expires_at', 1, '', 0],
            ['eventos_publicidad', 'Eventos de publicidad', 'Impresiones y clics de banners.', 'banner_events', 'created_at', 180, '', 0],
            ['avisos_enviados', 'Avisos ya enviados', 'Correos y notificaciones procesados.', 'notification_queue', 'created_at', 60, "status IN ('sent','cancelled','failed')", 0],
            ['auditoria', 'Bitacora de auditoria', 'Registro de acciones del panel.', 'audit_logs', 'created_at', 730, '', 0],
            ['tokens_expirados', 'Tokens caducados', 'Sesiones de la app movil ya vencidas.', 'refresh_tokens', 'expires_at', 30, '', 0],
            ['recuperacion_clave', 'Enlaces de recuperacion', 'Enlaces de restablecimiento usados o vencidos.', 'password_resets', 'expires_at', 7, '', 0],
            ['verificaciones', 'Codigos de verificacion', 'Codigos de correo y telefono vencidos.', 'email_verifications', 'expires_at', 7, '', 0],
            ['destinatarios_campana', 'Destinatarios de campanas', 'Detalle de envios de campanas antiguas.', 'campaign_recipients', 'created_at', 365, '', 0],
            ['mensajes_contacto', 'Mensajes de contacto', 'Mensajes del formulario web ya atendidos.', 'contact_messages', 'created_at', 365, 'is_read = 1', 0],
            ['comprobantes_antiguos', 'Comprobantes de pago', 'Imagenes de comprobantes de citas muy antiguas.', 'payment_proofs', 'created_at', 1095, '', 1],
            ['lista_espera', 'Lista de espera', 'Solicitudes de lista de espera vencidas.', 'waitlist', 'created_at', 90, "status IN ('expired','converted')", 0],
            ['historial_mantenimiento', 'Historial de mantenimiento', 'Registro de tareas de limpieza ejecutadas.', 'maintenance_runs', 'created_at', 365, '', 0],
        ];

        foreach ($policies as [$key, $label, $description, $table, $column, $days, $condition, $deletesFiles]) {
            QueryBuilder::table('retention_policies')->insert([
                'policy_key' => $key,
                'label' => $label,
                'description' => $description,
                'target_table' => $table,
                'date_column' => $column,
                'retention_days' => $days,
                'condition_sql' => $condition,
                'deletes_files' => $deletesFiles,
                'is_active' => 1,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }

        $this->note('Politicas de retencion creadas (' . count($policies) . ').');
    }

    private function seedFaqs(): void
    {
        if (QueryBuilder::table('faqs')->exists()) {
            return;
        }

        $faqs = [
            ['Necesito cita previa?', 'Recomendamos agendar para asegurar tu horario, pero tambien atendemos por orden de llegada segun disponibilidad.'],
            ['Como cancelo o cambio mi cita?', 'Desde la app o la web, en la seccion "Mis citas". Se admite con la antelacion indicada en las condiciones.'],
            ['Que formas de pago aceptan?', 'Efectivo y tarjeta en el local, y transferencia bancaria subiendo el comprobante desde la app o la web.'],
            ['Puedo elegir a mi profesional?', 'Si. Al agendar puedes seleccionar a quien prefieras o dejar que asignemos al primero disponible.'],
        ];

        $order = 0;

        foreach ($faqs as [$question, $answer]) {
            QueryBuilder::table('faqs')->insert([
                'question' => $question,
                'answer' => $answer,
                'is_active' => 1,
                'sort_order' => $order++,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }

        $this->note('Preguntas frecuentes de ejemplo creadas.');
    }

    // ---- Cuenta administradora ------------------------------------------

    private function seedAdmin(string $email, string $password): void
    {
        $email = mb_strtolower(trim($email));

        if (QueryBuilder::table('users')->where('email', $email)->exists()) {
            $this->note('La cuenta ' . $email . ' ya existia; no se modifico.');

            return;
        }

        QueryBuilder::table('users')->insert([
            'uuid' => self::uuid4(),
            'role' => 'super_admin',
            'first_name' => 'Administrador',
            'last_name' => '',
            'email' => $email,
            'email_verified_at' => $this->now(),
            'password_hash' => Hash::make($password),
            'password_changed_at' => $this->now(),
            'status' => 'active',
            'accepts_marketing' => 0,
            'referral_code' => strtoupper(bin2hex(random_bytes(4))),
            'source' => 'instalacion',
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);

        $this->note('Cuenta de super administrador creada: ' . $email);
    }

    public static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
