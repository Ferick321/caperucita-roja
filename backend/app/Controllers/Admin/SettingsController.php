<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Security\Audit;
use App\Security\Auth;
use App\Services\MediaService;
use App\Services\SettingsService;

/**
 * Ajustes globales del sistema.
 *
 * Este es el modulo que hace realidad el "todo configurable sin tocar codigo":
 * cada ajuste sabe de que tipo es y el panel pinta el control adecuado
 * (texto, color, interruptor, imagen, lista desplegable...).
 */
final class SettingsController extends AdminController
{
    /** Grupos con su nombre y descripcion para la navegacion del panel. */
    private const GROUPS = [
        'business' => ['Negocio', 'Nombre, contacto, direccion, moneda y zona horaria'],
        'theme' => ['Apariencia', 'Colores, tipografias y estilo de la web y la app'],
        'booking' => ['Reservas', 'Reglas de agendamiento, antelacion y cancelaciones'],
        'payments' => ['Pagos', 'Metodos, abonos y comprobantes'],
        'ads' => ['Publicidad', 'Cuando y como se muestran los anuncios'],
        'app' => ['App movil', 'Enlaces de descarga, version y pantalla de bienvenida'],
        'notifications' => ['Avisos', 'Correos, recordatorios y solicitudes de resena'],
        'loyalty' => ['Fidelidad', 'Puntos por compra y beneficios'],
        'seo' => ['Buscadores', 'Titulos, descripciones y analitica'],
        'social' => ['Redes sociales', 'Enlaces a tus perfiles'],
        'legal' => ['Legal', 'Privacidad, terminos y aviso de cookies'],
        'push' => ['Notificaciones push', 'Credenciales del servicio de mensajeria'],
        'system' => ['Sistema', 'Mantenimiento y limpieza automatica'],
    ];

    public function index(Request $request): Response
    {
        $this->authorize('ajustes.ver');

        return $this->redirect('/panel/ajustes/business');
    }

    public function group(Request $request): Response
    {
        $this->authorize('ajustes.ver');

        $group = (string) $request->param('group');

        if (!isset(self::GROUPS[$group])) {
            throw new HttpException(404, 'Ese grupo de ajustes no existe.');
        }

        return $this->view('admin.settings.index', [
            'group' => $group,
            'groups' => self::GROUPS,
            'groupLabel' => self::GROUPS[$group][0],
            'groupHelp' => self::GROUPS[$group][1],
            'settings' => SettingsService::group($group),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request): Response
    {
        $this->authorize('ajustes.editar');

        $group = (string) $request->param('group');

        if (!isset(self::GROUPS[$group])) {
            throw new HttpException(404, 'Ese grupo de ajustes no existe.');
        }

        $definitions = SettingsService::group($group);
        $userId = Auth::id();
        $before = [];
        $after = [];

        foreach ($definitions as $definition) {
            $key = (string) $definition['setting_key'];
            $type = (string) $definition['value_type'];
            // El nombre del campo usa guion bajo porque el punto no viaja bien en formularios.
            $field = str_replace('.', '__', $key);

            $currentValue = SettingsService::get($key);

            if ($type === 'image') {
                $value = $this->handleImageField($request, $field, (string) ($currentValue ?? ''), $userId);
            } elseif ($type === 'bool') {
                $value = $request->bool($field);
            } else {
                $value = $this->sanitizeValue($request, $field, $type);
            }

            if ($value === null) {
                continue;
            }

            if ((string) $currentValue !== (string) (is_bool($value) ? ($value ? '1' : '0') : $value)) {
                $before[$key] = $currentValue;
                $after[$key] = $value;
            }

            SettingsService::set($key, $value, $userId);
        }

        SettingsService::flushCache();

        if ($after !== []) {
            Audit::record('ajustes.actualizados', 'settings', null, $before, $after, $request);
        }

        Session::success('Ajustes guardados. Los cambios ya estan activos en la web y en la app.');

        return $this->redirect('/panel/ajustes/' . $group);
    }

    /**
     * Sanea el valor segun su tipo declarado.
     * Devolver null significa "no tocar este ajuste".
     */
    private function sanitizeValue(Request $request, string $field, string $type): mixed
    {
        if (!$request->has($field)) {
            return null;
        }

        return match ($type) {
            'int' => $request->int($field),
            'float' => $request->float($field),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $request->string($field)) === 1
                ? strtolower($request->string($field))
                : null,
            'email' => filter_var($request->string($field), FILTER_VALIDATE_EMAIL) !== false
                ? mb_strtolower($request->string($field))
                : ($request->string($field) === '' ? '' : null),
            'url' => $this->sanitizeUrl($request->string($field)),
            'html' => $this->sanitizeHtml($request->string($field)),
            'json' => $this->sanitizeJson($request->string($field)),
            'text' => mb_substr($request->string($field), 0, 20000),
            default => mb_substr($request->string($field), 0, 1000),
        };
    }

    private function sanitizeUrl(string $value): ?string
    {
        if ($value === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $value;
    }

    /**
     * Contenido enriquecido de textos legales: se admite formato basico pero
     * se eliminan scripts, marcos y atributos de evento.
     */
    private function sanitizeHtml(string $value): string
    {
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4>'
            . '<a><blockquote><hr><table><thead><tbody><tr><th><td><span>';

        $clean = strip_tags($value, $allowed);
        $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
        $clean = preg_replace('/(href|src)\s*=\s*("|\')\s*(javascript|data|vbscript):/i', '$1=$2#', $clean) ?? '';

        return mb_substr($clean, 0, 40000);
    }

    private function sanitizeJson(string $value): ?string
    {
        if (trim($value) === '') {
            return '';
        }

        json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $value : null;
    }

    /** Sube una imagen nueva, o borra la actual si se marco la casilla. */
    private function handleImageField(Request $request, string $field, string $current, ?int $userId): ?string
    {
        if ($request->bool($field . '__remove')) {
            if ($current !== '') {
                \App\Security\FileUploader::delete($current);
            }

            return '';
        }

        if (!$request->hasFile($field)) {
            return null;
        }

        return MediaService::replace(
            $current,
            (array) $request->file($field),
            'ajustes',
            $userId,
            str_contains($field, 'favicon') ? 256 : 1600
        );
    }

    // ---- Gestion de las plantillas de aviso ------------------------------

    public function templates(Request $request): Response
    {
        $this->authorize('ajustes.ver');

        return $this->view('admin.settings.templates', [
            'templates' => QueryBuilder::table('notification_templates')->orderBy('template_key')->get(),
        ]);
    }

    public function updateTemplate(Request $request): Response
    {
        $this->authorize('ajustes.editar');

        $id = $request->paramInt('id');
        $template = QueryBuilder::table('notification_templates')->where('id', $id)->first();

        if ($template === null) {
            throw new HttpException(404, 'La plantilla no existe.');
        }

        $data = $this->validate($request, [
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:40000',
        ], ['subject' => 'asunto', 'body' => 'contenido']);

        QueryBuilder::table('notification_templates')->where('id', $id)->update([
            'subject' => $data['subject'],
            'body' => $this->sanitizeHtml((string) $data['body']),
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('plantilla.actualizada', 'notification_template', $id, null, ['key' => $template['template_key']], $request);
        Session::success('Plantilla actualizada.');

        return $this->redirect('/panel/ajustes/plantillas');
    }
}
