<?php

declare(strict_types=1);

namespace Runearc\Resources;

use Runearc\Model\ApiKey;
use Runearc\Model\CreatedKey;
use Runearc\Model\ListResponse;
use Runearc\RequestOptions;
use Runearc\Support;

final class Keys extends Resource
{
    /**
     * The response has the key's `secret`, shown once.
     *
     * @param array{name: string, permissions?: string, inbox_id?: string, domain_id?: string} $key
     */
    public function create(array $key, ?RequestOptions $options = null): CreatedKey
    {
        return CreatedKey::fromArray(self::map($this->api->json('POST', '/v1/keys', body: $key, options: $options)));
    }

    /**
     * @return ListResponse<ApiKey>
     */
    public function list(?RequestOptions $options = null): ListResponse
    {
        return ListResponse::of(self::map($this->api->json('GET', '/v1/keys', options: $options)), ApiKey::fromArray(...));
    }

    /** Revokes the key at once. */
    public function delete(string $id, ?RequestOptions $options = null): void
    {
        $this->api->json('DELETE', '/v1/keys/' . Support::segment($id), options: $options);
    }
}
