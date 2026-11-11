<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class ApiKey
{
    public function __construct(
        public string $id,
        public string $org_id,
        public string $project_id,
        public string $name,
        public string $prefix,
        public string $scope,
        public string $permissions,
        public ?string $domain_id,
        public ?string $last_used_at,
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
            Fields::string($data['name'] ?? null),
            Fields::string($data['prefix'] ?? null),
            Fields::string($data['scope'] ?? null),
            Fields::string($data['permissions'] ?? null),
            Fields::nullableString($data['domain_id'] ?? null),
            Fields::nullableString($data['last_used_at'] ?? null),
            Fields::string($data['created_at'] ?? null),
        );
    }
}
