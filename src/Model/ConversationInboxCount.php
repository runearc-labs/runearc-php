<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class ConversationInboxCount
{
    public function __construct(
        public string $inbox_id,
        public string $project_id,
        public int $open,
        public int $unread,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['inbox_id'] ?? null),
            Fields::string($data['project_id'] ?? null),
            Fields::int($data['open'] ?? null),
            Fields::int($data['unread'] ?? null),
        );
    }
}
