<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class Inbox
{
    public function __construct(
        public string $id,
        public string $org_id,
        public string $project_id,
        public string $address,
        public string $local_part,
        public string $domain,
        public ?string $display_name,
        public bool $catch_all,
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
            Fields::string($data['address'] ?? null),
            Fields::string($data['local_part'] ?? null),
            Fields::string($data['domain'] ?? null),
            Fields::nullableString($data['display_name'] ?? null),
            Fields::bool($data['catch_all'] ?? null),
            Fields::string($data['created_at'] ?? null),
        );
    }
}
