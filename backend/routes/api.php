<?php

declare(strict_types=1);

/**
 * API REST v1 para la aplicacion movil.
 *
 * Convenciones:
 *  - respuesta uniforme {"ok": true, "data": ...} o {"ok": false, "error": ...};
 *  - autenticacion por cabecera Authorization: Bearer <token de acceso>;
 *  - todas las rutas estan limitadas por frecuencia;
 *  - las fechas viajan en UTC y en formato ISO (Y-m-d H:i:s).
 *
 * @var App\Core\Router $router
 */

use App\Controllers\Api\AdController;
use App\Controllers\Api\AppointmentController;
use App\Controllers\Api\AuthController;
use App\Controllers\Api\CatalogController;
use App\Controllers\Api\ConfigController;
use App\Controllers\Api\PaymentController;
use App\Controllers\Api\ProfileController;

$router->group('/api/v1', ['cors'], static function (App\Core\Router $router): void {

    // ---- Publico ---------------------------------------------------------
    $router->get('/config', [ConfigController::class, 'index'], ['throttle:120,60']);
    $router->get('/sucursales/{id:int}/horarios', [ConfigController::class, 'branchHours'], ['throttle:60,60']);

    $router->get('/categorias', [CatalogController::class, 'categories'], ['throttle:120,60']);
    $router->get('/servicios', [CatalogController::class, 'services'], ['throttle:120,60']);
    $router->get('/servicios/{id:int}', [CatalogController::class, 'service'], ['throttle:120,60']);
    $router->get('/profesionales', [CatalogController::class, 'staff'], ['throttle:120,60']);
    $router->get('/galeria', [CatalogController::class, 'gallery'], ['throttle:60,60']);
    $router->get('/resenas', [CatalogController::class, 'reviews'], ['throttle:60,60']);
    $router->get('/preguntas', [CatalogController::class, 'faqs'], ['throttle:60,60']);
    $router->get('/disponibilidad', [CatalogController::class, 'availability'], ['throttle:180,60']);

    $router->get('/publicidad', [AdController::class, 'index'], ['throttle:120,60']);
    $router->post('/publicidad/evento', [AdController::class, 'track'], ['throttle:240,60']);

    // ---- Acceso ----------------------------------------------------------
    $router->post('/auth/registro', [AuthController::class, 'register'], ['throttle:10,3600']);
    $router->post('/auth/login', [AuthController::class, 'login'], ['throttle:20,900']);
    $router->post('/auth/refrescar', [AuthController::class, 'refresh'], ['throttle:60,3600']);
    $router->post('/auth/salir', [AuthController::class, 'logout'], ['throttle:60,3600']);
    $router->post('/auth/recuperar', [AuthController::class, 'forgotPassword'], ['throttle:6,3600']);

    // ---- Requiere sesion -------------------------------------------------
    $router->group('', ['api'], static function (App\Core\Router $router): void {
        $router->post('/auth/salir-todo', [AuthController::class, 'logoutAll'], ['throttle:20,3600']);
        $router->post('/dispositivos', [AuthController::class, 'registerDevice'], ['throttle:30,3600']);

        $router->get('/perfil', [ProfileController::class, 'show'], ['throttle:120,60']);
        $router->put('/perfil', [ProfileController::class, 'update'], ['throttle:40,3600']);
        $router->post('/perfil/avatar', [ProfileController::class, 'updateAvatar'], ['throttle:15,3600']);
        $router->post('/perfil/clave', [ProfileController::class, 'changePassword'], ['throttle:10,3600']);
        $router->post('/perfil/eliminar', [ProfileController::class, 'deleteAccount'], ['throttle:5,3600']);
        $router->get('/fidelidad', [ProfileController::class, 'loyalty'], ['throttle:60,60']);

        $router->get('/citas', [AppointmentController::class, 'index'], ['throttle:120,60']);
        $router->post('/citas', [AppointmentController::class, 'store'], ['throttle:20,3600']);
        $router->get('/citas/{id:int}', [AppointmentController::class, 'show'], ['throttle:120,60']);
        $router->post('/citas/{id:int}/cancelar', [AppointmentController::class, 'cancel'], ['throttle:20,3600']);
        $router->post('/citas/{id:int}/reprogramar', [AppointmentController::class, 'reschedule'], ['throttle:20,3600']);
        $router->post('/citas/{id:int}/resena', [AppointmentController::class, 'review'], ['throttle:20,3600']);

        $router->get('/pagos/metodos', [PaymentController::class, 'methods'], ['throttle:60,60']);
        $router->get('/pagos/cuentas', [PaymentController::class, 'bankAccounts'], ['throttle:60,60']);
        $router->post('/pagos', [PaymentController::class, 'store'], ['throttle:30,3600']);
        $router->post('/pagos/{id:int}/comprobante', [PaymentController::class, 'uploadProof'], ['throttle:20,3600']);
    });
});
