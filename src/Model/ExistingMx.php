<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class ExistingMx
{
    /**
     * @param list<string> $hosts
     */
    public function __construct(
        public array $hosts,
        public string $suggestion,
        public string $message,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::strings($data['hosts'] ?? null),
            Fields::string($data['suggestion'] ?? null),
            Fields::string($data['message'] ?? null),
        );
    }
}
