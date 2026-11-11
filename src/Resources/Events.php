<?php

declare(strict_types=1);

namespace Runearc\Resources;

use Runearc\Model\EventPage;
use Runearc\RequestOptions;

final class Events extends Resource
{
    /**
     * Events oldest first. Pass the returned `next_after` as `after` to continue.
     *
     * @param array{after?: string, limit?: int} $params
     */
    public function list(array $params = [], ?RequestOptions $options = null): EventPage
    {
        return EventPage::fromArray(self::map($this->api->json('GET', '/v1/events', query: $params, options: $options)));
    }
}
