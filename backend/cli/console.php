<?php

declare(strict_types=1);

/**
 * Consola de administracion.
 *
 *   php cli/console.php <comando> [opciones]
 *
 * Comandos disponibles: ver "ayuda".
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Esta herramienta solo se ejecuta desde la linea de comandos.\n");
}

/** @var App\Core\App $app */
$app = require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Clock;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Migrator;
use App\Security\Crypto;
use App\Security\Hash;
use App\Services\MaintenanceService;
use App\Services\QueueWorker;
use App\Services\SettingsService;
use Database\Seeds\InitialSeeder;

$command = $argv[1] ?? 'ayuda';
$options = [];

foreach (array_slice($argv, 2) as $argument) {
    if (preg_match('/^--([a-z0-9_-]+)(?:=(.*))?$/i', $argument, $matches) === 1) {
        $options[$matches[1]] = $matches[2] ?? '1';
    }
}

function out(string $line = ''): void
{
    echo $line . PHP_EOL;
}

function title(string $text): void
{
    out();
    out('== ' . $text . ' ' . str_repeat('=', max(0, 58 - mb_strlen($text))));
}

function prompt(string $question, bool $hidden = false): string
{
    echo $question . ': ';

    if ($hidden && DIRECTORY_SEPARATOR !== '\\') {
        system('stty -echo 2>/dev/null');
        $value = trim((string) fgets(STDIN));
        system('stty echo 2>/dev/null');
        echo PHP_EOL;

        return $value;
    }

    return trim((string) fgets(STDIN));
}

try {
    switch ($command) {
        // ------------------------------------------------------------------
        case 'ayuda':
        case 'help':
            title('Plataforma Estilo - consola');
            out();
            out('  instalar                Instalacion guiada completa (migraciones + datos + admin)');
            out('  migrate                 Aplica las migraciones pendientes');
            out('  migrate:estado          Muestra las migraciones pendientes');
            out('  seed                    Carga los datos iniciales');
            out('  key:generate            Genera APP_KEY y JWT_SECRET para el archivo .env');
            out('  usuario:crear           Crea una cuenta de personal');
            out('  usuario:clave           Cambia la contrasena de una cuenta');
            out('  cola:procesar           Envia los mensajes pendientes de la cola');
            out('  cron                    Tarea periodica (cola, recordatorios, campanas, ausencias)');
            out('  mantenimiento           Aplica las politicas de retencion de datos');
            out('  mantenimiento:archivos  Elimina archivos huerfanos del disco');
            out('  mantenimiento:optimizar Compacta las tablas y libera espacio');
            out('  espacio                 Informe de uso de base de datos y archivos');
            out('  diagnostico             Comprueba requisitos y configuracion');
            out();
            out('Ejemplos:');
            out('  php cli/console.php instalar --email=admin@mibarberia.com');
            out('  php cli/console.php cron       # programar cada 5 minutos');
            out();
            break;

        // ------------------------------------------------------------------
        case 'instalar':
            title('Instalacion');

            if ((string) Config::get('app.key', '') === '') {
                out('  ! Falta APP_KEY. Ejecuta primero: php cli/console.php key:generate');
                exit(1);
            }

            out('Aplicando migraciones...');
            foreach ((new Migrator(dirname(__DIR__) . '/database/migrations'))->run() as $line) {
                out($line);
            }

            $email = $options['email'] ?? prompt('Correo del administrador');
            $password = $options['password'] ?? prompt('Contrasena del administrador', true);

            if ($password === '') {
                $password = bin2hex(random_bytes(8));
                out('  Se genero una contrasena automatica: ' . $password);
            }

            out('Cargando datos iniciales...');
            foreach ((new InitialSeeder())->run($email, $password) as $line) {
                out($line);
            }

            SettingsService::set('system.installed_at', Clock::nowUtc());

            out();
            out('  Instalacion completada.');
            out('  Panel: ' . Config::get('app.url') . '/panel');
            out('  Recuerda programar la tarea: */5 * * * * php ' . dirname(__DIR__) . '/cli/console.php cron');
            out();
            break;

        // ------------------------------------------------------------------
        case 'migrate':
            title('Migraciones');
            foreach ((new Migrator(dirname(__DIR__) . '/database/migrations'))->run() as $line) {
                out($line);
            }
            break;

        case 'migrate:estado':
            title('Migraciones pendientes');
            $pending = (new Migrator(dirname(__DIR__) . '/database/migrations'))->pending();

            if ($pending === []) {
                out('  Todo al dia.');
            } else {
                foreach ($pending as $file) {
                    out('  - ' . $file);
                }
            }
            break;

        // ------------------------------------------------------------------
        case 'seed':
            title('Datos iniciales');
            $email = $options['email'] ?? prompt('Correo del administrador');
            $password = $options['password'] ?? prompt('Contrasena del administrador', true);

            foreach ((new InitialSeeder())->run($email, $password) as $line) {
                out($line);
            }
            break;

        // ------------------------------------------------------------------
        case 'key:generate':
            title('Claves de la aplicacion');
            out();
            out('Copia estas lineas en tu archivo .env:');
            out();
            out('APP_KEY=' . Crypto::generateKey());
            out('JWT_SECRET=' . bin2hex(random_bytes(32)));
            out('PASSWORD_PEPPER=' . bin2hex(random_bytes(32)));
            out();
            out('AVISO: cambiar PASSWORD_PEPPER invalida todas las contrasenas existentes.');
            out('       Cambiar APP_KEY impide descifrar los datos bancarios guardados.');
            out();
            break;

        // ------------------------------------------------------------------
        case 'usuario:crear':
            title('Nueva cuenta de personal');
            $email = $options['email'] ?? prompt('Correo');
            $name = $options['nombre'] ?? prompt('Nombre');
            $role = $options['rol'] ?? prompt('Rol (super_admin, admin, manager, staff)');
            $password = $options['password'] ?? prompt('Contrasena', true);

            if (!in_array($role, ['super_admin', 'admin', 'manager', 'staff'], true)) {
                out('  ! Rol no valido.');
                exit(1);
            }

            if (App\Core\QueryBuilder::table('users')->where('email', mb_strtolower($email))->exists()) {
                out('  ! Ya existe una cuenta con ese correo.');
                exit(1);
            }

            App\Core\QueryBuilder::table('users')->insert([
                'uuid' => InitialSeeder::uuid4(),
                'role' => $role,
                'first_name' => $name,
                'email' => mb_strtolower($email),
                'email_verified_at' => Clock::nowUtc(),
                'password_hash' => Hash::make($password),
                'password_changed_at' => Clock::nowUtc(),
                'status' => 'active',
                'referral_code' => strtoupper(bin2hex(random_bytes(4))),
                'source' => 'consola',
                'created_at' => Clock::nowUtc(),
                'updated_at' => Clock::nowUtc(),
            ]);

            out('  Cuenta creada: ' . $email . ' (' . $role . ')');
            break;

        // ------------------------------------------------------------------
        case 'usuario:clave':
            title('Cambio de contrasena');
            $email = $options['email'] ?? prompt('Correo');
            $password = $options['password'] ?? prompt('Nueva contrasena', true);

            $affected = App\Core\QueryBuilder::table('users')
                ->where('email', mb_strtolower($email))
                ->update([
                    'password_hash' => Hash::make($password),
                    'password_changed_at' => Clock::nowUtc(),
                    'failed_logins' => 0,
                    'locked_until' => null,
                    // Cierra las sesiones abiertas en la app movil.
                    'tokens_valid_after' => Clock::nowUtc(),
                    'updated_at' => Clock::nowUtc(),
                ]);

            out($affected > 0 ? '  Contrasena actualizada.' : '  ! No se encontro esa cuenta.');
            break;

        // ------------------------------------------------------------------
        case 'cola:procesar':
            $result = QueueWorker::process((int) ($options['limite'] ?? 100));
            out(sprintf(
                '  Procesados: %d | enviados: %d | fallidos: %d',
                $result['processed'],
                $result['sent'],
                $result['failed']
            ));
            break;

        // ------------------------------------------------------------------
        case 'cron':
            // Pensado para ejecutarse cada 5 minutos.
            $queue = QueueWorker::process(100);
            $campaigns = QueueWorker::releaseScheduledCampaigns();
            $noShows = QueueWorker::markNoShows(60);

            $summary = sprintf(
                'cola=%d/%d campanas=%d ausencias=%d',
                $queue['sent'],
                $queue['processed'],
                $campaigns,
                $noShows
            );

            // La limpieza pesada se ejecuta una vez al dia, de madrugada.
            $hour = (int) Clock::nowLocal()->format('G');
            $minute = (int) Clock::nowLocal()->format('i');

            if ($hour === 3 && $minute < 5 && SettingsService::bool('system.auto_purge_enabled', true)) {
                $retention = MaintenanceService::runRetentionPolicies();
                $orphans = MaintenanceService::cleanOrphanFiles();
                Logger::purgeOlderThan(30);

                $summary .= sprintf(
                    ' limpieza=%d_filas/%d_archivos',
                    $retention['total_rows'],
                    $orphans['files']
                );
            }

            out('  ' . $summary);
            Logger::info('Tarea programada ejecutada', ['resumen' => $summary]);
            break;

        // ------------------------------------------------------------------
        case 'mantenimiento':
            title('Politicas de retencion');
            $dryRun = isset($options['simular']);
            $result = MaintenanceService::runRetentionPolicies(null, $dryRun);

            foreach ($result['policies'] as $policy) {
                out(sprintf(
                    '  %-26s %6d filas  %4d archivos  (%d dias)%s',
                    $policy['policy'],
                    $policy['rows_deleted'],
                    $policy['files_deleted'],
                    $policy['retention_days'],
                    $policy['error'] !== null ? '  ERROR: ' . $policy['error'] : ''
                ));
            }

            out();
            out(sprintf(
                '  Total: %d filas, %d archivos, %s liberados%s',
                $result['total_rows'],
                $result['total_files'],
                MaintenanceService::formatBytes($result['bytes_freed']),
                $dryRun ? '  (SIMULACION: no se borro nada)' : ''
            ));
            break;

        case 'mantenimiento:archivos':
            title('Archivos huerfanos');
            $dryRun = isset($options['simular']);
            $result = MaintenanceService::cleanOrphanFiles($dryRun);

            out(sprintf(
                '  %d archivos, %s%s',
                $result['files'],
                MaintenanceService::formatBytes($result['bytes']),
                $dryRun ? ' (SIMULACION)' : ' eliminados'
            ));

            foreach (array_slice($result['paths'], 0, 20) as $path) {
                out('    - ' . $path);
            }
            break;

        case 'mantenimiento:optimizar':
            title('Optimizacion de tablas');
            foreach (MaintenanceService::optimizeTables() as $table => $status) {
                out(sprintf('  %-30s %s', $table, $status));
            }
            break;

        // ------------------------------------------------------------------
        case 'espacio':
            title('Uso de espacio');
            $usage = MaintenanceService::databaseUsage();
            $totalData = 0.0;
            $totalFree = 0.0;

            out(sprintf('  %-30s %10s %10s %10s %10s', 'Tabla', 'Filas', 'Datos MB', 'Indice MB', 'Libre MB'));
            out('  ' . str_repeat('-', 74));

            foreach (array_slice($usage, 0, 25) as $row) {
                out(sprintf(
                    '  %-30s %10d %10.2f %10.2f %10.2f',
                    $row['table'],
                    $row['rows'],
                    $row['data_mb'],
                    $row['index_mb'],
                    $row['free_mb']
                ));

                $totalData += $row['data_mb'] + $row['index_mb'];
                $totalFree += $row['free_mb'];
            }

            $storage = MaintenanceService::storageUsage();

            out();
            out(sprintf('  Base de datos: %.2f MB usados, %.2f MB recuperables', $totalData, $totalFree));
            out(sprintf(
                '  Archivos subidos: %d archivos, %s',
                $storage['files'],
                MaintenanceService::formatBytes($storage['bytes'])
            ));
            break;

        // ------------------------------------------------------------------
        case 'diagnostico':
            title('Diagnostico del sistema');

            $checks = [
                'PHP 8.2 o superior' => PHP_VERSION_ID >= 80200,
                'Extension pdo_mysql' => extension_loaded('pdo_mysql'),
                'Extension mbstring' => extension_loaded('mbstring'),
                'Extension gd (imagenes)' => extension_loaded('gd'),
                'Extension sodium (cifrado)' => extension_loaded('sodium'),
                'Extension fileinfo' => extension_loaded('fileinfo'),
                'Extension openssl' => extension_loaded('openssl'),
                'Argon2id disponible' => in_array('argon2id', password_algos(), true),
                'APP_KEY configurada' => (string) Config::get('app.key', '') !== '',
                'JWT_SECRET configurado' => (string) Config::get('security.jwt.secret', '') !== '',
                'PASSWORD_PEPPER configurado' => (string) Config::get('security.password.pepper', '') !== '',
                'Modo depuracion apagado' => !Config::get('app.debug', false),
                'HTTPS forzado' => (bool) Config::get('app.force_https', false),
                'Carpeta de subidas escribible' => is_writable((string) Config::get('uploads.directory', '')),
                'Carpeta de registros escribible' => is_writable((string) Config::get('app.log_path', '')),
                'Carpeta de sesiones escribible' => is_writable((string) Config::get('session.path', '')),
            ];

            try {
                Database::instance()->scalar('SELECT 1');
                $checks['Conexion a la base de datos'] = true;
            } catch (Throwable) {
                $checks['Conexion a la base de datos'] = false;
            }

            $failures = 0;

            foreach ($checks as $label => $passed) {
                out(sprintf('  [%s] %s', $passed ? 'OK' : '!!', $label));
                $failures += $passed ? 0 : 1;
            }

            out();
            out($failures === 0
                ? '  Todo correcto.'
                : sprintf('  %d comprobacion(es) requieren atencion.', $failures));

            if ($failures > 0) {
                exit(1);
            }
            break;

        // ------------------------------------------------------------------
        default:
            out('Comando desconocido: ' . $command);
            out('Usa "php cli/console.php ayuda" para ver la lista.');
            exit(1);
    }
} catch (Throwable $e) {
    out();
    out('  ERROR: ' . $e->getMessage());

    if (Config::get('app.debug', false)) {
        out($e->getTraceAsString());
    }

    Logger::error('Fallo en la consola', ['command' => $command, 'error' => $e->getMessage()]);
    exit(1);
}
