<?php

declare(strict_types=1);

namespace Runearc\Model;

use Closure;
use Runearc\Fields;

/**
 * One page of a cursor-paged list: `data` and `next_cursor` (null on the last page).
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param list<T> $data
     */
    public function __construct(
        public array $data,
        public ?string $next_cursor,
    ) {
    }

    /**
     * @template R
     * @param array<string, mixed> $data
     * @param Closure(array<string, mixed>): R $build
     * @return self<R>
     */
    public static function of(array $data, Closure $build): self
    {
        return new self(
            array_map($build, Fields::objects($data['data'] ?? null)),
            Fields::nullableString($data['next_cursor'] ?? null),
        );
    }
}
