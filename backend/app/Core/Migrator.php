<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Ejecutor de migraciones SQL.
 *
 * Cada archivo .sql de /database/migrations se aplica una sola vez y queda
 * registrado con su hash, de modo que una migracion ya aplicada que cambie
 * se detecta y se avisa en lugar de corromper el esquema en silencio.
 */
final class Migrator
{
    private string $directory;

    private Database $db;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/');
        $this->db = Database::instance();
    }

    private function ensureTable(): void
    {
        $this->db->statement(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                filename VARCHAR(191) NOT NULL,
                checksum CHAR(64) NOT NULL,
                batch INT UNSIGNED NOT NULL,
                applied_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_migrations_filename (filename)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return list<string> mensajes de la ejecucion */
    public function run(): array
    {
        $this->ensureTable();

        $applied = [];
        foreach ($this->db->select('SELECT filename, checksum FROM migrations') as $row) {
            $applied[(string) $row['filename']] = (string) $row['checksum'];
        }

        $batch = (int) $this->db->scalar('SELECT COALESCE(MAX(batch), 0) FROM migrations') + 1;

        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $messages = [];

        foreach ($files as $file) {
            $name = basename($file);
            $sql = (string) file_get_contents($file);
            $checksum = hash('sha256', $sql);

            if (isset($applied[$name])) {
                if (!hash_equals($applied[$name], $checksum)) {
                    $messages[] = "  ! {$name}: el archivo cambio despues de aplicarse (revisar manualmente).";
                }

                continue;
            }

            foreach (self::splitStatements($sql) as $statement) {
                $this->db->statement($statement);
            }

            $this->db->statement(
                'INSERT INTO migrations (filename, checksum, batch, applied_at) VALUES (:f, :c, :b, :a)',
                ['f' => $name, 'c' => $checksum, 'b' => $batch, 'a' => Clock::nowUtc()]
            );

            $messages[] = "  + {$name}";
        }

        if ($messages === []) {
            $messages[] = '  = Sin migraciones pendientes.';
        }

        return $messages;
    }

    /**
     * Divide un archivo SQL en sentencias respetando cadenas, comentarios y
     * bloques delimitados por DELIMITER (disparadores y procedimientos).
     *
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $delimiter = ';';
        $length = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $buffer .= $char;
                }

                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }

                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick) {
                if (($char === '-' && $next === '-') || $char === '#') {
                    $inLineComment = true;

                    continue;
                }

                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;

                    continue;
                }

                // Sentencia DELIMITER (fuera del SQL estandar).
                if (($buffer === '' || str_ends_with($buffer, "\n"))
                    && strtoupper(substr($sql, $i, 10)) === 'DELIMITER '
                ) {
                    $eol = strpos($sql, "\n", $i);
                    $eol = $eol === false ? $length : $eol;
                    $delimiter = trim(substr($sql, $i + 10, $eol - $i - 10));
                    $i = $eol;

                    continue;
                }
            }

            if ($char === "'" && !$inDouble && !$inBacktick && ($sql[$i - 1] ?? '') !== '\\') {
                $inSingle = !$inSingle;
            } elseif ($char === '"' && !$inSingle && !$inBacktick && ($sql[$i - 1] ?? '') !== '\\') {
                $inDouble = !$inDouble;
            } elseif ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            if (!$inSingle && !$inDouble && !$inBacktick
                && substr($sql, $i, strlen($delimiter)) === $delimiter
            ) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                $i += strlen($delimiter) - 1;

                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    /** @return list<string> */
    public function pending(): array
    {
        $this->ensureTable();

        $applied = $this->db->select('SELECT filename FROM migrations');
        $appliedNames = array_map(static fn (array $r): string => (string) $r['filename'], $applied);

        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $pending = [];
        foreach ($files as $file) {
            if (!in_array(basename($file), $appliedNames, true)) {
                $pending[] = basename($file);
            }
        }

        return $pending;
    }
}
