<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Model;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Security\Audit;
use App\Security\Auth;
use App\Services\CampaignService;
use App\Services\MediaService;

/** Campanas de publicidad hacia los clientes registrados. */
final class CampaignController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->authorize('campanas.ver');

        $query = QueryBuilder::table('campaigns')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC');

        return $this->view('admin.campaigns.index', [
            'result' => Model::paginate($query, $this->page($request), 20),
            'audienceSizes' => $this->audienceSizes(),
        ]);
    }

    public function form(Request $request): Response
    {
        $this->authorize('campanas.ver');

        $id = $request->paramInt('id');
        $campaign = $id > 0 ? QueryBuilder::table('campaigns')->where('id', $id)->first() : null;

        if ($id > 0 && $campaign === null) {
            throw new HttpException(404, 'La campana no existe.');
        }

        return $this->view('admin.campaigns.form', [
            'campaign' => $campaign,
            'audienceSizes' => $this->audienceSizes(),
            'recipients' => $id > 0
                ? QueryBuilder::table('campaign_recipients')
                    ->where('campaign_id', $id)->orderBy('id')->limit(50)->get()
                : [],
        ]);
    }

    public function save(Request $request): Response
    {
        $this->authorize('campanas.ver');

        $id = $request->paramInt('id');

        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:160|no_html',
            'channel' => 'required|in:email,sms,push,whatsapp',
            'subject' => 'optional|string|max:200|no_html',
            'body' => 'required|string|min:10|max:20000',
            'cta_label' => 'optional|string|max:80|no_html',
            'cta_url' => 'optional|url',
            'audience' => 'required|in:all,new_clients,inactive_clients,frequent_clients,birthday,custom',
            'inactive_days' => 'optional|int|between:1,3650',
            'scheduled_at' => 'optional|datetime',
        ], [
            'name' => 'nombre', 'channel' => 'canal', 'subject' => 'asunto',
            'body' => 'mensaje', 'audience' => 'publico',
        ]);

        $existing = $id > 0 ? QueryBuilder::table('campaigns')->where('id', $id)->first() : null;

        if ($existing !== null && in_array((string) $existing['status'], ['sending', 'sent'], true)) {
            Session::error('Una campana ya enviada no se puede modificar. Duplicala para reutilizarla.');

            return $this->redirect('/panel/campanas');
        }

        $payload = [
            'name' => $data['name'],
            'channel' => (string) $data['channel'],
            'subject' => (string) ($data['subject'] ?? ''),
            // Se admite formato basico; se eliminan scripts y atributos de evento.
            'body' => $this->sanitizeBody((string) $data['body']),
            'cta_label' => (string) ($data['cta_label'] ?? ''),
            'cta_url' => (string) ($data['cta_url'] ?? ''),
            'audience' => (string) $data['audience'],
            'inactive_days' => (int) ($data['inactive_days'] ?? 60),
            'scheduled_at' => !empty($data['scheduled_at'])
                ? Clock::localToUtc((string) $data['scheduled_at'] . (strlen((string) $data['scheduled_at']) === 16 ? ':00' : ''))
                : null,
            'status' => !empty($data['scheduled_at']) ? 'scheduled' : 'draft',
            'updated_at' => Clock::nowUtc(),
        ];

        if ($request->hasFile('image')) {
            $payload['image_path'] = MediaService::replace(
                (string) ($existing['image_path'] ?? ''),
                (array) $request->file('image'),
                'campanas',
                Auth::id(),
                1200
            );
        }

        if ($id > 0) {
            QueryBuilder::table('campaigns')->where('id', $id)->update($payload);
        } else {
            $payload['created_by'] = Auth::id();
            $payload['created_at'] = Clock::nowUtc();
            $id = QueryBuilder::table('campaigns')->insert($payload);
        }

        $count = CampaignService::buildAudience($id);

        Audit::record('campana.guardada', 'campaign', $id, $existing, $payload, $request);
        Session::success("Campana guardada. Publico calculado: {$count} destinatario(s) con consentimiento vigente.");

        return $this->redirect('/panel/campanas/' . $id . '/editar');
    }

    public function send(Request $request): Response
    {
        $this->authorize('campanas.enviar');

        $id = $request->paramInt('id');

        if ($request->string('confirm') !== 'ENVIAR') {
            Session::error('Escribe ENVIAR para confirmar el envio.');

            return $this->redirect('/panel/campanas/' . $id . '/editar');
        }

        $queued = CampaignService::dispatch($id, Auth::id());

        Session::success("Campana en cola: {$queued} mensaje(s). Se enviaran progresivamente.");

        return $this->redirect('/panel/campanas/' . $id . '/editar');
    }

    public function cancel(Request $request): Response
    {
        $this->authorize('campanas.enviar');

        $id = $request->paramInt('id');

        QueryBuilder::table('campaigns')->where('id', $id)->update([
            'status' => 'cancelled',
            'updated_at' => Clock::nowUtc(),
        ]);

        QueryBuilder::table('notification_queue')
            ->where('related_type', 'campaign')
            ->where('related_id', $id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        Audit::record('campana.cancelada', 'campaign', $id, null, null, $request);
        Session::success('Campana cancelada. Los mensajes pendientes no se enviaran.');

        return $this->redirect('/panel/campanas');
    }

    public function delete(Request $request): Response
    {
        $this->authorize('campanas.enviar');

        $id = $request->paramInt('id');

        QueryBuilder::table('campaigns')->where('id', $id)->update([
            'deleted_at' => Clock::nowUtc(),
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('campana.eliminada', 'campaign', $id, null, null, $request);
        Session::success('Campana eliminada.');

        return $this->redirect('/panel/campanas');
    }

    /**
     * Tamano estimado de cada publico, contando solo a quien dio permiso.
     *
     * @return array<string,int>
     */
    private function audienceSizes(): array
    {
        $base = static fn (): \App\Core\QueryBuilder => QueryBuilder::table('users')
            ->where('role', 'client')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where('accepts_marketing', 1);

        $inactiveCutoff = gmdate('Y-m-d H:i:s', time() - 60 * 86400);

        return [
            'all' => $base()->count(),
            'new_clients' => $base()->where('total_visits', '<=', 1)->count(),
            'inactive_clients' => $base()
                ->whereGroup(static function (\App\Core\QueryBuilder $q) use ($inactiveCutoff): void {
                    $q->whereNull('last_visit_at')->orWhere('last_visit_at', '<', $inactiveCutoff);
                })->count(),
            'frequent_clients' => $base()->where('total_visits', '>=', 5)->count(),
            'birthday' => $base()->whereNotNull('birth_date')->count(),
        ];
    }

    private function sanitizeBody(string $body): string
    {
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><a><blockquote><span><img>';
        $clean = strip_tags($body, $allowed);
        $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
        $clean = preg_replace('/(href|src)\s*=\s*("|\')\s*(javascript|data|vbscript):/i', '$1=$2#', $clean) ?? '';

        return mb_substr($clean, 0, 20000);
    }
}
