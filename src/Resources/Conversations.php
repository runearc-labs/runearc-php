<?php

declare(strict_types=1);

namespace Runearc\Resources;

use DateTimeInterface;
use Runearc\Model\Conversation;
use Runearc\Model\ConversationCounts;
use Runearc\Model\Page;
use Runearc\RequestOptions;
use Runearc\Support;

final class Conversations extends Resource
{
    /**
     * @param array{status?: string, assignee?: string, project_id?: string, inbox_id?: string, q?: string, cursor?: string, limit?: int} $filter
     * @return Page<Conversation>
     */
    public function list(array $filter = [], ?RequestOptions $options = null): Page
    {
        return Page::of(self::map($this->api->json('GET', '/v1/conversations', query: $filter, options: $options)), Conversation::fromArray(...));
    }

    public function counts(?RequestOptions $options = null): ConversationCounts
    {
        return ConversationCounts::fromArray(self::map($this->api->json('GET', '/v1/conversations/counts', options: $options)));
    }

    /**
     * `snoozed_until` and `assignee_user_id` accept null to wake or unassign.
     *
     * @param array{status?: string, assignee_user_id?: string|null, snoozed_until?: string|DateTimeInterface|null} $update
     */
    public function update(string $id, array $update, ?RequestOptions $options = null): Conversation
    {
        if (array_key_exists('snoozed_until', $update)) {
            $update['snoozed_until'] = Support::time($update['snoozed_until']);
        }

        return Conversation::fromArray(self::map($this->api->json('PATCH', '/v1/conversations/' . Support::segment($id), body: Support::object($update), options: $options)));
    }

    public function read(string $id, ?RequestOptions $options = null): void
    {
        $this->api->json('POST', '/v1/conversations/' . Support::segment($id) . '/read', idempotent: true, options: $options);
    }

    public function unread(string $id, ?RequestOptions $options = null): void
    {
        $this->api->json('DELETE', '/v1/conversations/' . Support::segment($id) . '/read', options: $options);
    }
}
