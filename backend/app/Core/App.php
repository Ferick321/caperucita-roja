<?php

declare(strict_types=1);

namespace App\Core;

use App\Security\Auth;
use App\Security\SecurityHeaders;
use App\Services\SettingsService;

/** Nucleo de arranque y ciclo de vida de la peticion. */
final class App
{
    private static ?self $instance = null;

    private string $basePath;

    private Router $router;

    private bool $booted = false;

    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->router = new Router();
    }

    public static function boot(string $basePath): self
    {
        if (self::$instance === null) {
            self::$instance = new self($basePath);
            self::$instance->initialize();
        }

        return self::$instance;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('La aplicacion no se ha inicializado.');
        }

        return self::$instance;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    public function router(): Router
    {
        return $this->router;
    }

    private function initialize(): void
    {
        if ($this->booted) {
            return;
        }

        Env::load($this->basePath('.env'));
        Config::loadDirectory($this->basePath('config'));

        // Los errores nunca se muestran al visitante: se registran.
        $debug = (bool) Config::get('app.debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);
        date_default_timezone_set('UTC');
        mb_internal_encoding('UTF-8');

        Logger::configure(
            (string) Config::get('app.log_path', $this->basePath('storage/logs')),
            (string) Config::get('app.log_level', 'info')
        );

        View::setPath($this->basePath('app/Views'));

        $this->router->registerMiddleware([
            'csrf' => \App\Middleware\VerifyCsrf::class,
            'auth' => \App\Middleware\Authenticate::class,
            'admin' => \App\Middleware\RequireAdmin::class,
            'can' => \App\Middleware\RequirePermission::class,
            'api' => \App\Middleware\ApiAuthenticate::class,
            'throttle' => \App\Middleware\ThrottleRequests::class,
            'https' => \App\Middleware\ForceHttps::class,
            'maintenance' => \App\Middleware\DetectMaintenance::class,
            'cors' => \App\Middleware\Cors::class,
        ]);

        $this->registerErrorHandlers();

        $this->booted = true;
    }

    private function registerErrorHandlers(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (\Throwable $e): void {
            $response = $this->renderException($e, Request::capture());
            $response->send();
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();

            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Logger::critical('Error fatal', [
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line'],
                ]);
            }
        });
    }

    /** Carga los archivos de rutas. */
    public function loadRoutes(string ...$files): void
    {
        $router = $this->router;

        foreach ($files as $file) {
            $path = $this->basePath('routes/' . $file);

            if (is_file($path)) {
                require $path;
            }
        }
    }

    public function handle(Request $request): Response
    {
        try {
            Session::start($request);

            // Ajustes del negocio: idioma, zona horaria y datos de marca.
            SettingsService::warmUp();
            Clock::setBusinessTimezone((string) SettingsService::get('business.timezone', 'UTC'));

            $this->shareViewData($request);

            $response = $this->router->dispatch($request);
        } catch (\Throwable $e) {
            $response = $this->renderException($e, $request);
        }

        return SecurityHeaders::apply($response, $request, str_starts_with($request->path(), '/panel'));
    }

    private function shareViewData(Request $request): void
    {
        View::share('request', $request);
        View::share('currentPath', $request->path());
        View::share('authUser', Auth::user());
        View::share('flash', Session::pullFlash());
        View::share('old', Session::pullOldInput());
        View::share('errors', Session::pullErrors());
        View::share('cspNonce', SecurityHeaders::nonce());
    }

    private function renderException(\Throwable $e, Request $request): Response
    {
        $status = $e instanceof HttpException ? $e->statusCode() : 500;
        $debug = (bool) Config::get('app.debug', false);

        if ($status >= 500) {
            Logger::error('Excepcion no controlada', [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $debug ? $e->getTraceAsString() : '(oculto)',
            ]);
        } else {
            Logger::info('Peticion rechazada', [
                'status' => $status,
                'message' => $e->getMessage(),
                'path' => $request->path(),
            ]);
        }

        // Los errores 5xx nunca revelan detalles internos en produccion.
        $message = $status >= 500 && !$debug
            ? 'Ocurrio un error inesperado. Ya estamos trabajando en ello.'
            : $e->getMessage();

        if ($request->wantsJson()) {
            $details = $e instanceof HttpException ? $e->details() : [];

            return Response::apiError($message, $status, $debug && $status >= 500
                ? ['exception' => get_class($e), 'file' => $e->getFile(), 'line' => $e->getLine()]
                : $details);
        }

        try {
            $html = View::render('errors.generic', [
                'status' => $status,
                'message' => $message,
                'trace' => $debug && $status >= 500 ? $e->getTraceAsString() : '',
            ]);
        } catch (\Throwable) {
            $html = '<!doctype html><meta charset="utf-8"><title>Error ' . $status . '</title>'
                . '<h1>Error ' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return Response::html($html, $status);
    }
}
