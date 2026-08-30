<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;
use App\Security\FileUploader;

/**
 * Entrega de archivos subidos.
 *
 * Los archivos viven FUERA del directorio publico, asi que nadie puede
 * pedirlos directamente por URL: pasan por aqui, donde se valida la ruta y,
 * en el caso de los comprobantes de pago, tambien el permiso de quien mira.
 */
final class MediaController extends Controller
{
    public function show(Request $request): Response
    {
        $path = (string) $request->param('path');

        // Los comprobantes contienen datos bancarios del cliente.
        if (str_starts_with($path, 'comprobantes/')) {
            $this->authorizeProof($path);
        }

        $absolute = FileUploader::absolutePath($path);

        if ($absolute === null || !is_readable($absolute)) {
            throw new HttpException(404, 'Archivo no encontrado.');
        }

        $mime = FileUploader::mimeFor($path);

        $response = Response::file($absolute, $mime, basename($path), true);

        // Las imagenes publicas se cachean; los comprobantes nunca.
        if (str_starts_with($path, 'comprobantes/')) {
            $response->header('Cache-Control', 'private, no-store, max-age=0');
        } else {
            $response->header('Cache-Control', 'public, max-age=2592000, immutable');
        }

        return $response;
    }

    private function authorizeProof(string $path): void
    {
        if (!Auth::check()) {
            throw new HttpException(403, 'Necesitas iniciar sesion para ver este archivo.');
        }

        if (Auth::isStaff()) {
            return;
        }

        $ownsProof = QueryBuilder::table('payment_proofs')
            ->join('payments', 'payments.id', '=', 'payment_proofs.payment_id')
            ->where('payment_proofs.file_path', $path)
            ->where('payments.client_id', (int) Auth::id())
            ->exists();

        if (!$ownsProof) {
            throw new HttpException(403, 'No tienes acceso a este archivo.');
        }
    }
}
