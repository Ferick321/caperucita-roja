<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Envoltorio de PDO.
 *
 * Reglas duras del proyecto:
 *  - emulacion de prepares DESACTIVADA (previene inyeccion via placeholders);
 *  - excepciones activadas;
 *  - jamas se concatena entrada de usuario en el SQL: solo parametros ligados.
 */
final class Database
{
    private static ?self $instance = null;

    private PDO $pdo;

    private int $transactions = 0;

    /** @var list<array{sql:string,time:float}> */
    private array $log = [];

    private bool $logQueries = false;

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(self::connect());
        }

        return self::$instance;
    }

    /** Inyecta una conexion ya construida (pruebas). */
    public static function swap(PDO $pdo): void
    {
        self::$instance = new self($pdo);
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private static function connect(): PDO
    {
        $driver = (string) Config::get('database.driver', 'mysql');

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        if ($driver === 'sqlite') {
            $pdo = new PDO('sqlite:' . Config::get('database.database'), null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');

            return $pdo;
        }

        $host = (string) Config::get('database.host', '127.0.0.1');
        $port = (int) Config::get('database.port', 3306);
        $name = (string) Config::get('database.database', '');
        $charset = (string) Config::get('database.charset', 'utf8mb4');
        $socket = (string) Config::get('database.socket', '');

        $dsn = $socket !== ''
            ? "mysql:unix_socket={$socket};dbname={$name};charset={$charset}"
            : "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        if ((bool) Config::get('database.ssl.enabled', false)) {
            $ca = (string) Config::get('database.ssl.ca', '');
            if ($ca !== '') {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
            }
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] =
                (bool) Config::get('database.ssl.verify', true);
        }

        try {
            $pdo = new PDO(
                $dsn,
                (string) Config::get('database.username', ''),
                (string) Config::get('database.password', ''),
                $options
            );
        } catch (PDOException $e) {
            // El mensaje de PDO trae credenciales: nunca se propaga al usuario.
            Logger::critical('Fallo de conexion a la base de datos', ['error' => $e->getMessage()]);

            throw new \RuntimeException('No se pudo conectar con la base de datos.', 0);
        }

        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        $pdo->exec("SET SESSION time_zone = '+00:00'");

        return $pdo;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /** @param array<string|int,mixed> $bindings */
    public function run(string $sql, array $bindings = []): PDOStatement
    {
        $start = microtime(true);

        $statement = $this->pdo->prepare($sql);

        foreach ($bindings as $key => $value) {
            $param = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');

            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };

            $statement->bindValue($param, $value, $type);
        }

        $statement->execute();

        if ($this->logQueries) {
            $this->log[] = ['sql' => $sql, 'time' => microtime(true) - $start];
        }

        return $statement;
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return list<array<string,mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        /** @var list<array<string,mixed>> $rows */
        $rows = $this->run($sql, $bindings)->fetchAll();

        return $rows;
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return array<string,mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string|int,mixed> $bindings */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $value = $this->run($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string|int,mixed> $bindings */
    public function statement(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /** Transacciones anidables mediante savepoints. */
    public function beginTransaction(): void
    {
        if ($this->transactions === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT trans' . ($this->transactions + 1));
        }

        $this->transactions++;
    }

    public function commit(): void
    {
        if ($this->transactions === 1) {
            $this->pdo->commit();
        } elseif ($this->transactions > 1) {
            $this->pdo->exec('RELEASE SAVEPOINT trans' . $this->transactions);
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    public function rollBack(): void
    {
        if ($this->transactions === 1) {
            $this->pdo->rollBack();
        } elseif ($this->transactions > 1) {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactions);
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }
    }

    public function enableQueryLog(bool $enabled = true): void
    {
        $this->logQueries = $enabled;
    }

    /** @return list<array{sql:string,time:float}> */
    public function queryLog(): array
    {
        return $this->log;
    }
}
