<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class Suppression
{
    public function __construct(
        public string $address,
        public string $reason,
        public string $created_at,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['address'] ?? null),
            Fields::string($data['reason'] ?? null),
            Fields::string($data['created_at'] ?? null),
        );
    }
}
