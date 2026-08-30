<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Constructor de consultas.
 *
 * Todo valor de usuario viaja como parametro ligado. Los identificadores
 * (tabla, columna, direccion de orden) se validan contra una lista blanca de
 * caracteres y se citan, de modo que no exista ninguna via de inyeccion.
 */
final class QueryBuilder
{
    private string $table;

    /** @var list<string> */
    private array $columns = ['*'];

    /** @var list<array{sql:string,boolean:string}> */
    private array $wheres = [];

    /** @var list<string> */
    private array $joins = [];

    /** @var list<string> */
    private array $groups = [];

    /** @var list<string> */
    private array $havings = [];

    /** @var list<string> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    /** @var array<string,mixed> */
    private array $bindings = [];

    private int $bindingSeq = 0;

    private bool $forUpdate = false;

    public function __construct(string $table)
    {
        $this->table = self::qualify($table);
    }

    public static function table(string $table): self
    {
        return new self($table);
    }

    /** Valida y cita un identificador (tabla, columna, alias). */
    public static function qualify(string $identifier): string
    {
        $identifier = trim($identifier);

        // Soporta "tabla AS alias" y "tabla alias".
        if (preg_match('/^([A-Za-z0-9_.]+)\s+(?:as\s+)?([A-Za-z0-9_]+)$/i', $identifier, $m) === 1) {
            return self::qualify($m[1]) . ' AS ' . self::quoteSegment($m[2]);
        }

        if ($identifier === '*') {
            return '*';
        }

        $parts = explode('.', $identifier);
        $quoted = [];

        foreach ($parts as $part) {
            $quoted[] = $part === '*' ? '*' : self::quoteSegment($part);
        }

        return implode('.', $quoted);
    }

    private static function quoteSegment(string $segment): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1) {
            throw new \InvalidArgumentException("Identificador SQL no valido: {$segment}");
        }

        return '`' . $segment . '`';
    }

    /** @param list<string> $columns */
    public function select(array $columns): self
    {
        $this->columns = array_map(
            static fn (string $c): string => str_contains(strtoupper($c), '(') ? $c : self::qualify($c),
            $columns
        );

        return $this;
    }

    /** Expresion cruda: uso interno unicamente (COUNT(*), SUM(...)). Nunca con datos de usuario. */
    public function selectRaw(string $expression): self
    {
        $this->columns = [$expression];

        return $this;
    }

    private function bind(mixed $value): string
    {
        $name = 'p' . (++$this->bindingSeq);
        $this->bindings[$name] = $value;

        return ':' . $name;
    }

    private const OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];

    public function where(string $column, mixed $operatorOrValue, mixed $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $operator = '=';
            $value = $operatorOrValue;
        } else {
            $operator = strtoupper((string) $operatorOrValue);
        }

        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException("Operador no permitido: {$operator}");
        }

        if ($value === null) {
            return $this->whereNull($column, $operator === '=' ? false : true, $boolean);
        }

        $this->wheres[] = [
            'sql' => self::qualify($column) . ' ' . $operator . ' ' . $this->bind($value),
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        return func_num_args() === 2
            ? $this->where($column, $operatorOrValue, null, 'OR')
            : $this->where($column, $operatorOrValue, $value, 'OR');
    }

    /** @param array<int,mixed> $values */
    public function whereIn(string $column, array $values, bool $not = false, string $boolean = 'AND'): self
    {
        if ($values === []) {
            $this->wheres[] = ['sql' => $not ? '1 = 1' : '1 = 0', 'boolean' => $boolean];

            return $this;
        }

        $placeholders = array_map(fn (mixed $v): string => $this->bind($v), array_values($values));

        $this->wheres[] = [
            'sql' => self::qualify($column) . ($not ? ' NOT IN (' : ' IN (') . implode(', ', $placeholders) . ')',
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNull(string $column, bool $not = false, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'sql' => self::qualify($column) . ($not ? ' IS NOT NULL' : ' IS NULL'),
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        return $this->whereNull($column, true, $boolean);
    }

    public function whereBetween(string $column, mixed $from, mixed $to, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'sql' => self::qualify($column) . ' BETWEEN ' . $this->bind($from) . ' AND ' . $this->bind($to),
            'boolean' => $boolean,
        ];

        return $this;
    }

    /** Agrupa condiciones: ->whereGroup(fn($q) => $q->where(...)->orWhere(...)) */
    public function whereGroup(callable $callback, string $boolean = 'AND'): self
    {
        $nested = new self('placeholder');
        $nested->bindingSeq = $this->bindingSeq + 1000;
        $callback($nested);

        if ($nested->wheres !== []) {
            $this->wheres[] = ['sql' => '(' . $nested->compileWheres(false) . ')', 'boolean' => $boolean];
            $this->bindings += $nested->bindings;
            $this->bindingSeq = max($this->bindingSeq, $nested->bindingSeq);
        }

        return $this;
    }

    /**
     * Busqueda de texto sobre varias columnas (LIKE seguro con escape de comodines).
     *
     * @param list<string> $columns
     */
    public function search(string $term, array $columns): self
    {
        $term = trim($term);

        if ($term === '' || $columns === []) {
            return $this;
        }

        $escaped = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

        return $this->whereGroup(function (self $q) use ($escaped, $columns): void {
            $first = true;
            foreach ($columns as $column) {
                $q->where($column, 'LIKE', $escaped, $first ? 'AND' : 'OR');
                $first = false;
            }
        });
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $type = strtoupper($type);

        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true)) {
            throw new \InvalidArgumentException("Tipo de JOIN no permitido: {$type}");
        }

        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException("Operador no permitido: {$operator}");
        }

        $this->joins[] = sprintf(
            '%s JOIN %s ON %s %s %s',
            $type,
            self::qualify($table),
            self::qualify($first),
            $operator,
            self::qualify($second)
        );

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groups[] = self::qualify($column);
        }

        return $this;
    }

    public function havingRaw(string $expression): self
    {
        $this->havings[] = $expression;

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = self::qualify($column) . ' ' . $direction;

        return $this;
    }

    public function orderByRaw(string $expression): self
    {
        $this->orders[] = $expression;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    public function forUpdate(): self
    {
        $this->forUpdate = true;

        return $this;
    }

    private function compileWheres(bool $withKeyword = true): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $sql = '';

        foreach ($this->wheres as $index => $where) {
            $sql .= $index === 0 ? '' : ' ' . $where['boolean'] . ' ';
            $sql .= $where['sql'];
        }

        return $withKeyword ? ' WHERE ' . $sql : $sql;
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table;

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->compileWheres();

        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        if ($this->havings !== []) {
            $sql .= ' HAVING ' . implode(' AND ', $this->havings);
        }

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        if ($this->forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $sql;
    }

    /** @return array<string,mixed> */
    public function bindings(): array
    {
        return $this->bindings;
    }

    /** @return list<array<string,mixed>> */
    public function get(): array
    {
        return Database::instance()->select($this->toSql(), $this->bindings);
    }

    /** @return array<string,mixed>|null */
    public function first(): ?array
    {
        $this->limit(1);

        return Database::instance()->selectOne($this->toSql(), $this->bindings);
    }

    public function value(string $column): mixed
    {
        $this->select([$column])->limit(1);

        return Database::instance()->scalar($this->toSql(), $this->bindings);
    }

    /** @return list<mixed> */
    public function pluck(string $column): array
    {
        $rows = $this->select([$column])->get();
        $key = self::lastSegment($column);

        return array_map(static fn (array $r): mixed => $r[$key] ?? null, $rows);
    }

    public function count(string $column = '*'): int
    {
        $clone = clone $this;
        $clone->orders = [];
        $clone->limit = null;
        $clone->offset = null;
        $clone->selectRaw('COUNT(' . ($column === '*' ? '*' : self::qualify($column)) . ') AS aggregate');

        return (int) Database::instance()->scalar($clone->toSql(), $clone->bindings);
    }

    public function sum(string $column): float
    {
        $clone = clone $this;
        $clone->orders = [];
        $clone->selectRaw('COALESCE(SUM(' . self::qualify($column) . '), 0) AS aggregate');

        return (float) Database::instance()->scalar($clone->toSql(), $clone->bindings);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /** @param array<string,mixed> $data */
    public function insert(array $data): int
    {
        if ($data === []) {
            throw new \InvalidArgumentException('No hay datos para insertar.');
        }

        $columns = [];
        $placeholders = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $columns[] = self::qualify($column);
            $placeholders[] = ':' . self::lastSegment($column);
            $bindings[self::lastSegment($column)] = $value;
        }

        $sql = 'INSERT INTO ' . $this->table
            . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        $db = Database::instance();
        $db->statement($sql, $bindings);

        return $db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(array $data): int
    {
        if ($data === []) {
            return 0;
        }

        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $name = 'set_' . self::lastSegment($column);
            $sets[] = self::qualify($column) . ' = :' . $name;
            $bindings[$name] = $value;
        }

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets) . $this->compileWheres();

        return Database::instance()->statement($sql, $bindings + $this->bindings);
    }

    public function increment(string $column, int|float $amount = 1): int
    {
        $col = self::qualify($column);
        $sql = 'UPDATE ' . $this->table . ' SET ' . $col . ' = ' . $col . ' + :inc_amount' . $this->compileWheres();

        return Database::instance()->statement($sql, ['inc_amount' => $amount] + $this->bindings);
    }

    public function delete(): int
    {
        if ($this->wheres === []) {
            // Salvaguarda: evita borrar tablas completas por descuido.
            throw new \RuntimeException('DELETE sin condiciones bloqueado por seguridad.');
        }

        $sql = 'DELETE FROM ' . $this->table . $this->compileWheres();

        return Database::instance()->statement($sql, $this->bindings);
    }

    private static function lastSegment(string $column): string
    {
        $parts = explode('.', $column);

        return end($parts) ?: $column;
    }
}
