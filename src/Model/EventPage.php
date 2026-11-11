<?php

declare(strict_types=1);

namespace Runearc\Model;

use Runearc\Fields;

final readonly class EventPage
{
    /**
     * @param list<Event> $data
     */
    public function __construct(
        public array $data,
        public ?string $next_after,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            array_map(Event::fromArray(...), Fields::objects($data['data'] ?? null)),
            Fields::nullableString($data['next_after'] ?? null),
        );
    }
}
