<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class EmailRecipient
{
    public function __construct(
        public string $address,
        public string $kind,
        public string $status,
        public ?string $response,
        public string $updated_at,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['address'] ?? null),
            Fields::string($data['kind'] ?? null),
            Fields::string($data['status'] ?? null),
            Fields::nullableString($data['response'] ?? null),
            Fields::string($data['updated_at'] ?? null),
        );
    }
}
