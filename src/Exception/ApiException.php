<?php

declare(strict_types=1);

namespace Runearc\Exception;

use Throwable;

/** The API answered with an error status. */
class ApiException extends RunearcException
{
    /**
     * Picks the subclass for an HTTP status.
     *
     * @param list<array<string, mixed>>|null $errors
     */
    public static function fromResponse(
        int $status,
        string $errorCode,
        string $message,
        ?string $fix = null,
        ?array $errors = null,
        ?int $retryAfter = null,
        ?Throwable $previous = null,
    ): self {
        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 403 => PermissionException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            $status >= 400 => InvalidRequestException::class,
            default => self::class,
        };

        return new $class($message, $errorCode, self::kindFor($status), $status, $fix, $errors, $retryAfter, $previous);
    }

    /** The API's error kinds, by status. */
    public static function kindFor(int $status): string
    {
        return match (true) {
            $status === 401 => 'unauthorized',
            $status === 402 => 'limit_reached',
            $status === 403 => 'forbidden',
            $status === 404 => 'not_found',
            $status === 409 => 'conflict',
            $status === 429 => 'rate_limited',
            $status === 503 => 'unavailable',
            $status >= 500 => 'internal',
            $status >= 400 => 'invalid',
            default => 'unknown',
        };
    }
}
