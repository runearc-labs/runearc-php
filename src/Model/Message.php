<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class Message
{
    /**
     * @param list<string> $references
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $delivered_to
     * @param list<MessageAttachment> $attachments
     */
    public function __construct(
        public string $id,
        public string $org_id,
        public string $project_id,
        public string $inbox_id,
        public string $thread_id,
        public string $direction,
        public ?string $rfc_message_id,
        public ?string $in_reply_to,
        public array $references,
        public string $from,
        public array $to,
        public array $cc,
        public array $delivered_to,
        public string $subject,
        public ?string $text,
        public ?string $html,
        public int $size_bytes,
        public string $status,
        public bool $spam,
        public mixed $auth_results,
        public string $created_at,
        public array $attachments,
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
            Fields::string($data['thread_id'] ?? null),
            Fields::string($data['direction'] ?? null),
            Fields::nullableString($data['rfc_message_id'] ?? null),
            Fields::nullableString($data['in_reply_to'] ?? null),
            Fields::strings($data['references'] ?? null),
            Fields::string($data['from'] ?? null),
            Fields::strings($data['to'] ?? null),
            Fields::strings($data['cc'] ?? null),
            Fields::strings($data['delivered_to'] ?? null),
            Fields::string($data['subject'] ?? null),
            Fields::nullableString($data['text'] ?? null),
            Fields::nullableString($data['html'] ?? null),
            Fields::int($data['size_bytes'] ?? null),
            Fields::string($data['status'] ?? null),
            Fields::bool($data['spam'] ?? null),
            $data['auth_results'] ?? null,
            Fields::string($data['created_at'] ?? null),
            array_map(MessageAttachment::fromArray(...), Fields::objects($data['attachments'] ?? null)),
        );
    }
}
