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
 * Cupones de descuento.
 *
 * El motor que los aplica (CouponService) ya existia; esto es el panel para
 * crearlos y controlarlos sin tocar la base de datos.
 */
final class CouponController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->authorize('cupones.ver');

        $query = QueryBuilder::table('coupons')
            ->select(['coupons.*', 'services.name AS service_name'])
            ->leftJoin('services', 'services.id', '=', 'coupons.service_id')
            ->whereNull('coupons.deleted_at');

        $search = $request->string('q');
        if ($search !== '') {
            $query->search($search, ['coupons.code', 'coupons.description']);
        }

        $estado = $request->string('estado');
        if ($estado === 'activos') {
            $query->where('coupons.is_active', 1);
        } elseif ($estado === 'apagados') {
            $query->where('coupons.is_active', 0);
        }

        $query->orderBy('coupons.created_at', 'DESC');

        return $this->view('admin.coupons.index', [
            'result' => Model::paginate($query, $this->page($request), 30),
            'filters' => ['q' => $search, 'estado' => $estado],
        ]);
    }

    public function form(Request $request): Response
    {
        $this->authorize('cupones.editar');

        $id = $request->paramInt('id');
        $coupon = null;

        if ($id > 0) {
            $coupon = QueryBuilder::table('coupons')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->first();

            if ($coupon === null) {
                throw new HttpException(404, 'Ese cupon no existe.');
            }
        }

        return $this->view('admin.coupons.form', [
            'coupon' => $coupon,
            'services' => QueryBuilder::table('services')
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(),
            'redemptions' => $id > 0
                ? QueryBuilder::table('coupon_redemptions')
                    ->select(['coupon_redemptions.*', 'users.first_name', 'users.last_name', 'users.email'])
                    ->leftJoin('users', 'users.id', '=', 'coupon_redemptions.user_id')
                    ->where('coupon_redemptions.coupon_id', $id)
                    ->orderBy('coupon_redemptions.created_at', 'DESC')
                    ->limit(30)
                    ->get()
                : [],
        ]);
    }

    public function save(Request $request): Response
    {
        $this->authorize('cupones.editar');

        $id = $request->paramInt('id');

        $data = $this->validate($request, [
            'code' => 'required|string|min:3|max:40|no_html',
            'description' => 'optional|string|max:255|no_html',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0|max:999999',
            'min_amount' => 'optional|numeric|min:0|max:999999',
            'max_discount' => 'optional|numeric|min:0|max:999999',
            'service_id' => 'optional|int|min:0',
            'usage_limit' => 'optional|int|between:0,1000000',
            'usage_limit_per_user' => 'optional|int|between:0,1000',
            'starts_at' => 'optional|date',
            'ends_at' => 'optional|date',
        ], [
            'code' => 'codigo', 'discount_type' => 'tipo de descuento',
            'discount_value' => 'valor del descuento', 'min_amount' => 'monto minimo',
            'max_discount' => 'descuento maximo', 'service_id' => 'servicio',
            'usage_limit' => 'limite de usos', 'starts_at' => 'fecha de inicio',
            'ends_at' => 'fecha de fin',
        ]);

        $codigo = mb_strtoupper(str_replace(' ', '', (string) $data['code']));

        // Un porcentaje mayor que 100 regalaria dinero al cliente.
        if ($data['discount_type'] === 'percent' && (float) $data['discount_value'] > 100) {
            throw new HttpException(422, 'Un descuento en porcentaje no puede superar el 100%.');
        }

        $duplicado = QueryBuilder::table('coupons')->where('code', $codigo);

        if ($id > 0) {
            $duplicado->where('id', '!=', $id);
        }

        if ($duplicado->exists()) {
            Session::error("Ya existe un cupon con el codigo {$codigo}.");

            return $this->redirect($id > 0 ? '/panel/cupones/' . $id . '/editar' : '/panel/cupones/nuevo');
        }

        $inicio = (string) ($data['starts_at'] ?? '');
        $fin = (string) ($data['ends_at'] ?? '');

        if ($inicio !== '' && $fin !== '' && $fin < $inicio) {
            Session::error('La fecha de fin no puede ser anterior a la de inicio.');

            return $this->redirect($id > 0 ? '/panel/cupones/' . $id . '/editar' : '/panel/cupones/nuevo');
        }

        $servicioId = (int) ($data['service_id'] ?? 0);

        if ($servicioId > 0
            && !QueryBuilder::table('services')->where('id', $servicioId)->whereNull('deleted_at')->exists()) {
            throw new HttpException(422, 'Ese servicio no existe.');
        }

        $payload = [
            'code' => $codigo,
            'description' => (string) ($data['description'] ?? ''),
            'discount_type' => (string) $data['discount_type'],
            'discount_value' => (float) $data['discount_value'],
            'min_amount' => (float) ($data['min_amount'] ?? 0),
            'max_discount' => ($data['max_discount'] ?? '') !== '' ? (float) $data['max_discount'] : null,
            'service_id' => $servicioId > 0 ? $servicioId : null,
            'first_visit_only' => $request->bool('first_visit_only') ? 1 : 0,
            'usage_limit' => (int) ($data['usage_limit'] ?? 0),
            'usage_limit_per_user' => (int) ($data['usage_limit_per_user'] ?? 0),
            'starts_at' => $inicio !== '' ? Clock::localToUtc($inicio . ' 00:00:00') : null,
            'ends_at' => $fin !== '' ? Clock::localToUtc($fin . ' 23:59:59') : null,
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'updated_at' => Clock::nowUtc(),
        ];

        if ($id > 0) {
            $anterior = QueryBuilder::table('coupons')->where('id', $id)->first();
            QueryBuilder::table('coupons')->where('id', $id)->update($payload);
            Audit::record('cupon.actualizado', 'coupon', $id, $anterior, $payload, $request);
        } else {
            $payload['times_used'] = 0;
            $payload['created_at'] = Clock::nowUtc();
            $id = QueryBuilder::table('coupons')->insert($payload);
            Audit::record('cupon.creado', 'coupon', $id, null, $payload, $request);
        }

        Session::success("Cupon {$codigo} guardado.");

        return $this->redirect('/panel/cupones');
    }

    public function toggle(Request $request): Response
    {
        $this->authorize('cupones.editar');

        $id = $request->paramInt('id');
        $coupon = QueryBuilder::table('coupons')->where('id', $id)->whereNull('deleted_at')->first();

        if ($coupon === null) {
            throw new HttpException(404, 'Ese cupon no existe.');
        }

        $activo = (bool) $coupon['is_active'] ? 0 : 1;

        QueryBuilder::table('coupons')->where('id', $id)->update([
            'is_active' => $activo,
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('cupon.estado', 'coupon', $id, null, ['is_active' => $activo], $request);
        Session::success($activo === 1 ? 'Cupon activado.' : 'Cupon apagado.');

        return $this->redirect('/panel/cupones');
    }

    public function delete(Request $request): Response
    {
        $this->authorize('cupones.editar');

        $id = $request->paramInt('id');
        $coupon = QueryBuilder::table('coupons')->where('id', $id)->whereNull('deleted_at')->first();

        if ($coupon === null) {
            throw new HttpException(404, 'Ese cupon no existe.');
        }

        // Se marca como eliminado en vez de borrarlo: las citas que ya lo
        // usaron apuntan a el y el informe de ventas debe seguir cuadrando.
        QueryBuilder::table('coupons')->where('id', $id)->update([
            'deleted_at' => Clock::nowUtc(),
            'is_active' => 0,
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('cupon.eliminado', 'coupon', $id, $coupon, null, $request);
        Session::success('Cupon eliminado. Ya no se puede canjear.');

        return $this->redirect('/panel/cupones');
    }
}
