<?php

declare(strict_types=1);

namespace Runearc;

/** The last argument of every resource method. */
class RequestOptions
{
    public function __construct(
        /**
         * Makes the request safe to repeat: the API returns the first result for the
         * same key for 24 hours. Mail sending calls get a random one when you give none.
         */
        public readonly ?string $idempotencyKey = null,
        /** Overrides the client's per attempt timeout, in milliseconds. */
        public readonly ?int $timeoutMs = null,
    ) {
    }
}
