<?php

declare(strict_types=1);

namespace Runearc\Exception;

/** Thrown when a payload is not a genuine, fresh Runearc webhook. */
class WebhookVerificationException extends RunearcException
{
    public function __construct(string $errorCode, string $message, ?string $fix = null)
    {
        parent::__construct($message, $errorCode, 'webhook', 0, $fix);
    }
}
