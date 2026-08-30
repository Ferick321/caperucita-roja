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
use App\Services\PaymentService;

/** Pagos: verificacion de comprobantes, metodos y cuentas bancarias. */
final class PaymentController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->authorize('pagos.ver');

        $query = QueryBuilder::table('payments')
            ->select([
                'payments.*',
                'appointments.code AS appointment_code',
                'appointments.client_name',
                'appointments.starts_at AS appointment_at',
                'payment_methods.name AS method_name',
                'bank_accounts.bank_name',
            ])
            ->leftJoin('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->leftJoin('bank_accounts', 'bank_accounts.id', '=', 'payments.bank_account_id')
            ->whereNull('payments.deleted_at');

        $status = $request->string('estado', 'awaiting_verification');

        if ($status !== 'todos' && in_array($status, ['pending', 'awaiting_verification', 'approved', 'rejected', 'refunded'], true)) {
            $query->where('payments.status', $status);
        }

        $search = $request->string('q');
        if ($search !== '') {
            $query->search($search, ['appointments.code', 'appointments.client_name', 'payments.reference']);
        }

        $query->orderBy('payments.created_at', 'DESC');

        $result = Model::paginate($query, $this->page($request), 25);

        foreach ($result['data'] as $index => $payment) {
            $result['data'][$index]['proofs'] = QueryBuilder::table('payment_proofs')
                ->where('payment_id', (int) $payment['id'])
                ->orderBy('created_at', 'DESC')
                ->get();
        }

        return $this->view('admin.payments.index', [
            'result' => $result,
            'status' => $status,
            'search' => $search,
            'counts' => [
                'awaiting_verification' => QueryBuilder::table('payments')
                    ->where('status', 'awaiting_verification')->whereNull('deleted_at')->count(),
                'approved' => QueryBuilder::table('payments')
                    ->where('status', 'approved')->whereNull('deleted_at')->count(),
                'rejected' => QueryBuilder::table('payments')
                    ->where('status', 'rejected')->whereNull('deleted_at')->count(),
            ],
        ]);
    }

    public function approve(Request $request): Response
    {
        $this->authorize('pagos.verificar');

        PaymentService::approve($request->paramInt('id'), (int) Auth::id(), $request->string('note'));
        Session::success('Pago aprobado y cita confirmada.');

        return $this->back($request, '/panel/pagos');
    }

    public function reject(Request $request): Response
    {
        $this->authorize('pagos.verificar');

        $reason = $request->string('reason');

        if (mb_strlen($reason) < 5) {
            Session::error('Indica el motivo del rechazo para que el cliente sepa que corregir.');

            return $this->back($request, '/panel/pagos');
        }

        PaymentService::reject($request->paramInt('id'), (int) Auth::id(), $reason);
        Session::success('Pago rechazado. Se aviso al cliente.');

        return $this->back($request, '/panel/pagos');
    }

    /** Registro manual de un cobro hecho en el local. */
    public function registerManual(Request $request): Response
    {
        $this->authorize('pagos.verificar');

        $data = $this->validate($request, [
            'appointment_id' => 'required|int|min:1',
            'payment_method_id' => 'required|int|min:1',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'optional|string|max:120|no_html',
        ], ['appointment_id' => 'cita', 'payment_method_id' => 'metodo', 'amount' => 'importe']);

        $payment = PaymentService::registerForAppointment(
            (int) $data['appointment_id'],
            (int) $data['payment_method_id'],
            null,
            (float) $data['amount'],
            (string) ($data['reference'] ?? ''),
            null,
            Auth::id()
        );

        PaymentService::approve((int) $payment['id'], (int) Auth::id(), 'Cobro registrado en el local');
        Session::success('Cobro registrado.');

        return $this->back($request, '/panel/citas/' . (int) $data['appointment_id']);
    }

    // ---- Cuentas bancarias -----------------------------------------------

    public function bankAccounts(Request $request): Response
    {
        $this->authorize('pagos.cuentas');

        return $this->view('admin.payments.accounts', [
            // Se muestran completas porque quien las edita es el duenio del negocio.
            'accounts' => PaymentService::bankAccounts(true),
            'methods' => QueryBuilder::table('payment_methods')->orderBy('sort_order')->get(),
        ]);
    }

    public function saveBankAccount(Request $request): Response
    {
        $this->authorize('pagos.cuentas');

        $id = $request->paramInt('id');

        $data = $this->validate($request, [
            'bank_name' => 'required|string|min:2|max:120|no_html',
            'account_type' => 'optional|string|max:60|no_html',
            'account_number' => ($id > 0 ? 'optional' : 'required') . '|string|max:60|regex:/^[0-9A-Za-z\-\s]+$/',
            'holder_name' => 'required|string|min:2|max:160|no_html',
            'holder_document' => 'optional|string|max:60|no_html',
            'holder_email' => 'optional|email',
            'holder_phone' => 'optional|phone',
            'instructions' => 'optional|string|max:1000|no_html',
            'currency' => 'optional|string|max:3',
            'sort_order' => 'optional|int|between:0,999',
        ], [
            'bank_name' => 'banco',
            'account_number' => 'numero de cuenta',
            'holder_name' => 'titular',
            'holder_document' => 'identificacion del titular',
        ]);

        $data['is_active'] = $request->bool('is_active');

        $savedId = PaymentService::saveBankAccount($data, $id > 0 ? $id : null);

        Session::success('Cuenta bancaria guardada. Los clientes ya la veran al elegir transferencia.');

        return $this->redirect('/panel/pagos/cuentas#cuenta-' . $savedId);
    }

    public function deleteBankAccount(Request $request): Response
    {
        $this->authorize('pagos.cuentas');

        $id = $request->paramInt('id');

        QueryBuilder::table('bank_accounts')->where('id', $id)->update([
            'deleted_at' => Clock::nowUtc(),
            'is_active' => 0,
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('cuenta_bancaria.eliminada', 'bank_account', $id, null, null, $request);
        Session::success('Cuenta eliminada.');

        return $this->redirect('/panel/pagos/cuentas');
    }

    // ---- Metodos de pago -------------------------------------------------

    public function saveMethod(Request $request): Response
    {
        $this->authorize('pagos.cuentas');

        $id = $request->paramInt('id');
        $method = QueryBuilder::table('payment_methods')->where('id', $id)->first();

        if ($method === null) {
            throw new HttpException(404, 'El metodo de pago no existe.');
        }

        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:100|no_html',
            'description' => 'optional|string|max:500|no_html',
            'instructions' => 'optional|string|max:2000|no_html',
            'sort_order' => 'optional|int|between:0,999',
        ], ['name' => 'nombre']);

        QueryBuilder::table('payment_methods')->where('id', $id)->update([
            'name' => $data['name'],
            'description' => (string) ($data['description'] ?? ''),
            'instructions' => (string) ($data['instructions'] ?? ''),
            'requires_proof' => $request->bool('requires_proof') ? 1 : 0,
            'shows_bank_accounts' => $request->bool('shows_bank_accounts') ? 1 : 0,
            'requires_verification' => $request->bool('requires_verification') ? 1 : 0,
            'is_online' => $request->bool('is_online') ? 1 : 0,
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('metodo_pago.actualizado', 'payment_method', $id, $method, $data, $request);
        Session::success('Metodo de pago actualizado.');

        return $this->redirect('/panel/pagos/cuentas');
    }
}
