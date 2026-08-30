<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

/** Base comun de todos los controladores. */
abstract class Controller
{
    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(View::render($template, $data), $status);
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function back(Request $request, string $fallback = '/'): Response
    {
        $referer = (string) $request->header('referer', '');

        return Response::redirect($referer !== '' ? $referer : $fallback);
    }

    /**
     * Valida la entrada. Si falla, en peticiones normales devuelve al
     * formulario con los errores y los datos escritos; en JSON lanza 422.
     *
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     * @return array<string,mixed>
     */
    protected function validate(Request $request, array $rules, array $labels = []): array
    {
        $validator = Validator::make($request->all(), $rules, $labels);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                throw new HttpException(422, $validator->firstError(), $validator->errors());
            }

            Session::flashInput($request->all());
            Session::flashErrors($validator->errors());
            Session::error($validator->firstError());

            throw new ValidationRedirect($this->back($request));
        }

        return $validator->validated();
    }

    /**
     * Defensa anti-bot para formularios publicos: campo trampa invisible y
     * tiempo minimo de cumplimentacion.
     */
    protected function assertNotBot(Request $request, int $minSeconds = 2): void
    {
        if ($request->string('website_url') !== '') {
            throw new HttpException(422, 'No pudimos procesar el formulario.');
        }

        $renderedAt = $request->int('form_rendered_at');

        if ($renderedAt > 0 && (time() - $renderedAt) < $minSeconds) {
            throw new HttpException(422, 'El formulario se envio demasiado rapido. Intentalo de nuevo.');
        }
    }
}
