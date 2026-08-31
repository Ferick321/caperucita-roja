<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Security\Audit;
use App\Security\Crypto;
use App\Security\FileUploader;

/**
 * Pagos de las citas.
 *
 * Flujo de transferencia:
 *   1. el cliente elige "Transferencia" y ve los datos bancarios editables;
 *   2. sube o fotografia el comprobante;
 *   3. la cita queda "en verificacion";
 *   4. el personal aprueba o rechaza desde el panel.
 */
final class PaymentService
{
    /**
     * Metodos de pago disponibles para el cliente.
     *
     * @return list<array<string,mixed>>
     */
    public static function availableMethods(bool $onlineOnly = true): array
    {
        $query = QueryBuilder::table('payment_methods')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($onlineOnly) {
            $query->where('is_online', 1);
        }

        return $query->get();
    }

    /**
     * Cuentas bancarias que se muestran al elegir transferencia.
     *
     * @param bool $reveal true solo cuando el cliente ya eligio transferir;
     *                     en los listados se devuelve enmascarado.
     * @return list<array<string,mixed>>
     */
    public static function bankAccounts(bool $reveal = false): array
    {
        $accounts = QueryBuilder::table('bank_accounts')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();

        foreach ($accounts as $index => $account) {
            $number = Crypto::decrypt((string) $account['account_number_enc']);

            $accounts[$index]['account_number'] = $reveal ? $number : Crypto::mask($number);
            unset($accounts[$index]['account_number_enc']);
        }

        return $accounts;
    }

    /** @return array<string,mixed>|null */
    public static function bankAccount(int $id, bool $reveal = false): ?array
    {
        $account = QueryBuilder::table('bank_accounts')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if ($account === null) {
            return null;
        }

        $number = Crypto::decrypt((string) $account['account_number_enc']);
        $account['account_number'] = $reveal ? $number : Crypto::mask($number);
        unset($account['account_number_enc']);

        return $account;
    }

    /** Guarda o actualiza una cuenta bancaria cifrando el numero. */
    public static function saveBankAccount(array $data, ?int $id = null): int
    {
        $number = trim((string) ($data['account_number'] ?? ''));

        if ($number === '' && $id === null) {
            throw new HttpException(422, 'El numero de cuenta es obligatorio.');
        }

        $now = Clock::nowUtc();

        $payload = [
            'bank_name' => mb_substr(trim((string) $data['bank_name']), 0, 120),
            'account_type' => mb_substr(trim((string) ($data['account_type'] ?? 'Ahorros')), 0, 60),
            'holder_name' => mb_substr(trim((string) $data['holder_name']), 0, 160),
            'holder_document' => mb_substr(trim((string) ($data['holder_document'] ?? '')), 0, 60),
            'holder_email' => mb_substr(trim((string) ($data['holder_email'] ?? '')), 0, 190),
            'holder_phone' => mb_substr(trim((string) ($data['holder_phone'] ?? '')), 0, 30),
            'instructions' => mb_substr(trim((string) ($data['instructions'] ?? '')), 0, 1000),
            'currency' => mb_substr((string) ($data['currency'] ?? 'USD'), 0, 3),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => $now,
        ];

        if ($number !== '') {
            $payload['account_number_enc'] = Crypto::encrypt($number);
            $payload['account_last4'] = mb_substr($number, -4);
        }

        if ($id === null) {
            $payload['created_at'] = $now;
            $newId = QueryBuilder::table('bank_accounts')->insert($payload);
            Audit::record('cuenta_bancaria.creada', 'bank_account', $newId);

            return $newId;
        }

        QueryBuilder::table('bank_accounts')->where('id', $id)->update($payload);
        Audit::record('cuenta_bancaria.actualizada', 'bank_account', $id);

        return $id;
    }

    /**
     * Registra la intencion de pago de una cita.
     *
     * @return array<string,mixed> el pago creado
     */
    public static function registerForAppointment(
        int $appointmentId,
        int $paymentMethodId,
        ?int $bankAccountId,
        float $amount,
        string $reference = '',
        ?string $transferredAt = null,
        ?int $userId = null
    ): array {
        $appointment = QueryBuilder::table('appointments')->where('id', $appointmentId)->first();

        if ($appointment === null) {
            throw new HttpException(404, 'La cita no existe.');
        }

        $method = QueryBuilder::table('payment_methods')
            ->where('id', $paymentMethodId)
            ->where('is_active', 1)
            ->first();

        if ($method === null) {
            throw new HttpException(422, 'El metodo de pago seleccionado no esta disponible.');
        }

        if ($amount <= 0) {
            $amount = (float) $appointment['total'];
        }

        if ($amount > (float) $appointment['total'] + 0.01) {
            throw new HttpException(422, 'El importe no puede superar el total de la cita.');
        }

        $requiresVerification = (bool) $method['requires_verification'];
        $now = Clock::nowUtc();

        $paymentId = QueryBuilder::table('payments')->insert([
            'appointment_id' => $appointmentId,
            'client_id' => $appointment['client_id'] ?? null,
            'payment_method_id' => $paymentMethodId,
            'bank_account_id' => (bool) $method['shows_bank_accounts'] ? $bankAccountId : null,
            'method_code' => (string) $method['code'],
            'amount' => round($amount, 2),
            'currency' => (string) $appointment['currency'],
            'kind' => $amount < (float) $appointment['total'] ? 'deposit' : 'full',
            'status' => $requiresVerification ? 'awaiting_verification' : 'pending',
            'reference' => mb_substr(trim($reference), 0, 120),
            'transferred_at' => $transferredAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($requiresVerification) {
            QueryBuilder::table('appointments')->where('id', $appointmentId)->update([
                'payment_status' => 'awaiting_verification',
                'updated_at' => $now,
            ]);
        }

        Audit::record('pago.registrado', 'payment', $paymentId, null, [
            'appointment_id' => $appointmentId,
            'method' => (string) $method['code'],
            'amount' => $amount,
        ], null, $userId);

        return QueryBuilder::table('payments')->where('id', $paymentId)->first() ?? [];
    }

    /**
     * Adjunta el comprobante subido por el cliente (archivo o foto de camara).
     *
     * @param array<string,mixed> $file entrada de $_FILES
     */
    public static function attachProof(
        int $paymentId,
        array $file,
        string $from = 'web',
        ?int $userId = null,
        bool $fromHttpUpload = true
    ): array {
        $payment = QueryBuilder::table('payments')->where('id', $paymentId)->first();

        if ($payment === null) {
            throw new HttpException(404, 'El pago no existe.');
        }

        if (in_array((string) $payment['status'], ['approved', 'refunded'], true)) {
            throw new HttpException(422, 'Este pago ya fue procesado.');
        }

        $stored = FileUploader::store($file, 'comprobantes', true, 1600, $fromHttpUpload);

        // Un mismo comprobante reutilizado en otra cita es una senal de alerta.
        $duplicate = QueryBuilder::table('payment_proofs')
            ->where('file_hash', $stored['hash'])
            ->where('payment_id', '!=', $paymentId)
            ->first();

        $proofId = QueryBuilder::table('payment_proofs')->insert([
            'payment_id' => $paymentId,
            'file_path' => $stored['path'],
            'file_mime' => $stored['mime'],
            'file_size' => $stored['size'],
            'file_hash' => $stored['hash'],
            'original_name' => $stored['original_name'],
            'uploaded_by' => $userId,
            'uploaded_from' => in_array($from, ['web', 'app', 'panel'], true) ? $from : 'web',
            'created_at' => Clock::nowUtc(),
        ]);

        $notes = $duplicate !== null
            ? 'AVISO: este comprobante ya fue usado en el pago #' . (int) $duplicate['payment_id']
            : (string) $payment['notes'];

        QueryBuilder::table('payments')->where('id', $paymentId)->update([
            'status' => 'awaiting_verification',
            'notes' => mb_substr($notes, 0, 500),
            'updated_at' => Clock::nowUtc(),
        ]);

        if ($payment['appointment_id'] !== null) {
            QueryBuilder::table('appointments')->where('id', (int) $payment['appointment_id'])->update([
                'payment_status' => 'awaiting_verification',
                'updated_at' => Clock::nowUtc(),
            ]);
        }

        NotificationService::onProofUploaded((int) $paymentId);
        Audit::record('pago.comprobante', 'payment', $paymentId, null, ['proof_id' => $proofId], null, $userId);

        return QueryBuilder::table('payment_proofs')->where('id', $proofId)->first() ?? [];
    }

    /** Aprueba un pago y actualiza el estado economico de la cita. */
    public static function approve(int $paymentId, int $verifierId, string $note = ''): void
    {
        Database::instance()->transaction(static function () use ($paymentId, $verifierId, $note): void {
            $payment = QueryBuilder::table('payments')->where('id', $paymentId)->forUpdate()->first();

            if ($payment === null) {
                throw new HttpException(404, 'El pago no existe.');
            }

            if ((string) $payment['status'] === 'approved') {
                return;
            }

            $now = Clock::nowUtc();

            QueryBuilder::table('payments')->where('id', $paymentId)->update([
                'status' => 'approved',
                'verified_by' => $verifierId,
                'verified_at' => $now,
                'notes' => mb_substr($note, 0, 500),
                'updated_at' => $now,
            ]);

            $appointmentId = $payment['appointment_id'] ?? null;

            if ($appointmentId === null) {
                return;
            }

            $appointmentId = (int) $appointmentId;
            $appointment = QueryBuilder::table('appointments')->where('id', $appointmentId)->first();

            if ($appointment === null) {
                return;
            }

            $paid = (float) Database::instance()->scalar(
                "SELECT COALESCE(SUM(amount), 0) FROM payments
                  WHERE appointment_id = :id AND status = 'approved' AND kind != 'refund'",
                ['id' => $appointmentId]
            );

            $total = (float) $appointment['total'];
            $status = $paid >= $total - 0.01 ? 'paid' : ($paid > 0 ? 'deposit_paid' : 'unpaid');

            $updates = [
                'paid_amount' => round($paid, 2),
                'payment_status' => $status,
                'updated_at' => $now,
            ];

            // Un pago aprobado confirma automaticamente la cita pendiente.
            if ((string) $appointment['status'] === 'pending') {
                $updates['status'] = 'confirmed';
                $updates['confirmed_at'] = $now;

                QueryBuilder::table('appointment_status_history')->insert([
                    'appointment_id' => $appointmentId,
                    'from_status' => 'pending',
                    'to_status' => 'confirmed',
                    'changed_by' => $verifierId,
                    'note' => 'Confirmada al validar el pago',
                    'created_at' => $now,
                ]);
            }

            QueryBuilder::table('appointments')->where('id', $appointmentId)->update($updates);

            NotificationService::onPaymentApproved(array_merge($appointment, $updates), $payment);
            Audit::record('pago.aprobado', 'payment', $paymentId, ['status' => $payment['status']], ['status' => 'approved']);
        });
    }

    public static function reject(int $paymentId, int $verifierId, string $reason): void
    {
        $payment = QueryBuilder::table('payments')->where('id', $paymentId)->first();

        if ($payment === null) {
            throw new HttpException(404, 'El pago no existe.');
        }

        $now = Clock::nowUtc();

        QueryBuilder::table('payments')->where('id', $paymentId)->update([
            'status' => 'rejected',
            'verified_by' => $verifierId,
            'verified_at' => $now,
            'rejection_reason' => mb_substr($reason, 0, 255),
            'updated_at' => $now,
        ]);

        if ($payment['appointment_id'] !== null) {
            QueryBuilder::table('appointments')->where('id', (int) $payment['appointment_id'])->update([
                'payment_status' => 'unpaid',
                'updated_at' => $now,
            ]);
        }

        NotificationService::onPaymentRejected((int) $paymentId, $reason);
        Audit::record('pago.rechazado', 'payment', $paymentId, null, ['reason' => $reason]);
    }

    /** Abono exigido para reservar, segun la configuracion y el servicio. */
    public static function requiredDeposit(float $total, array $services = []): float
    {
        foreach ($services as $service) {
            if (!empty($service['deposit_required'])) {
                return (bool) $service['deposit_is_percentage']
                    ? round($total * (float) $service['deposit_amount'] / 100, 2)
                    : round((float) $service['deposit_amount'], 2);
            }
        }

        if (!SettingsService::bool('payments.require_deposit', false)) {
            return 0.0;
        }

        return round($total * SettingsService::float('payments.deposit_percent', 30) / 100, 2);
    }
}
