<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class ConversationProjectCount
{
    public function __construct(
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
            Fields::string($data['project_id'] ?? null),
            Fields::int($data['open'] ?? null),
            Fields::int($data['unread'] ?? null),
        );
    }
}
