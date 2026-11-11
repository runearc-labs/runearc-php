<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class ThreadWithMessages
{
    /**
     * @param list<Message> $messages
     */
    public function __construct(
        public string $id,
        public string $org_id,
        public string $project_id,
        public string $inbox_id,
        public string $subject,
        public string $last_message_at,
        public int $message_count,
        public string $created_at,
        public string $status,
        public ?string $assignee_user_id,
        public ?string $snoozed_until,
        public ?string $last_inbound_at,
        public array $messages,
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
            Fields::string($data['project_id'] ?? null),
            Fields::string($data['inbox_id'] ?? null),
            Fields::string($data['subject'] ?? null),
            Fields::string($data['last_message_at'] ?? null),
            Fields::int($data['message_count'] ?? null),
            Fields::string($data['created_at'] ?? null),
            Fields::string($data['status'] ?? null),
            Fields::nullableString($data['assignee_user_id'] ?? null),
            Fields::nullableString($data['snoozed_until'] ?? null),
            Fields::nullableString($data['last_inbound_at'] ?? null),
            array_map(Message::fromArray(...), Fields::objects($data['messages'] ?? null)),
        );
    }
}
