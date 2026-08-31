<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Clock;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Logger;

/**
 * Subida de archivos endurecida.
 *
 * Todo lo que sube un cliente (comprobantes de transferencia, fotos de perfil,
 * imagenes de la galeria y banners) pasa por aqui.
 *
 * Controles:
 *  1. el archivo llego por HTTP POST (is_uploaded_file);
 *  2. tamanio maximo configurable;
 *  3. tipo MIME real leido del contenido, no del nombre ni de la cabecera;
 *  4. extension derivada del MIME real, jamas de la que envio el cliente;
 *  5. las imagenes se vuelven a codificar con GD: elimina EXIF, cargas utiles
 *     polyglot (imagenes que tambien son PHP) y metadatos de geolocalizacion;
 *  6. nombre aleatorio y almacenamiento FUERA del directorio publico;
 *  7. permisos 0640 y directorios 0750.
 */
final class FileUploader
{
    private const IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    private const DOCUMENT_MIMES = [
        'application/pdf' => 'pdf',
    ];

    /**
     * @param array<string,mixed> $file entrada de $_FILES
     * @param bool $fromHttpUpload false solo para archivos que la propia
     *        aplicacion escribio en disco (por ejemplo una imagen enviada en
     *        base64 por la app movil), nunca para datos que llegan por HTTP
     *        como archivo adjunto.
     * @return array{path:string,mime:string,size:int,width:int,height:int,original_name:string,hash:string}
     */
    public static function store(
        array $file,
        string $folder,
        bool $allowDocuments = false,
        int $maxWidth = 2000,
        bool $fromHttpUpload = true
    ): array {
        self::assertValidUpload($file, $fromHttpUpload);

        $tmpPath = (string) $file['tmp_name'];
        $size = (int) $file['size'];
        $originalName = self::sanitizeName((string) ($file['name'] ?? 'archivo'));

        $maxBytes = (int) Config::get('uploads.max_bytes', 5 * 1024 * 1024);

        if ($size > $maxBytes) {
            throw new HttpException(422, sprintf(
                'El archivo supera el tamano maximo permitido (%s MB).',
                number_format($maxBytes / 1048576, 1)
            ));
        }

        $mime = self::detectMime($tmpPath);
        $allowed = $allowDocuments
            ? self::IMAGE_MIMES + self::DOCUMENT_MIMES
            : self::IMAGE_MIMES;

        if (!isset($allowed[$mime])) {
            Logger::warning('Subida rechazada por tipo no permitido', ['mime' => $mime, 'name' => $originalName]);

            throw new HttpException(422, 'Formato no permitido. Se aceptan imagenes JPG, PNG, WEBP'
                . ($allowDocuments ? ' o archivos PDF.' : '.'));
        }

        $extension = $allowed[$mime];
        $relativeDir = trim($folder, '/') . '/' . Clock::nowLocal()->format('Y/m');
        $absoluteDir = self::baseDir() . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('No se pudo crear el directorio de almacenamiento.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $absolutePath = $absoluteDir . '/' . $filename;

        $width = 0;
        $height = 0;

        if (isset(self::IMAGE_MIMES[$mime])) {
            [$width, $height] = self::reencodeImage($tmpPath, $absolutePath, $mime, $maxWidth);
        } else {
            self::storePdf($tmpPath, $absolutePath);
        }

        chmod($absolutePath, 0640);

        return [
            'path' => $relativeDir . '/' . $filename,
            'mime' => $mime,
            'size' => (int) (filesize($absolutePath) ?: 0),
            'width' => $width,
            'height' => $height,
            'original_name' => $originalName,
            'hash' => (string) hash_file('sha256', $absolutePath),
        ];
    }

    /** @param array<string,mixed> $file */
    private static function assertValidUpload(array $file, bool $fromHttpUpload = true): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new HttpException(422, match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande.',
                UPLOAD_ERR_PARTIAL => 'La subida se interrumpio; intentalo de nuevo.',
                UPLOAD_ERR_NO_FILE => 'No se selecciono ningun archivo.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar el archivo.',
                default => 'No se pudo procesar el archivo.',
            });
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');

        // is_uploaded_file solo tiene sentido para adjuntos HTTP reales.
        if ($fromHttpUpload && PHP_SAPI !== 'cli' && !is_uploaded_file($tmpPath)) {
            Logger::warning('Intento de subida sin peticion HTTP legitima', ['tmp' => $tmpPath]);

            throw new HttpException(400, 'Subida no valida.');
        }

        if (!is_readable($tmpPath)) {
            throw new HttpException(422, 'No se pudo leer el archivo subido.');
        }
    }

    private static function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return is_string($mime) ? strtolower($mime) : 'application/octet-stream';
    }

    /**
     * Reconstruye la imagen pixel a pixel. El archivo resultante contiene
     * unicamente datos de imagen: cualquier codigo incrustado desaparece.
     *
     * @return array{0:int,1:int}
     */
    private static function reencodeImage(string $source, string $destination, string $mime, int $maxWidth): array
    {
        $info = @getimagesize($source);

        if ($info === false) {
            throw new HttpException(422, 'El archivo no es una imagen valida.');
        }

        [$originalWidth, $originalHeight] = $info;

        // Limite de pixeles: frena las "bombas de descompresion".
        $maxPixels = (int) Config::get('uploads.max_pixels', 40_000_000);

        if ($originalWidth * $originalHeight > $maxPixels) {
            throw new HttpException(422, 'La imagen tiene una resolucion excesiva.');
        }

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            'image/webp' => @imagecreatefromwebp($source),
            'image/gif' => @imagecreatefromgif($source),
            default => false,
        };

        if ($image === false) {
            throw new HttpException(422, 'No se pudo procesar la imagen.');
        }

        $width = $originalWidth;
        $height = $originalHeight;

        if ($maxWidth > 0 && $width > $maxWidth) {
            $height = (int) round($height * ($maxWidth / $width));
            $width = $maxWidth;
        }

        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            imagedestroy($image);

            throw new \RuntimeException('No se pudo crear el lienzo de la imagen.');
        }

        // Conserva la transparencia de PNG/WEBP/GIF.
        if (in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
            }
        }

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);

        $quality = (int) Config::get('uploads.image_quality', 82);

        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($canvas, $destination, $quality),
            'image/png' => imagepng($canvas, $destination, 6),
            'image/webp' => imagewebp($canvas, $destination, $quality),
            'image/gif' => imagegif($canvas, $destination),
            default => false,
        };

        imagedestroy($image);
        imagedestroy($canvas);

        if ($saved === false) {
            throw new \RuntimeException('No se pudo guardar la imagen procesada.');
        }

        return [$width, $height];
    }

    /**
     * Los PDF no se pueden reconstruir con GD, asi que se validan por
     * estructura y se rechazan los que contengan JavaScript o acciones
     * automaticas, que son el vector habitual de PDF maliciosos.
     */
    private static function storePdf(string $source, string $destination): void
    {
        $handle = fopen($source, 'rb');

        if ($handle === false) {
            throw new HttpException(422, 'No se pudo leer el documento.');
        }

        $header = (string) fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            throw new HttpException(422, 'El documento no es un PDF valido.');
        }

        $content = (string) file_get_contents($source);

        foreach (['/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/OpenAction', '/AA'] as $marker) {
            if (stripos($content, $marker) !== false) {
                Logger::warning('PDF rechazado por contenido activo', ['marker' => $marker]);

                throw new HttpException(422, 'El PDF contiene elementos activos y no se admite. '
                    . 'Sube una captura de pantalla en su lugar.');
            }
        }

        if (!copy($source, $destination)) {
            throw new \RuntimeException('No se pudo guardar el documento.');
        }
    }

    public static function sanitizeName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._\- ]/u', '', $name) ?? 'archivo';

        return mb_substr(trim($name) ?: 'archivo', 0, 120);
    }

    public static function baseDir(): string
    {
        return rtrim((string) Config::get('uploads.directory', ''), '/');
    }

    /** Ruta absoluta a partir de la ruta relativa guardada en la base de datos. */
    public static function absolutePath(string $relativePath): ?string
    {
        // Bloquea el salto de directorio: la ruta debe quedar dentro de la base.
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            return null;
        }

        if (preg_match('#^[A-Za-z0-9/_\-]+\.[A-Za-z0-9]{1,5}$#', $relativePath) !== 1) {
            return null;
        }

        $base = self::baseDir();
        $absolute = $base . '/' . $relativePath;
        $real = realpath($absolute);
        $realBase = realpath($base);

        if ($real === false || $realBase === false || !str_starts_with($real, $realBase . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    public static function delete(string $relativePath): bool
    {
        $absolute = self::absolutePath($relativePath);

        return $absolute !== null && @unlink($absolute);
    }

    public static function mimeFor(string $relativePath): string
    {
        return match (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
