<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Clock;
use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;
use App\Security\RateLimiter;
use App\Services\PaymentService;
use App\Services\SettingsService;

/**
 * Pagos desde la app.
 *
 * El cliente elige efectivo o transferencia; si elige transferencia recibe
 * los datos bancarios completos y sube el comprobante (archivo o foto de la
 * camara del telefono).
 */
final class PaymentController extends ApiController
{
    public function methods(Request $request): Response
    {
        $methods = PaymentService::availableMethods();

        return $this->ok(array_map(static fn (array $method): array => [
            'id' => (int) $method['id'],
            'code' => (string) $method['code'],
            'name' => (string) $method['name'],
            'description' => (string) $method['description'],
            'instructions' => (string) ($method['instructions'] ?? ''),
            'icon' => (string) $method['icon'],
            'requires_proof' => (bool) $method['requires_proof'],
            'shows_bank_accounts' => (bool) $method['shows_bank_accounts'],
            'requires_verification' => (bool) $method['requires_verification'],
        ], $methods));
    }

    /**
     * Datos bancarios completos.
     *
     * Solo se entregan a un cliente autenticado que va a pagar, nunca en un
     * endpoint publico.
     */
    public function bankAccounts(Request $request): Response
    {
        $accounts = PaymentService::bankAccounts(true);

        return $this->ok([
            'instructions' => SettingsService::string('payments.transfer_instructions'),
            'accounts' => array_map(static fn (array $account): array => [
                'id' => (int) $account['id'],
                'bank_name' => (string) $account['bank_name'],
                'account_type' => (string) $account['account_type'],
                'account_number' => (string) $account['account_number'],
                'holder_name' => (string) $account['holder_name'],
                'holder_document' => (string) $account['holder_document'],
                'holder_email' => (string) $account['holder_email'],
                'holder_phone' => (string) $account['holder_phone'],
                'instructions' => (string) ($account['instructions'] ?? ''),
                'currency' => (string) $account['currency'],
            ], $accounts),
        ]);
    }

    /** Registra la intencion de pago de una cita del propio cliente. */
    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'appointment_id' => 'required|int|min:1',
            'payment_method_id' => 'required|int|min:1',
            'amount' => 'optional|numeric|min:0',
            'reference' => 'optional|string|max:120|no_html',
            'transferred_at' => 'optional|date',
        ], [
            'appointment_id' => 'cita', 'payment_method_id' => 'metodo de pago',
            'amount' => 'importe', 'reference' => 'referencia',
        ]);

        $appointmentId = (int) $data['appointment_id'];
        $this->assertOwnsAppointment($appointmentId);

        $payment = PaymentService::registerForAppointment(
            $appointmentId,
            (int) $data['payment_method_id'],
            $request->int('bank_account_id') ?: null,
            (float) ($data['amount'] ?? 0),
            (string) ($data['reference'] ?? ''),
            !empty($data['transferred_at'])
                ? Clock::localToUtc((string) $data['transferred_at'] . ' 12:00:00')
                : null,
            (int) Auth::id()
        );

        return Response::json([
            'ok' => true,
            'data' => [
                'id' => (int) $payment['id'],
                'status' => (string) $payment['status'],
                'amount' => round((float) $payment['amount'], 2),
                'requires_proof' => (bool) QueryBuilder::table('payment_methods')
                    ->where('id', (int) $data['payment_method_id'])
                    ->value('requires_proof'),
            ],
        ], 201);
    }

    /**
     * Sube el comprobante.
     *
     * Acepta multipart/form-data (campo "proof") o una imagen en base64
     * (campo "proof_base64"), que es lo que envia la camara del telefono.
     */
    public function uploadProof(Request $request): Response
    {
        $limit = RateLimiter::hit('api:comprobante:' . Auth::id(), 20, 3600);

        if (!$limit['allowed']) {
            throw new HttpException(429, 'Demasiadas subidas seguidas. Intentalo mas tarde.');
        }

        $paymentId = $request->paramInt('id');

        $payment = QueryBuilder::table('payments')
            ->where('id', $paymentId)
            ->where('client_id', (int) Auth::id())
            ->whereNull('deleted_at')
            ->first();

        if ($payment === null) {
            throw new HttpException(404, 'No encontramos ese pago.');
        }

        $isHttpUpload = $request->hasFile('proof');

        $file = $isHttpUpload
            ? (array) $request->file('proof')
            : $this->fileFromBase64($request->string('proof_base64'), $request->string('proof_name', 'comprobante.jpg'));

        try {
            $proof = PaymentService::attachProof($paymentId, $file, 'app', (int) Auth::id(), $isHttpUpload);
        } finally {
            // El temporal de la imagen en base64 no debe quedarse en disco.
            if (!$isHttpUpload && is_file((string) $file['tmp_name'])) {
                @unlink((string) $file['tmp_name']);
            }
        }

        return $this->ok([
            'message' => 'Comprobante recibido. Lo verificaremos en breve.',
            'proof' => [
                'id' => (int) $proof['id'],
                'url' => media_url((string) $proof['file_path']),
                'mime' => (string) $proof['file_mime'],
                'created_at' => local_datetime((string) $proof['created_at'], 'Y-m-d H:i'),
            ],
        ]);
    }

    /**
     * Convierte una imagen en base64 en una entrada de subida temporal.
     *
     * Se limita el tamano ANTES de escribir en disco y el archivo temporal se
     * valida despues con las mismas reglas que cualquier otra subida.
     *
     * @return array<string,mixed>
     */
    private function fileFromBase64(string $payload, string $name): array
    {
        if ($payload === '') {
            throw new HttpException(422, 'No se recibio ningun comprobante.');
        }

        // Admite el prefijo "data:image/jpeg;base64,".
        if (preg_match('#^data:([a-z]+/[a-z0-9.+-]+);base64,#i', $payload, $matches) === 1) {
            $payload = substr($payload, strlen($matches[0]));
        }

        $maxBytes = (int) config('uploads.max_bytes', 5 * 1024 * 1024);

        // Cada 4 caracteres de base64 son 3 bytes: se comprueba sin decodificar.
        if ((strlen($payload) * 3 / 4) > $maxBytes) {
            throw new HttpException(422, 'El comprobante es demasiado grande.');
        }

        $binary = base64_decode($payload, true);

        if ($binary === false || $binary === '') {
            throw new HttpException(422, 'El comprobante no se pudo leer.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'proof_');

        if ($temporary === false || file_put_contents($temporary, $binary) === false) {
            throw new HttpException(500, 'No se pudo procesar el comprobante.');
        }

        return [
            'name' => $name,
            'tmp_name' => $temporary,
            'size' => strlen($binary),
            'error' => UPLOAD_ERR_OK,
            'type' => 'application/octet-stream',
        ];
    }

    private function assertOwnsAppointment(int $appointmentId): void
    {
        $owns = QueryBuilder::table('appointments')
            ->where('id', $appointmentId)
            ->where('client_id', (int) Auth::id())
            ->whereNull('deleted_at')
            ->exists();

        if (!$owns) {
            throw new HttpException(403, 'Esa cita no es tuya.');
        }
    }
}
