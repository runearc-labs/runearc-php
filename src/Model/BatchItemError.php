<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class BatchItemError
{
    public function __construct(
        public int $index,
        public string $code,
        public string $message,
        public ?string $fix,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::int($data['index'] ?? null),
            Fields::string($data['code'] ?? null),
            Fields::string($data['message'] ?? null),
            Fields::nullableString($data['fix'] ?? null),
        );
    }
}
