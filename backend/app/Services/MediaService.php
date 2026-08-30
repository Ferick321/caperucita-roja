<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\QueryBuilder;
use App\Security\Audit;
use App\Security\FileUploader;

/** Biblioteca de medios: registra cada archivo para poder auditarlo y limpiarlo. */
final class MediaService
{
    /**
     * @param array<string,mixed> $file entrada de $_FILES
     * @return array<string,mixed> fila de media creada
     */
    public static function store(
        array $file,
        string $folder = 'general',
        ?int $userId = null,
        string $altText = '',
        int $maxWidth = 1600
    ): array {
        $folder = preg_replace('/[^a-z0-9_-]/i', '', $folder) ?: 'general';
        $stored = FileUploader::store($file, $folder, false, $maxWidth);

        // Un archivo identico ya subido se reutiliza en lugar de duplicarse.
        $existing = QueryBuilder::table('media')
            ->where('file_hash', $stored['hash'])
            ->whereNull('deleted_at')
            ->first();

        if ($existing !== null) {
            FileUploader::delete($stored['path']);
            QueryBuilder::table('media')->where('id', (int) $existing['id'])->increment('usage_count');

            return $existing;
        }

        $id = QueryBuilder::table('media')->insert([
            'file_path' => $stored['path'],
            'file_mime' => $stored['mime'],
            'file_size' => $stored['size'],
            'file_hash' => $stored['hash'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'original_name' => $stored['original_name'],
            'alt_text' => mb_substr($altText, 0, 255),
            'folder' => $folder,
            'uploaded_by' => $userId,
            'usage_count' => 1,
            'created_at' => Clock::nowUtc(),
        ]);

        Audit::record('media.subida', 'media', $id, null, ['path' => $stored['path'], 'size' => $stored['size']]);

        return QueryBuilder::table('media')->where('id', $id)->first() ?? [];
    }

    /** Borrado definitivo del registro y del archivo en disco. */
    public static function delete(int $mediaId): bool
    {
        $media = QueryBuilder::table('media')->where('id', $mediaId)->first();

        if ($media === null) {
            return false;
        }

        FileUploader::delete((string) $media['file_path']);
        QueryBuilder::table('media')->where('id', $mediaId)->delete();

        Audit::record('media.eliminada', 'media', $mediaId, ['path' => (string) $media['file_path']], null);

        return true;
    }

    /**
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public static function paginate(int $page = 1, string $folder = '', string $search = ''): array
    {
        $query = QueryBuilder::table('media')->whereNull('deleted_at');

        if ($folder !== '') {
            $query->where('folder', $folder);
        }

        if ($search !== '') {
            $query->search($search, ['original_name', 'alt_text']);
        }

        $query->orderBy('created_at', 'DESC');

        return \App\Core\Model::paginate($query, $page, 24);
    }

    /**
     * Sube una imagen y devuelve solo su ruta relativa.
     * Es el atajo que usan los formularios del panel.
     */
    public static function storePath(
        array $file,
        string $folder,
        ?int $userId = null,
        int $maxWidth = 1600
    ): string {
        $media = self::store($file, $folder, $userId, '', $maxWidth);

        return (string) ($media['file_path'] ?? '');
    }

    /** Reemplaza una imagen liberando la anterior si ya nadie la usa. */
    public static function replace(
        string $currentPath,
        array $file,
        string $folder,
        ?int $userId = null,
        int $maxWidth = 1600
    ): string {
        $newPath = self::storePath($file, $folder, $userId, $maxWidth);

        if ($currentPath !== '' && $currentPath !== $newPath) {
            $media = QueryBuilder::table('media')->where('file_path', $currentPath)->first();

            if ($media !== null && (int) $media['usage_count'] <= 1) {
                self::delete((int) $media['id']);
            } else {
                FileUploader::delete($currentPath);
            }
        }

        return $newPath;
    }
}
