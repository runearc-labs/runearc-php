<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class Event
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $id,
        public string $org_id,
        public string $project_id,
        public string $type,
        public array $data,
        public string $created_at,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['id'] ?? null),
            Fields::string($data['org_id'] ?? null),
            Fields::string($data['project_id'] ?? null),
            Fields::string($data['type'] ?? null),
            Fields::map($data['data'] ?? null),
            Fields::string($data['created_at'] ?? null),
        );
    }
}
