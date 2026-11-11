<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class EmailAttachment
{
    public function __construct(
        public string $id,
        public string $filename,
        public string $content_type,
        public ?string $content_id,
        public int $size_bytes,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['id'] ?? null),
            Fields::string($data['filename'] ?? null),
            Fields::string($data['content_type'] ?? null),
            Fields::nullableString($data['content_id'] ?? null),
            Fields::int($data['size_bytes'] ?? null),
        );
    }
}
