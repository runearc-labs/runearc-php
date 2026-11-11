<?php

declare(strict_types=1);

namespace Runearc\Resources;

use Runearc\Model\ListResponse;
use Runearc\Model\Suppression;
use Runearc\RequestOptions;
use Runearc\Support;

final class Suppressions extends Resource
{
    /**
     * @return ListResponse<Suppression>
     */
    public function list(?RequestOptions $options = null): ListResponse
    {
        return ListResponse::of(self::map($this->api->json('GET', '/v1/suppressions', options: $options)), Suppression::fromArray(...));
    }

    /** Lets mail go to the address again. */
    public function delete(string $address, ?RequestOptions $options = null): void
    {
        $this->api->json('DELETE', '/v1/suppressions/' . Support::segment($address), options: $options);
    }
}
