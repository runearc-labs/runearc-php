<?php

declare(strict_types=1);

namespace Runearc\Resources;

use Runearc\Model\AddedDomain;
use Runearc\Model\Domain;
use Runearc\Model\ListResponse;
use Runearc\RequestOptions;
use Runearc\Support;

final class Domains extends Resource
{
    /**
     * @param array{name: string} $domain
     * @return AddedDomain The domain with the DNS `records` to publish and `next_step`.
     */
    public function create(array $domain, ?RequestOptions $options = null): AddedDomain
    {
        return AddedDomain::fromArray(self::map($this->api->json('POST', '/v1/domains', body: ['name' => $domain['name']], options: $options)));
    }

    /**
     * @return ListResponse<Domain>
     */
    public function list(?RequestOptions $options = null): ListResponse
    {
        return ListResponse::of(self::map($this->api->json('GET', '/v1/domains', options: $options)), Domain::fromArray(...));
    }

    /**
     * `$id` is a domain id (`dom_...`) or the domain name.
     */
    public function get(string $id, ?RequestOptions $options = null): Domain
    {
        return Domain::fromArray(self::map($this->api->json('GET', '/v1/domains/' . Support::segment($id), options: $options)));
    }

    public function delete(string $id, ?RequestOptions $options = null): void
    {
        $this->api->json('DELETE', '/v1/domains/' . Support::segment($id), options: $options);
    }

    /**
     * Checks the DNS records now and returns the domain with each record's status.
     */
    public function verify(string $id, ?RequestOptions $options = null): Domain
    {
        return Domain::fromArray(self::map($this->api->json('POST', '/v1/domains/' . Support::segment($id) . '/verify', idempotent: true, options: $options)));
    }
}
