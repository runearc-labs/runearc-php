<?php

declare(strict_types=1);

namespace Runearc\Http;

/** @internal */
final class HttpResponse
{
    /**
     * @param array<string, string> $headers Lower case names.
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
