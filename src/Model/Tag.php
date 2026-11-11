<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class Tag
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['name'] ?? null),
            Fields::string($data['value'] ?? null),
        );
    }
}
