<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class SenderAddress
{
    public function __construct(
        public string $address,
        public string $domain,
        public string $domain_id,
        public bool $verified,
        public string $kind,
        public ?string $display_name,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['address'] ?? null),
            Fields::string($data['domain'] ?? null),
            Fields::string($data['domain_id'] ?? null),
            Fields::bool($data['verified'] ?? null),
            Fields::string($data['kind'] ?? null),
            Fields::nullableString($data['display_name'] ?? null),
        );
    }
}
