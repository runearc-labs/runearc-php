<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class WebhookEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $timestamp,
        public array $data,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Fields::string($data['id'] ?? null),
            Fields::string($data['type'] ?? null),
            Fields::string($data['timestamp'] ?? null),
            Fields::map($data['data'] ?? null),
        );
    }
}
