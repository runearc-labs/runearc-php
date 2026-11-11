<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class DomainRecord
{
    public function __construct(
        public string $kind,
        public string $name,
        public string $type,
        public string $value,
        public ?int $priority,
        public bool $required,
        public string $status,
        public ?string $fix,
        public ?string $last_checked_at,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['kind'] ?? null),
            Fields::string($data['name'] ?? null),
            Fields::string($data['type'] ?? null),
            Fields::string($data['value'] ?? null),
            Fields::nullableInt($data['priority'] ?? null),
            Fields::bool($data['required'] ?? null),
            Fields::string($data['status'] ?? null),
            Fields::nullableString($data['fix'] ?? null),
            Fields::nullableString($data['last_checked_at'] ?? null),
        );
    }
}
