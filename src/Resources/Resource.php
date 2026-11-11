<?php

declare(strict_types=1);

namespace Runearc\Resources;

use Runearc\Exception\InvalidResponseException;
use Runearc\Http\Api;

/** @internal */
abstract class Resource
{
    public function __construct(protected readonly Api $api)
    {
    }

    /**
     * Narrows a decoded JSON answer to the object or list the API documents.
     *
     * @return array<string, mixed>
     */
    protected static function map(mixed $decoded): array
    {
        if (!is_array($decoded)) {
            throw new InvalidResponseException(
                'the API sent a response that is not a JSON object',
                'invalid_response',
                'invalid',
                0,
                'Check that baseUrl points at the Runearc API',
            );
        }
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
