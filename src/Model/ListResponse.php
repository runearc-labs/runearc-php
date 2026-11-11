<?php

declare(strict_types=1);

namespace Runearc\Model;

use Closure;
use Runearc\Fields;

/**
 * A list answer: `data`.
 *
 * @template T
 */
final readonly class ListResponse
{
    /**
     * @param list<T> $data
     */
    public function __construct(
        public array $data,
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
        return new self(array_map($build, Fields::objects($data['data'] ?? null)));
    }
}
