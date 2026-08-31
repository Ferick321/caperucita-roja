<?php

declare(strict_types=1);

/**
 * Rutas del sitio publico.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\Web\AccountController;
use App\Controllers\Web\AdController;
use App\Controllers\Web\AuthController;
use App\Controllers\Web\BookingController;
use App\Controllers\Web\HomeController;
use App\Controllers\Web\MediaController;

$router->group('', ['https', 'maintenance'], static function (App\Core\Router $router): void {

    // ---- Paginas publicas -------------------------------------------------
    $router->get('/', [HomeController::class, 'index'], [], 'inicio');
    $router->get('/servicios', [HomeController::class, 'services'], [], 'servicios');
    $router->get('/servicios/{slug}', [HomeController::class, 'serviceDetail'], [], 'servicio');
    $router->get('/equipo', [HomeController::class, 'teamPage'], [], 'equipo');
    $router->get('/galeria', [HomeController::class, 'gallery'], [], 'galeria');
    $router->get('/contacto', [HomeController::class, 'contact'], [], 'contacto');
    $router->post('/contacto', [HomeController::class, 'submitContact'], ['csrf']);
    $router->get('/app', [HomeController::class, 'appDownload'], [], 'app');
    $router->get('/legal/{page}', [HomeController::class, 'legal'], [], 'legal');
    $router->post('/boletin', [HomeController::class, 'subscribe'], ['csrf']);

    // ---- Agendamiento -----------------------------------------------------
    $router->get('/agendar', [BookingController::class, 'start'], [], 'agendar');
    $router->post('/agendar/disponibilidad', [BookingController::class, 'availability'], ['csrf']);
    $router->post('/agendar', [BookingController::class, 'store'], ['csrf']);
    $router->get('/agendar/confirmacion/{code}', [BookingController::class, 'confirmation'], [], 'confirmacion');

    // ---- Acceso -----------------------------------------------------------
    $router->get('/ingresar', [AuthController::class, 'showLogin'], [], 'ingresar');
    $router->post('/ingresar', [AuthController::class, 'login'], ['csrf', 'throttle:15,900']);
    $router->get('/registro', [AuthController::class, 'showRegister'], [], 'registro');
    $router->post('/registro', [AuthController::class, 'register'], ['csrf', 'throttle:10,3600']);
    $router->post('/salir', [AuthController::class, 'logout'], ['csrf']);

    $router->get('/verificacion', [AuthController::class, 'showTwoFactor']);
    $router->post('/verificacion', [AuthController::class, 'verifyTwoFactor'], ['csrf', 'throttle:10,600']);

    $router->get('/recuperar', [AuthController::class, 'showForgot'], [], 'recuperar');
    $router->post('/recuperar', [AuthController::class, 'sendResetLink'], ['csrf', 'throttle:6,3600']);
    $router->get('/restablecer/{token}', [AuthController::class, 'showReset']);
    $router->post('/restablecer', [AuthController::class, 'resetPassword'], ['csrf', 'throttle:10,3600']);

    // ---- Area privada del cliente ----------------------------------------
    $router->get('/mi-cuenta', [AccountController::class, 'dashboard'], ['auth'], 'mi_cuenta');
    $router->get('/mis-citas', [AccountController::class, 'appointments'], ['auth'], 'mis_citas');
    $router->post('/mis-citas/{id:int}/cancelar', [AccountController::class, 'cancelAppointment'], ['auth', 'csrf']);
    $router->get('/mis-citas/{id:int}/pago', [AccountController::class, 'paymentPage'], ['auth']);
    $router->post('/mis-citas/{id:int}/pago', [AccountController::class, 'submitPayment'], ['auth', 'csrf', 'throttle:20,3600']);
    $router->get('/mi-perfil', [AccountController::class, 'profile'], ['auth'], 'mi_perfil');
    $router->post('/mi-perfil', [AccountController::class, 'updateProfile'], ['auth', 'csrf']);
    $router->post('/mi-perfil/clave', [AccountController::class, 'changePassword'], ['auth', 'csrf']);
    $router->post('/mi-perfil/eliminar', [AccountController::class, 'deleteAccount'], ['auth', 'csrf']);

    // ---- Publicidad y medios ---------------------------------------------
    $router->get('/publicidad/obtener', [AdController::class, 'fetch']);
    $router->post('/publicidad/evento', [AdController::class, 'track'], ['csrf']);
    $router->get('/baja/{token}', [AdController::class, 'unsubscribe']);
    $router->get('/apertura/{token}.gif', [AdController::class, 'trackOpen']);
    $router->get('/media/{path:path}', [MediaController::class, 'show']);
});
