<?php

declare(strict_types=1);

namespace Runearc;

/** Options of `emails->batch()`. */
final class BatchOptions extends RequestOptions
{
    public function __construct(
        ?string $idempotencyKey = null,
        ?int $timeoutMs = null,
        /**
         * `strict` (default): one invalid item refuses the whole batch with `invalid_batch`.
         * `permissive`: valid items send, invalid ones come back in `errors`.
         */
        public readonly ?string $batchValidation = null,
    ) {
        parent::__construct($idempotencyKey, $timeoutMs);
    }
}
