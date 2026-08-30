<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validador de entrada.
 *
 * Reglas soportadas:
 *   required, optional, string, int, numeric, bool, email, phone, url, date,
 *   time, datetime, in:a,b,c, min:n, max:n, between:a,b, length:n,
 *   regex:/.../, confirmed, slug, hex_color, json, array, image, no_html,
 *   password (politica de robustez).
 */
final class Validator
{
    /** @var array<string,mixed> */
    private array $data;

    /** @var array<string,list<string>> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $validated = [];

    /** @var array<string,string> */
    private array $labels;

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $labels
     */
    public function __construct(array $data, array $labels = [])
    {
        $this->data = $data;
        $this->labels = $labels;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data, $labels);

        foreach ($rules as $field => $ruleString) {
            $validator->check($field, $ruleString);
        }

        return $validator;
    }

    public function check(string $field, string $ruleString): void
    {
        $rules = array_filter(array_map('trim', explode('|', $ruleString)));
        $value = $this->data[$field] ?? null;

        $isRequired = in_array('required', $rules, true);
        $isPresent = $value !== null && $value !== '' && $value !== [];

        if (!$isPresent) {
            if ($isRequired) {
                $this->addError($field, 'es obligatorio.');

                return;
            }

            // Campo opcional ausente: se conserva null para poder limpiar valores.
            if (array_key_exists($field, $this->data)) {
                $this->validated[$field] = $value === '' ? null : $value;
            }

            return;
        }

        foreach ($rules as $rule) {
            if ($rule === 'required' || $rule === 'optional') {
                continue;
            }

            [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

            $result = $this->apply($field, (string) $name, $parameter, $value);

            if ($result === false) {
                return;
            }

            if (!is_bool($result)) {
                $value = $result;
            }
        }

        $this->validated[$field] = $value;
    }

    private function apply(string $field, string $rule, ?string $parameter, mixed $value): mixed
    {
        switch ($rule) {
            case 'string':
                if (!is_scalar($value)) {
                    return $this->fail($field, 'debe ser texto.');
                }

                return trim((string) $value);

            case 'no_html':
                $clean = strip_tags((string) $value);
                if ($clean !== (string) $value) {
                    return $this->fail($field, 'no puede contener etiquetas HTML.');
                }

                return $clean;

            case 'int':
                if (!is_numeric($value) || (string) (int) $value !== trim((string) $value)) {
                    return $this->fail($field, 'debe ser un numero entero.');
                }

                return (int) $value;

            case 'numeric':
                if (!is_numeric($value)) {
                    return $this->fail($field, 'debe ser un numero.');
                }

                return (float) $value;

            case 'bool':
                return in_array(
                    is_string($value) ? strtolower($value) : $value,
                    [true, 1, '1', 'true', 'on', 'yes', 'si'],
                    true
                );

            case 'array':
                if (!is_array($value)) {
                    return $this->fail($field, 'debe ser una lista.');
                }

                return $value;

            case 'email':
                $email = trim((string) $value);
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
                    return $this->fail($field, 'no es un correo valido.');
                }

                return mb_strtolower($email);

            case 'phone':
                $phone = preg_replace('/[^0-9+]/', '', (string) $value) ?? '';
                if (preg_match('/^\+?[0-9]{7,15}$/', $phone) !== 1) {
                    return $this->fail($field, 'no es un telefono valido (7 a 15 digitos).');
                }

                return $phone;

            case 'url':
                $url = trim((string) $value);
                if (filter_var($url, FILTER_VALIDATE_URL) === false
                    || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
                ) {
                    return $this->fail($field, 'no es una direccion web valida.');
                }

                return $url;

            case 'slug':
                if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value) !== 1) {
                    return $this->fail($field, 'solo admite minusculas, numeros y guiones.');
                }

                return (string) $value;

            case 'hex_color':
                if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $value) !== 1) {
                    return $this->fail($field, 'debe ser un color hexadecimal (#RRGGBB).');
                }

                return strtolower((string) $value);

            case 'date':
                if (!self::isValidFormat((string) $value, 'Y-m-d')) {
                    return $this->fail($field, 'debe tener el formato AAAA-MM-DD.');
                }

                return (string) $value;

            case 'time':
                if (preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', (string) $value) !== 1) {
                    return $this->fail($field, 'debe tener el formato HH:MM.');
                }

                return mb_strlen((string) $value) === 5 ? $value . ':00' : (string) $value;

            case 'datetime':
                if (!self::isValidFormat((string) $value, 'Y-m-d H:i:s')
                    && !self::isValidFormat((string) $value, 'Y-m-d H:i')
                ) {
                    return $this->fail($field, 'debe tener el formato AAAA-MM-DD HH:MM.');
                }

                return (string) $value;

            case 'json':
                json_decode((string) $value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->fail($field, 'no contiene JSON valido.');
                }

                return (string) $value;

            case 'in':
                $options = explode(',', (string) $parameter);
                if (!in_array((string) $value, $options, true)) {
                    return $this->fail($field, 'contiene un valor no permitido.');
                }

                return (string) $value;

            case 'min':
                $min = (float) $parameter;
                if (is_numeric($value) ? (float) $value < $min : mb_strlen((string) $value) < $min) {
                    return $this->fail(
                        $field,
                        is_numeric($value)
                            ? "debe ser mayor o igual a {$parameter}."
                            : "debe tener al menos {$parameter} caracteres."
                    );
                }

                return true;

            case 'max':
                $max = (float) $parameter;
                if (is_numeric($value) ? (float) $value > $max : mb_strlen((string) $value) > $max) {
                    return $this->fail(
                        $field,
                        is_numeric($value)
                            ? "no puede ser mayor a {$parameter}."
                            : "no puede superar {$parameter} caracteres."
                    );
                }

                return true;

            case 'between':
                [$low, $high] = array_pad(explode(',', (string) $parameter), 2, '0');
                $number = (float) $value;
                if ($number < (float) $low || $number > (float) $high) {
                    return $this->fail($field, "debe estar entre {$low} y {$high}.");
                }

                return true;

            case 'length':
                if (mb_strlen((string) $value) !== (int) $parameter) {
                    return $this->fail($field, "debe tener exactamente {$parameter} caracteres.");
                }

                return true;

            case 'regex':
                if (@preg_match((string) $parameter, (string) $value) !== 1) {
                    return $this->fail($field, 'tiene un formato no valido.');
                }

                return true;

            case 'confirmed':
                if (($this->data[$field . '_confirmation'] ?? null) !== $value) {
                    return $this->fail($field, 'no coincide con la confirmacion.');
                }

                return true;

            case 'password':
                return $this->validatePassword($field, (string) $value);

            default:
                return true;
        }
    }

    private function validatePassword(string $field, string $password): mixed
    {
        $minLength = (int) Config::get('security.password.min_length', 10);

        if (mb_strlen($password) < $minLength) {
            return $this->fail($field, "debe tener al menos {$minLength} caracteres.");
        }

        if (mb_strlen($password) > 200) {
            return $this->fail($field, 'no puede superar 200 caracteres.');
        }

        $checks = 0;
        $checks += preg_match('/[a-z]/', $password);
        $checks += preg_match('/[A-Z]/', $password);
        $checks += preg_match('/[0-9]/', $password);
        $checks += preg_match('/[^a-zA-Z0-9]/', $password);

        if ($checks < 3) {
            return $this->fail(
                $field,
                'debe combinar al menos tres de: minusculas, mayusculas, numeros y simbolos.'
            );
        }

        // Rechaza las contrasenas mas usadas del mundo aunque cumplan el formato.
        $common = ['password', 'contrasena', '12345678', 'qwerty', 'admin123', 'barberia', 'peluqueria'];
        $lower = mb_strtolower($password);

        foreach ($common as $bad) {
            if (str_contains($lower, $bad)) {
                return $this->fail($field, 'es demasiado predecible; elige otra.');
            }
        }

        return true;
    }

    private static function isValidFormat(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    private function fail(string $field, string $message): bool
    {
        $this->addError($field, $message);

        return false;
    }

    private function addError(string $field, string $message): void
    {
        $label = $this->labels[$field] ?? str_replace('_', ' ', $field);
        $this->errors[$field][] = ucfirst($label) . ' ' . $message;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<string> */
    public function flatErrors(): array
    {
        return array_merge(...array_values($this->errors ?: [[]]));
    }

    public function firstError(): string
    {
        $flat = $this->flatErrors();

        return $flat[0] ?? 'Datos no validos.';
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    /** Lanza 422 con el detalle de errores si la validacion no pasa. */
    public function validateOrFail(): array
    {
        if ($this->fails()) {
            throw new HttpException(422, $this->firstError(), $this->errors);
        }

        return $this->validated;
    }
}
