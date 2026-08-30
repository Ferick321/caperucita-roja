<?php

declare(strict_types=1);

namespace App\Core;

/** Error HTTP con mensaje apto para mostrarse al usuario. */
class HttpException extends \RuntimeException
{
    private int $statusCode;

    /** @var array<string,mixed> */
    private array $details;

    /** @param array<string,mixed> $details */
    public function __construct(int $statusCode, string $message = '', array $details = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);

        $this->statusCode = $statusCode;
        $this->details = $details;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string,mixed> */
    public function details(): array
    {
        return $this->details;
    }
}
