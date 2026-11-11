<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class Delivery
{
    public function __construct(
        public string $id,
        public string $webhook_id,
        public string $event_id,
        public ?string $replay_of,
        public int $attempt,
        public string $status,
        public ?int $response_code,
        public ?string $response_body_snippet,
        public ?string $next_attempt_at,
        public string $created_at,
        public string $updated_at,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['id'] ?? null),
            Fields::string($data['webhook_id'] ?? null),
            Fields::string($data['event_id'] ?? null),
            Fields::nullableString($data['replay_of'] ?? null),
            Fields::int($data['attempt'] ?? null),
            Fields::string($data['status'] ?? null),
            Fields::nullableInt($data['response_code'] ?? null),
            Fields::nullableString($data['response_body_snippet'] ?? null),
            Fields::nullableString($data['next_attempt_at'] ?? null),
            Fields::string($data['created_at'] ?? null),
            Fields::string($data['updated_at'] ?? null),
        );
    }
}
