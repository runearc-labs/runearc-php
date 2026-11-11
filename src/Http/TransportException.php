<?php

declare(strict_types=1);

namespace Runearc\Http;

use RuntimeException;

/** @internal No HTTP response was received. */
final class TransportException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $timedOut)
    {
        parent::__construct($message);
    }
}
