<?php

declare(strict_types=1);

namespace Runearc\Resources;

use Runearc\Model\ListResponse;
use Runearc\Model\Thread;
use Runearc\Model\ThreadWithMessages;
use Runearc\RequestOptions;
use Runearc\Support;

final class Threads extends Resource
{
    /**
     * @return ListResponse<Thread>
     */
    public function list(string $inboxId, ?RequestOptions $options = null): ListResponse
    {
        return ListResponse::of(self::map($this->api->json('GET', '/v1/inboxes/' . Support::segment($inboxId) . '/threads', options: $options)), Thread::fromArray(...));
    }

    /**
     * A thread with its `messages`.
     */
    public function get(string $id, ?RequestOptions $options = null): ThreadWithMessages
    {
        return ThreadWithMessages::fromArray(self::map($this->api->json('GET', '/v1/threads/' . Support::segment($id), options: $options)));
    }
}
