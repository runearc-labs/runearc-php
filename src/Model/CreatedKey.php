<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class CreatedKey
{
    public function __construct(
        public ApiKey $key,
        public string $secret,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ApiKey::fromArray(Fields::map($data['key'] ?? null)),
            Fields::string($data['secret'] ?? null),
        );
    }
}
