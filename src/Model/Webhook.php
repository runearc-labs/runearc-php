<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class Webhook
{
    /**
     * @param list<string> $event_types
     */
    public function __construct(
        public string $id,
        public string $org_id,
        public string $project_id,
        public string $url,
        public array $event_types,
        public ?string $inbox_id,
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
            Fields::string($data['url'] ?? null),
            Fields::strings($data['event_types'] ?? null),
            Fields::nullableString($data['inbox_id'] ?? null),
            Fields::string($data['created_at'] ?? null),
        );
    }
}
