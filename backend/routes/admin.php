<?php

declare(strict_types=1);

/**
 * Rutas del panel de administracion.
 *
 * Todas cuelgan de /panel y exigen sesion, rol operativo y segundo factor
 * (middleware "admin"). Los permisos finos se declaran con "can:modulo.accion".
 *
 * @var App\Core\Router $router
 */

use App\Controllers\Admin\AppointmentController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\BannerController;
use App\Controllers\Admin\CampaignController;
use App\Controllers\Admin\CatalogController;
use App\Controllers\Admin\ClientController;
use App\Controllers\Admin\ContentController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\PaymentController;
use App\Controllers\Admin\ReportController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\StaffController;
use App\Controllers\Admin\SystemController;

// ---- Acceso al panel (sin sesion) --------------------------------------
$router->group('/panel', ['https'], static function (App\Core\Router $router): void {
    $router->get('/acceso', [AuthController::class, 'showLogin'], [], 'panel_acceso');
    $router->post('/acceso', [AuthController::class, 'login'], ['csrf', 'throttle:15,900']);
    $router->post('/salir', [AuthController::class, 'logout'], ['csrf']);

    // La verificacion en dos pasos reutiliza el controlador publico.
    $router->get('/verificacion', [App\Controllers\Web\AuthController::class, 'showTwoFactor']);
    $router->post('/verificacion', [App\Controllers\Web\AuthController::class, 'verifyTwoFactor'], ['csrf', 'throttle:10,600']);
});

// ---- Panel (requiere sesion de personal) --------------------------------
$router->group('/panel', ['https', 'auth', 'admin'], static function (App\Core\Router $router): void {

    $router->get('', [DashboardController::class, 'index'], ['can:panel.ver'], 'panel');

    // -- Citas ------------------------------------------------------------
    $router->get('/citas', [AppointmentController::class, 'index'], ['can:citas.ver'], 'panel_citas');
    $router->get('/citas/agenda', [AppointmentController::class, 'agenda'], ['can:citas.ver']);
    $router->get('/citas/nueva', [AppointmentController::class, 'create'], ['can:citas.crear']);
    $router->post('/citas', [AppointmentController::class, 'store'], ['csrf', 'can:citas.crear']);
    $router->post('/citas/disponibilidad', [AppointmentController::class, 'availability'], ['csrf', 'can:citas.ver']);
    $router->get('/citas/{id:int}', [AppointmentController::class, 'show'], ['can:citas.ver']);
    $router->post('/citas/{id:int}/estado', [AppointmentController::class, 'changeStatus'], ['csrf', 'can:citas.editar']);
    $router->post('/citas/{id:int}/reprogramar', [AppointmentController::class, 'reschedule'], ['csrf', 'can:citas.editar']);
    $router->post('/citas/{id:int}/eliminar', [AppointmentController::class, 'destroy'], ['csrf', 'can:citas.eliminar']);

    // -- Clientes ---------------------------------------------------------
    $router->get('/clientes', [ClientController::class, 'index'], ['can:clientes.ver'], 'panel_clientes');
    $router->post('/clientes', [ClientController::class, 'store'], ['csrf', 'can:clientes.crear']);
    $router->get('/clientes/exportar', [ClientController::class, 'export'], ['can:clientes.exportar']);
    $router->get('/clientes/{id:int}', [ClientController::class, 'show'], ['can:clientes.ver']);
    $router->post('/clientes/{id:int}', [ClientController::class, 'update'], ['csrf', 'can:clientes.editar']);
    $router->post('/clientes/{id:int}/puntos', [ClientController::class, 'adjustPoints'], ['csrf', 'can:clientes.editar']);
    $router->post('/clientes/{id:int}/eliminar', [ClientController::class, 'delete'], ['csrf', 'can:clientes.eliminar']);
    $router->post('/clientes/{id:int}/olvidar', [ClientController::class, 'forget'], ['csrf', 'can:clientes.eliminar']);

    // -- Catalogo ---------------------------------------------------------
    $router->get('/servicios', [CatalogController::class, 'services'], ['can:servicios.ver'], 'panel_servicios');
    $router->get('/servicios/nuevo', [CatalogController::class, 'serviceForm'], ['can:servicios.editar']);
    $router->post('/servicios', [CatalogController::class, 'saveService'], ['csrf', 'can:servicios.editar']);
    $router->get('/servicios/categorias', [CatalogController::class, 'categories'], ['can:servicios.ver']);
    $router->post('/servicios/categorias', [CatalogController::class, 'saveCategory'], ['csrf', 'can:servicios.editar']);
    $router->post('/servicios/categorias/{id:int}', [CatalogController::class, 'saveCategory'], ['csrf', 'can:servicios.editar']);
    $router->post('/servicios/categorias/{id:int}/eliminar', [CatalogController::class, 'deleteCategory'], ['csrf', 'can:servicios.editar']);
    $router->get('/servicios/{id:int}/editar', [CatalogController::class, 'serviceForm'], ['can:servicios.editar']);
    $router->post('/servicios/{id:int}', [CatalogController::class, 'saveService'], ['csrf', 'can:servicios.editar']);
    $router->post('/servicios/{id:int}/eliminar', [CatalogController::class, 'deleteService'], ['csrf', 'can:servicios.editar']);

    // -- Equipo -----------------------------------------------------------
    $router->get('/personal', [StaffController::class, 'index'], ['can:personal.ver'], 'panel_personal');
    $router->get('/personal/nuevo', [StaffController::class, 'form'], ['can:personal.editar']);
    $router->post('/personal', [StaffController::class, 'save'], ['csrf', 'can:personal.editar']);
    $router->get('/personal/{id:int}/editar', [StaffController::class, 'form'], ['can:personal.editar']);
    $router->post('/personal/{id:int}', [StaffController::class, 'save'], ['csrf', 'can:personal.editar']);
    $router->post('/personal/{id:int}/horario', [StaffController::class, 'saveSchedule'], ['csrf', 'can:personal.horarios']);
    $router->post('/personal/{id:int}/ausencia', [StaffController::class, 'addTimeOff'], ['csrf', 'can:personal.horarios']);
    $router->post('/personal/{id:int}/ausencia/{timeOffId:int}/eliminar', [StaffController::class, 'deleteTimeOff'], ['csrf', 'can:personal.horarios']);
    $router->post('/personal/{id:int}/acceso', [StaffController::class, 'toggleAccess'], ['csrf', 'can:personal.editar']);
    $router->post('/personal/{id:int}/eliminar', [StaffController::class, 'delete'], ['csrf', 'can:personal.editar']);

    // -- Pagos ------------------------------------------------------------
    $router->get('/pagos', [PaymentController::class, 'index'], ['can:pagos.ver'], 'panel_pagos');
    $router->get('/pagos/cuentas', [PaymentController::class, 'bankAccounts'], ['can:pagos.cuentas']);
    $router->post('/pagos/cuentas', [PaymentController::class, 'saveBankAccount'], ['csrf', 'can:pagos.cuentas']);
    $router->post('/pagos/cuentas/{id:int}', [PaymentController::class, 'saveBankAccount'], ['csrf', 'can:pagos.cuentas']);
    $router->post('/pagos/cuentas/{id:int}/eliminar', [PaymentController::class, 'deleteBankAccount'], ['csrf', 'can:pagos.cuentas']);
    $router->post('/pagos/metodos/{id:int}', [PaymentController::class, 'saveMethod'], ['csrf', 'can:pagos.cuentas']);
    $router->post('/pagos/manual', [PaymentController::class, 'registerManual'], ['csrf', 'can:pagos.verificar']);
    $router->post('/pagos/{id:int}/aprobar', [PaymentController::class, 'approve'], ['csrf', 'can:pagos.verificar']);
    $router->post('/pagos/{id:int}/rechazar', [PaymentController::class, 'reject'], ['csrf', 'can:pagos.verificar']);

    // -- Publicidad -------------------------------------------------------
    $router->get('/publicidad', [BannerController::class, 'index'], ['can:publicidad.ver'], 'panel_publicidad');
    $router->get('/publicidad/nuevo', [BannerController::class, 'form'], ['can:publicidad.editar']);
    $router->post('/publicidad', [BannerController::class, 'save'], ['csrf', 'can:publicidad.editar']);
    $router->get('/publicidad/{id:int}/editar', [BannerController::class, 'form'], ['can:publicidad.editar']);
    $router->post('/publicidad/{id:int}', [BannerController::class, 'save'], ['csrf', 'can:publicidad.editar']);
    $router->post('/publicidad/{id:int}/activar', [BannerController::class, 'toggle'], ['csrf', 'can:publicidad.editar']);
    $router->post('/publicidad/{id:int}/reiniciar', [BannerController::class, 'resetStats'], ['csrf', 'can:publicidad.editar']);
    $router->post('/publicidad/{id:int}/eliminar', [BannerController::class, 'delete'], ['csrf', 'can:publicidad.editar']);

    // -- Campanas ---------------------------------------------------------
    $router->get('/campanas', [CampaignController::class, 'index'], ['can:campanas.ver'], 'panel_campanas');
    $router->get('/campanas/nueva', [CampaignController::class, 'form'], ['can:campanas.ver']);
    $router->post('/campanas', [CampaignController::class, 'save'], ['csrf', 'can:campanas.ver']);
    $router->get('/campanas/{id:int}/editar', [CampaignController::class, 'form'], ['can:campanas.ver']);
    $router->post('/campanas/{id:int}', [CampaignController::class, 'save'], ['csrf', 'can:campanas.ver']);
    $router->post('/campanas/{id:int}/enviar', [CampaignController::class, 'send'], ['csrf', 'can:campanas.enviar']);
    $router->post('/campanas/{id:int}/cancelar', [CampaignController::class, 'cancel'], ['csrf', 'can:campanas.enviar']);
    $router->post('/campanas/{id:int}/eliminar', [CampaignController::class, 'delete'], ['csrf', 'can:campanas.enviar']);

    // -- Contenido de la web ----------------------------------------------
    $router->get('/contenido', [ContentController::class, 'index'], ['can:contenido.ver'], 'panel_contenido');
    $router->post('/contenido/{id:int}', [ContentController::class, 'saveBlock'], ['csrf', 'can:contenido.editar']);
    $router->get('/contenido/galeria', [ContentController::class, 'gallery'], ['can:contenido.ver']);
    $router->post('/contenido/galeria', [ContentController::class, 'saveGalleryItem'], ['csrf', 'can:contenido.editar']);
    $router->post('/contenido/galeria/{id:int}', [ContentController::class, 'saveGalleryItem'], ['csrf', 'can:contenido.editar']);
    $router->post('/contenido/galeria/{id:int}/eliminar', [ContentController::class, 'deleteGalleryItem'], ['csrf', 'can:contenido.editar']);
    $router->get('/contenido/resenas', [ContentController::class, 'reviews'], ['can:contenido.ver']);
    $router->post('/contenido/resenas/{id:int}', [ContentController::class, 'moderateReview'], ['csrf', 'can:contenido.editar']);
    $router->get('/contenido/preguntas', [ContentController::class, 'faqs'], ['can:contenido.ver']);
    $router->post('/contenido/preguntas', [ContentController::class, 'saveFaq'], ['csrf', 'can:contenido.editar']);
    $router->post('/contenido/preguntas/{id:int}', [ContentController::class, 'saveFaq'], ['csrf', 'can:contenido.editar']);
    $router->post('/contenido/preguntas/{id:int}/eliminar', [ContentController::class, 'deleteFaq'], ['csrf', 'can:contenido.editar']);
    $router->get('/contenido/mensajes', [ContentController::class, 'messages'], ['can:contenido.ver']);
    $router->post('/contenido/mensajes/{id:int}/leido', [ContentController::class, 'markMessageRead'], ['csrf', 'can:contenido.ver']);
    $router->post('/contenido/mensajes/{id:int}/eliminar', [ContentController::class, 'deleteMessage'], ['csrf', 'can:contenido.editar']);

    // -- Informes ---------------------------------------------------------
    $router->get('/reportes', [ReportController::class, 'index'], ['can:reportes.ver'], 'panel_reportes');
    $router->get('/reportes/exportar', [ReportController::class, 'exportAppointments'], ['can:reportes.ver']);

    // -- Ajustes ----------------------------------------------------------
    $router->get('/ajustes', [SettingsController::class, 'index'], ['can:ajustes.ver'], 'panel_ajustes');
    $router->get('/ajustes/plantillas', [SettingsController::class, 'templates'], ['can:ajustes.ver']);
    $router->post('/ajustes/plantillas/{id:int}', [SettingsController::class, 'updateTemplate'], ['csrf', 'can:ajustes.editar']);
    $router->get('/ajustes/{group}', [SettingsController::class, 'group'], ['can:ajustes.ver']);
    $router->post('/ajustes/{group}', [SettingsController::class, 'update'], ['csrf', 'can:ajustes.editar']);

    // -- Sistema ----------------------------------------------------------
    $router->get('/sistema', [SystemController::class, 'index'], ['can:sistema.mantenimiento'], 'panel_sistema');
    $router->get('/sistema/simular-limpieza', [SystemController::class, 'previewCleanup'], ['can:sistema.mantenimiento']);
    $router->post('/sistema/limpiar', [SystemController::class, 'runCleanup'], ['csrf', 'can:sistema.mantenimiento']);
    $router->post('/sistema/retencion/{id:int}', [SystemController::class, 'savePolicy'], ['csrf', 'can:sistema.mantenimiento']);
    $router->post('/sistema/cola', [SystemController::class, 'processQueue'], ['csrf', 'can:sistema.mantenimiento']);
    $router->post('/sistema/cola/reintentar', [SystemController::class, 'retryFailed'], ['csrf', 'can:sistema.mantenimiento']);
    $router->get('/sistema/auditoria', [SystemController::class, 'audit'], ['can:sistema.auditoria']);
    $router->get('/sistema/accesos', [SystemController::class, 'loginAttempts'], ['can:sistema.auditoria']);
});
