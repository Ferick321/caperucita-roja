<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Modelo base ligero (Active Record acotado).
 *
 * `$fillable` es una lista blanca obligatoria: cualquier campo que llegue del
 * exterior y no este listado se descarta antes de tocar la base de datos, de
 * modo que no exista asignacion masiva de columnas sensibles.
 */
abstract class Model
{
    protected static string $table = '';

    /** @var list<string> */
    protected static array $fillable = [];

    protected static bool $timestamps = true;

    protected static bool $softDeletes = false;

    public static function table(): string
    {
        return static::$table;
    }

    public static function query(bool $withTrashed = false): QueryBuilder
    {
        $query = QueryBuilder::table(static::$table);

        if (static::$softDeletes && !$withTrashed) {
            $query->whereNull(static::$table . '.deleted_at');
        }

        return $query;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id, bool $withTrashed = false): ?array
    {
        return static::query($withTrashed)->where(static::$table . '.id', $id)->first();
    }

    /** @return array<string,mixed>|null */
    public static function findBy(string $column, mixed $value, bool $withTrashed = false): ?array
    {
        return static::query($withTrashed)->where(static::$table . '.' . $column, $value)->first();
    }

    /** @return list<array<string,mixed>> */
    public static function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        return static::query()->orderBy(static::$table . '.' . $orderBy, $direction)->get();
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public static function create(array $attributes): int
    {
        $data = static::filter($attributes);

        if (static::$timestamps) {
            $now = Clock::nowUtc();
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        return QueryBuilder::table(static::$table)->insert($data);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public static function update(int $id, array $attributes): int
    {
        $data = static::filter($attributes);

        if ($data === []) {
            return 0;
        }

        if (static::$timestamps) {
            $data['updated_at'] = Clock::nowUtc();
        }

        return QueryBuilder::table(static::$table)->where('id', $id)->update($data);
    }

    /** Borrado logico si el modelo lo soporta, fisico en caso contrario. */
    public static function delete(int $id): int
    {
        if (static::$softDeletes) {
            return QueryBuilder::table(static::$table)
                ->where('id', $id)
                ->update(['deleted_at' => Clock::nowUtc()]);
        }

        return QueryBuilder::table(static::$table)->where('id', $id)->delete();
    }

    /** Borrado definitivo: la fila desaparece de la tabla. */
    public static function forceDelete(int $id): int
    {
        return QueryBuilder::table(static::$table)->where('id', $id)->delete();
    }

    public static function restore(int $id): int
    {
        return QueryBuilder::table(static::$table)
            ->where('id', $id)
            ->update(['deleted_at' => null]);
    }

    /**
     * @param array<string,mixed> $attributes
     * @return array<string,mixed>
     */
    protected static function filter(array $attributes): array
    {
        if (static::$fillable === []) {
            return $attributes;
        }

        return array_intersect_key($attributes, array_flip(static::$fillable));
    }

    /**
     * Paginacion simple.
     *
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public static function paginate(QueryBuilder $query, int $page, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $total = $query->count();

        $data = $query->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / $perPage),
        ];
    }
}
