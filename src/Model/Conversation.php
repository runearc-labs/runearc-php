<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class Conversation
{
    public function __construct(
        public string $id,
        public ConversationProject $project,
        public ConversationInbox $inbox,
        public string $subject,
        public string $snippet,
        public string $status,
        public ?string $snoozed_until,
        public ?ConversationAssignee $assignee,
        public bool $unread,
        public int $message_count,
        public string $last_message_at,
        public ?string $last_inbound_at,
        public string $last_from,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['id'] ?? null),
            ConversationProject::fromArray(Fields::map($data['project'] ?? null)),
            ConversationInbox::fromArray(Fields::map($data['inbox'] ?? null)),
            Fields::string($data['subject'] ?? null),
            Fields::string($data['snippet'] ?? null),
            Fields::string($data['status'] ?? null),
            Fields::nullableString($data['snoozed_until'] ?? null),
            Fields::optional($data['assignee'] ?? null, ConversationAssignee::fromArray(...)),
            Fields::bool($data['unread'] ?? null),
            Fields::int($data['message_count'] ?? null),
            Fields::string($data['last_message_at'] ?? null),
            Fields::nullableString($data['last_inbound_at'] ?? null),
            Fields::string($data['last_from'] ?? null),
        );
    }
}
