<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;

/**
 * Excepcion de control para volver al formulario tras un error de validacion.
 * La captura el enrutador en Router::call.
 */
final class ValidationRedirect extends \RuntimeException
{
    private Response $response;

    public function __construct(Response $response)
    {
        parent::__construct('Redireccion por validacion');

        $this->response = $response;
    }

    public function response(): Response
    {
        return $this->response;
    }
}
