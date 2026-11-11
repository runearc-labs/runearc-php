<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class SendEmailResponse
{
    public function __construct(
        public string $id,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['id'] ?? null),
        );
    }
}
