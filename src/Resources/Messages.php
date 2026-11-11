<?php

declare(strict_types=1);

namespace Runearc\Resources;

use DateTimeInterface;
use Runearc\Exception\ApiException;
use Runearc\Model\Message;
use Runearc\Model\Page;
use Runearc\Model\Waited;
use Runearc\RequestOptions;
use Runearc\Support;

final class Messages extends Resource
{
    /**
     * Messages of an inbox, newest first.
     *
     * @param array{limit?: int, cursor?: string} $params
     * @return Page<Message>
     */
    public function list(string $inboxId, array $params = [], ?RequestOptions $options = null): Page
    {
        return Page::of(self::map($this->api->json('GET', '/v1/inboxes/' . Support::segment($inboxId) . '/messages', query: $params, options: $options)), Message::fromArray(...));
    }

    public function get(string $id, ?RequestOptions $options = null): Message
    {
        return Message::fromArray(self::map($this->api->json('GET', '/v1/messages/' . Support::segment($id), options: $options)));
    }

    /** The message as received (RFC 822). */
    public function raw(string $id, ?RequestOptions $options = null): string
    {
        return $this->api->text('/v1/messages/' . Support::segment($id) . '/raw', options: $options);
    }

    /**
     * Blocks until a matching message arrives (up to `timeout` seconds, max 120) and
     * returns `message`, `codes` and `links`. Returns null when nothing arrived in
     * time; calling again is safe.
     *
     * @param array{from?: string, subject?: string, to?: string, timeout?: int, after?: string|DateTimeInterface} $params
     */
    public function wait(string $inboxId, array $params = [], ?RequestOptions $options = null): ?Waited
    {
        $secs = $params['timeout'] ?? 30;
        $params['timeout'] = $secs;
        try {
            return Waited::fromArray(self::map($this->api->json(
                'GET',
                '/v1/inboxes/' . Support::segment($inboxId) . '/wait',
                query: $params,
                options: new RequestOptions($options?->idempotencyKey, $options->timeoutMs ?? $secs * 1000 + 15_000),
            )));
        } catch (ApiException $e) {
            if ($e->errorCode === 'wait_timeout') {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Sends a new message from an inbox.
     *
     * @param array<string, mixed> $message
     */
    public function send(string $inboxId, array $message, ?RequestOptions $options = null): Message
    {
        return Message::fromArray(self::map($this->api->json(
            'POST',
            '/v1/inboxes/' . Support::segment($inboxId) . '/messages',
            body: $message,
            sendsMail: true,
            options: $options,
        )));
    }

    /**
     * Replies in the thread of a message.
     *
     * @param array{text?: string, html?: string, reply_all?: bool, headers?: array<string, string>, attachments?: list<array<string, mixed>>} $reply
     */
    public function reply(string $messageId, array $reply, ?RequestOptions $options = null): Message
    {
        return Message::fromArray(self::map($this->api->json(
            'POST',
            '/v1/messages/' . Support::segment($messageId) . '/reply',
            body: Support::object($reply),
            sendsMail: true,
            options: $options,
        )));
    }
}
