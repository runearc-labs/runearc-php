<?php

declare(strict_types=1);

namespace Runearc\Resources;

use Runearc\Model\Inbox;
use Runearc\Model\ListResponse;
use Runearc\RequestOptions;
use Runearc\Support;

final class Inboxes extends Resource
{
    /**
     * @param array{local_part?: string, display_name?: string, domain?: string} $inbox
     */
    public function create(array $inbox = [], ?RequestOptions $options = null): Inbox
    {
        return Inbox::fromArray(self::map($this->api->json('POST', '/v1/inboxes', body: Support::object($inbox), options: $options)));
    }

    /**
     * @return ListResponse<Inbox>
     */
    public function list(?RequestOptions $options = null): ListResponse
    {
        return ListResponse::of(self::map($this->api->json('GET', '/v1/inboxes', options: $options)), Inbox::fromArray(...));
    }

    public function get(string $id, ?RequestOptions $options = null): Inbox
    {
        return Inbox::fromArray(self::map($this->api->json('GET', '/v1/inboxes/' . Support::segment($id), options: $options)));
    }

    public function delete(string $id, ?RequestOptions $options = null): void
    {
        $this->api->json('DELETE', '/v1/inboxes/' . Support::segment($id), options: $options);
    }
}
