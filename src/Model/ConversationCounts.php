<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class ConversationCounts
{
    /**
     * @param list<ConversationProjectCount> $by_project
     * @param list<ConversationInboxCount> $by_inbox
     */
    public function __construct(
        public int $open,
        public int $assigned_to_me,
        public int $unassigned,
        public array $by_project,
        public array $by_inbox,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::int($data['open'] ?? null),
            Fields::int($data['assigned_to_me'] ?? null),
            Fields::int($data['unassigned'] ?? null),
            array_map(ConversationProjectCount::fromArray(...), Fields::objects($data['by_project'] ?? null)),
            array_map(ConversationInboxCount::fromArray(...), Fields::objects($data['by_inbox'] ?? null)),
        );
    }
}
