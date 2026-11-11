<?php

declare(strict_types=1);

namespace Runearc\Exception;

use RuntimeException;
use Throwable;

/**
 * Every failure the SDK reports. API errors carry the API's code, message and fix;
 * failures before a response arrives have status 0.
 */
class RunearcException extends RuntimeException
{
    /**
     * @param list<array<string, mixed>>|null $errors Only for `invalid_batch`: every refused item.
     */
    public function __construct(
        string $message,
        /** A stable machine readable code, such as `invalid_from` or `rate_limited`. */
        public readonly string $errorCode,
        /** The API's error kind, such as `invalid` or `not_found`; `network` and `config` before a response. */
        public readonly string $kind,
        /** The HTTP status, or 0 when no response arrived. */
        public readonly int $status = 0,
        /** What to do about it, when there is something you can do. */
        public readonly ?string $fix = null,
        public readonly ?array $errors = null,
        /** Seconds the API asked us to wait (`Retry-After`), when it sent one. */
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'status' => $this->status,
            'code' => $this->errorCode,
            'kind' => $this->kind,
            'message' => $this->getMessage(),
            'fix' => $this->fix,
        ];
        if ($this->errors !== null) {
            $out['errors'] = $this->errors;
        }

        return $out;
    }
}
