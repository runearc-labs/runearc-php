<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class AddedDomain
{
    /**
     * @param list<DomainRecord> $records
     */
    public function __construct(
        public string $id,
        public string $org_id,
        public string $project_id,
        public string $name,
        public ?string $parent_domain_id,
        public string $status,
        public ?string $provider,
        public bool $can_auto_configure,
        public string $dkim_selector,
        public ?string $verified_at,
        public ?string $last_checked_at,
        public string $created_at,
        public array $records,
        public string $next_step,
        public ?ExistingMx $existing_mx,
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
            Fields::nullableString($data['parent_domain_id'] ?? null),
            Fields::string($data['status'] ?? null),
            Fields::nullableString($data['provider'] ?? null),
            Fields::bool($data['can_auto_configure'] ?? null),
            Fields::string($data['dkim_selector'] ?? null),
            Fields::nullableString($data['verified_at'] ?? null),
            Fields::nullableString($data['last_checked_at'] ?? null),
            Fields::string($data['created_at'] ?? null),
            array_map(DomainRecord::fromArray(...), Fields::objects($data['records'] ?? null)),
            Fields::string($data['next_step'] ?? null),
            Fields::optional($data['existing_mx'] ?? null, ExistingMx::fromArray(...)),
        );
    }
}
