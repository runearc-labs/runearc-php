<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class Waited
{
    /**
     * @param list<string> $codes
     * @param list<string> $links
     */
    public function __construct(
        public Message $message,
        public array $codes,
        public array $links,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Message::fromArray(Fields::map($data['message'] ?? null)),
            Fields::strings($data['codes'] ?? null),
            Fields::strings($data['links'] ?? null),
        );
    }
}
