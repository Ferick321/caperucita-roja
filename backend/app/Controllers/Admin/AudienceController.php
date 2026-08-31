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

/**
 * Suscriptores del boletin y lista de espera.
 *
 * Dos listas que el negocio necesita mirar y limpiar a mano: quien acepto
 * recibir publicidad y quien esta esperando un hueco que se libere.
 */
final class AudienceController extends AdminController
{
    // ---- Suscriptores ----------------------------------------------------

    public function subscribers(Request $request): Response
    {
        $this->authorize('suscriptores.ver');

        $query = QueryBuilder::table('subscribers');

        $search = $request->string('q');
        if ($search !== '') {
            $query->search($search, ['email', 'name', 'phone']);
        }

        $estado = $request->string('estado');
        if ($estado === 'activos') {
            $query->whereNull('unsubscribed_at');
        } elseif ($estado === 'bajas') {
            $query->whereNull('unsubscribed_at', true);
        } elseif ($estado === 'sin_confirmar') {
            $query->where('is_confirmed', 0)->whereNull('unsubscribed_at');
        }

        $query->orderBy('created_at', 'DESC');

        return $this->view('admin.audience.subscribers', [
            'result' => Model::paginate($query, $this->page($request), 50),
            'filters' => ['q' => $search, 'estado' => $estado],
            'stats' => [
                'total' => QueryBuilder::table('subscribers')->count(),
                'activos' => QueryBuilder::table('subscribers')->whereNull('unsubscribed_at')->count(),
                'confirmados' => QueryBuilder::table('subscribers')
                    ->where('is_confirmed', 1)
                    ->whereNull('unsubscribed_at')
                    ->count(),
                'bajas' => QueryBuilder::table('subscribers')->whereNull('unsubscribed_at', true)->count(),
            ],
        ]);
    }

    /** Alta manual, para el cliente que deja su correo en el local. */
    public function storeSubscriber(Request $request): Response
    {
        $this->authorize('suscriptores.editar');

        $data = $this->validate($request, [
            'email' => 'required|email|max:190',
            'name' => 'optional|string|max:120|no_html',
            'phone' => 'optional|string|max:20|no_html',
        ], ['email' => 'correo', 'name' => 'nombre', 'phone' => 'telefono']);

        $email = (string) $data['email'];

        if (QueryBuilder::table('subscribers')->where('email', $email)->exists()) {
            Session::error("El correo {$email} ya esta en la lista.");

            return $this->redirect('/panel/suscriptores');
        }

        QueryBuilder::table('subscribers')->insert([
            'email' => $email,
            'name' => (string) ($data['name'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'source' => 'panel',
            'is_confirmed' => 1,
            'confirmed_at' => Clock::nowUtc(),
            'unsubscribe_token' => bin2hex(random_bytes(16)),
            'consent_ip' => '',
            'created_at' => Clock::nowUtc(),
        ]);

        Audit::record('suscriptor.creado', 'subscriber', null, null, ['email' => $email], $request);
        Session::success("{$email} anadido a la lista.");

        return $this->redirect('/panel/suscriptores');
    }

    /** Da de baja sin borrar: hay que poder demostrar que pidio la baja. */
    public function unsubscribe(Request $request): Response
    {
        $this->authorize('suscriptores.editar');

        $id = $request->paramInt('id');
        $subscriber = $this->findSubscriber($id);

        QueryBuilder::table('subscribers')->where('id', $id)->update([
            'unsubscribed_at' => Clock::nowUtc(),
        ]);

        Audit::record('suscriptor.baja', 'subscriber', $id, $subscriber, null, $request);
        Session::success('Ya no recibira publicidad.');

        return $this->redirect('/panel/suscriptores');
    }

    public function deleteSubscriber(Request $request): Response
    {
        $this->authorize('suscriptores.editar');

        $id = $request->paramInt('id');
        $subscriber = $this->findSubscriber($id);

        QueryBuilder::table('subscribers')->where('id', $id)->delete();

        Audit::record('suscriptor.eliminado', 'subscriber', $id, $subscriber, null, $request);
        Session::success('Suscriptor borrado de la base de datos.');

        return $this->redirect('/panel/suscriptores');
    }

    /** Descarga la lista en CSV, para usarla en otra herramienta. */
    public function exportSubscribers(Request $request): Response
    {
        $this->authorize('suscriptores.ver');

        $rows = QueryBuilder::table('subscribers')
            ->whereNull('unsubscribed_at')
            ->orderBy('created_at', 'DESC')
            ->get();

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new HttpException(500, 'No se pudo generar el archivo.');
        }

        // BOM para que Excel reconozca los acentos.
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['Correo', 'Nombre', 'Telefono', 'Origen', 'Confirmado', 'Alta'], ';');

        foreach ($rows as $row) {
            fputcsv($handle, [
                $this->csvCell((string) $row['email']),
                $this->csvCell((string) $row['name']),
                $this->csvCell((string) $row['phone']),
                $this->csvCell((string) $row['source']),
                (bool) $row['is_confirmed'] ? 'Si' : 'No',
                local_datetime((string) $row['created_at'], 'd/m/Y'),
            ], ';');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        Audit::record('suscriptores.exportados', 'subscriber', null, null, ['filas' => count($rows)], $request);

        return Response::make($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="suscriptores-' . date('Y-m-d') . '.csv"');
    }

    // ---- Lista de espera -------------------------------------------------

    public function waitlist(Request $request): Response
    {
        $this->authorize('espera.ver');

        $query = QueryBuilder::table('waitlist')
            ->select([
                'waitlist.*',
                'services.name AS service_name',
                'staff.display_name AS staff_name',
                'branches.name AS branch_name',
            ])
            ->leftJoin('services', 'services.id', '=', 'waitlist.service_id')
            ->leftJoin('staff', 'staff.id', '=', 'waitlist.staff_id')
            ->leftJoin('branches', 'branches.id', '=', 'waitlist.branch_id');

        $estado = $request->string('estado');
        if (in_array($estado, ['waiting', 'notified', 'converted', 'expired'], true)) {
            $query->where('waitlist.status', $estado);
        }

        $query->orderBy('waitlist.desired_date')->orderBy('waitlist.created_at');

        return $this->view('admin.audience.waitlist', [
            'result' => Model::paginate($query, $this->page($request), 50),
            'filters' => ['estado' => $estado],
            'stats' => [
                'esperando' => QueryBuilder::table('waitlist')->where('status', 'waiting')->count(),
                'avisados' => QueryBuilder::table('waitlist')->where('status', 'notified')->count(),
                'convertidos' => QueryBuilder::table('waitlist')->where('status', 'converted')->count(),
            ],
        ]);
    }

    public function updateWaitlist(Request $request): Response
    {
        $this->authorize('espera.editar');

        $id = $request->paramInt('id');
        $entry = QueryBuilder::table('waitlist')->where('id', $id)->first();

        if ($entry === null) {
            throw new HttpException(404, 'Esa solicitud no existe.');
        }

        $estado = $request->string('estado');

        if (!in_array($estado, ['waiting', 'notified', 'converted', 'expired'], true)) {
            throw new HttpException(422, 'Ese estado no existe.');
        }

        $payload = ['status' => $estado];

        if ($estado === 'notified') {
            $payload['notified_at'] = Clock::nowUtc();
        }

        QueryBuilder::table('waitlist')->where('id', $id)->update($payload);

        Audit::record('espera.actualizada', 'waitlist', $id, $entry, $payload, $request);
        Session::success('Solicitud actualizada.');

        return $this->redirect('/panel/espera');
    }

    public function deleteWaitlist(Request $request): Response
    {
        $this->authorize('espera.editar');

        $id = $request->paramInt('id');
        $entry = QueryBuilder::table('waitlist')->where('id', $id)->first();

        if ($entry === null) {
            throw new HttpException(404, 'Esa solicitud no existe.');
        }

        QueryBuilder::table('waitlist')->where('id', $id)->delete();

        Audit::record('espera.eliminada', 'waitlist', $id, $entry, null, $request);
        Session::success('Solicitud eliminada.');

        return $this->redirect('/panel/espera');
    }

    // ---- Apoyo -----------------------------------------------------------

    /** @return array<string,mixed> */
    private function findSubscriber(int $id): array
    {
        $subscriber = QueryBuilder::table('subscribers')->where('id', $id)->first();

        if ($subscriber === null) {
            throw new HttpException(404, 'Ese suscriptor no existe.');
        }

        return $subscriber;
    }

    /**
     * Prepara un valor para el CSV.
     *
     * Un texto que empieza por = + - o @ lo interpreta Excel como formula,
     * asi que se le antepone una comilla. Sin esto, un nombre malicioso
     * podria ejecutar algo al abrir el archivo descargado. Del escape de
     * comillas y separadores se encarga fputcsv.
     */
    private function csvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            $value = "'" . $value;
        }

        return $value;
    }
}
