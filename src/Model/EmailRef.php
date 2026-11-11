<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class EmailRef
{
    public function __construct(
        public string $object,
        public string $id,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['object'] ?? null),
            Fields::string($data['id'] ?? null),
        );
    }
}
