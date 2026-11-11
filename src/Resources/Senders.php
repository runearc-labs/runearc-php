<?php

declare(strict_types=1);

namespace Runearc\Resources;

use Runearc\Model\ListResponse;
use Runearc\Model\SenderAddress;
use Runearc\RequestOptions;

final class Senders extends Resource
{
    /**
     * Addresses you can use as `from`.
     *
     * @return ListResponse<SenderAddress>
     */
    public function list(?RequestOptions $options = null): ListResponse
    {
        return ListResponse::of(self::map($this->api->json('GET', '/v1/senders', options: $options)), SenderAddress::fromArray(...));
    }
}
