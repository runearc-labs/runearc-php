<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class EmailDetail
{
    /**
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $bcc
     * @param list<string> $reply_to
     * @param list<Tag> $tags
     * @param array<string, string> $headers
     * @param list<EmailAttachment> $attachments
     * @param list<EmailRecipient> $recipients
     */
    public function __construct(
        public string $id,
        public string $project_id,
        public ?string $domain_id,
        public string $from,
        public ?string $from_name,
        public array $to,
        public array $cc,
        public array $bcc,
        public array $reply_to,
        public string $subject,
        public string $status,
        public array $tags,
        public int $size_bytes,
        public ?string $scheduled_at,
        public string $created_at,
        public string $object,
        public string $last_event,
        public ?string $html,
        public ?string $text,
        public array $headers,
        public array $attachments,
        public array $recipients,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['id'] ?? null),
            Fields::string($data['project_id'] ?? null),
            Fields::nullableString($data['domain_id'] ?? null),
            Fields::string($data['from'] ?? null),
            Fields::nullableString($data['from_name'] ?? null),
            Fields::strings($data['to'] ?? null),
            Fields::strings($data['cc'] ?? null),
            Fields::strings($data['bcc'] ?? null),
            Fields::strings($data['reply_to'] ?? null),
            Fields::string($data['subject'] ?? null),
            Fields::string($data['status'] ?? null),
            array_map(Tag::fromArray(...), Fields::objects($data['tags'] ?? null)),
            Fields::int($data['size_bytes'] ?? null),
            Fields::nullableString($data['scheduled_at'] ?? null),
            Fields::string($data['created_at'] ?? null),
            Fields::string($data['object'] ?? null),
            Fields::string($data['last_event'] ?? null),
            Fields::nullableString($data['html'] ?? null),
            Fields::nullableString($data['text'] ?? null),
            Fields::stringMap($data['headers'] ?? null),
            array_map(EmailAttachment::fromArray(...), Fields::objects($data['attachments'] ?? null)),
            array_map(EmailRecipient::fromArray(...), Fields::objects($data['recipients'] ?? null)),
        );
    }
}
