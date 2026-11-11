<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class BatchResponse
{
    /**
     * @param list<SendEmailResponse> $data
     * @param list<BatchItemError> $errors
     */
    public function __construct(
        public array $data,
        public array $errors,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            array_map(SendEmailResponse::fromArray(...), Fields::objects($data['data'] ?? null)),
            array_map(BatchItemError::fromArray(...), Fields::objects($data['errors'] ?? null)),
        );
    }
}
