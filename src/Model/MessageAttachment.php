<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class MessageAttachment
{
    public function __construct(
        public string $id,
        public string $org_id,
        public string $message_id,
        public ?string $filename,
        public string $content_type,
        public int $size_bytes,
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
            Fields::string($data['message_id'] ?? null),
            Fields::nullableString($data['filename'] ?? null),
            Fields::string($data['content_type'] ?? null),
            Fields::int($data['size_bytes'] ?? null),
            Fields::string($data['created_at'] ?? null),
        );
    }
}
