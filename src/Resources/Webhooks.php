<?php

declare(strict_types=1);

namespace Runearc\Resources;

use Runearc\Model\CreatedWebhook;
use Runearc\Model\Delivery;
use Runearc\Model\ListResponse;
use Runearc\Model\Webhook;
use Runearc\RequestOptions;
use Runearc\Support;
use Runearc\WebhookSignature;

final class Webhooks extends Resource
{
    /**
     * The response has the signing `secret`, shown once.
     *
     * @param array{url: string, event_types?: list<string>, inbox_id?: string|null} $webhook
     */
    public function create(array $webhook, ?RequestOptions $options = null): CreatedWebhook
    {
        return CreatedWebhook::fromArray(self::map($this->api->json('POST', '/v1/webhooks', body: $webhook, options: $options)));
    }

    /**
     * @return ListResponse<Webhook>
     */
    public function list(?RequestOptions $options = null): ListResponse
    {
        return ListResponse::of(self::map($this->api->json('GET', '/v1/webhooks', options: $options)), Webhook::fromArray(...));
    }

    public function delete(string $id, ?RequestOptions $options = null): void
    {
        $this->api->json('DELETE', '/v1/webhooks/' . Support::segment($id), options: $options);
    }

    /**
     * @param array{limit?: int} $params
     * @return ListResponse<Delivery>
     */
    public function deliveries(string $id, array $params = [], ?RequestOptions $options = null): ListResponse
    {
        return ListResponse::of(self::map($this->api->json('GET', '/v1/webhooks/' . Support::segment($id) . '/deliveries', query: $params, options: $options)), Delivery::fromArray(...));
    }

    /**
     * Delivers the event of a past delivery again.
     */
    public function replay(string $id, string $deliveryId, ?RequestOptions $options = null): Delivery
    {
        return Delivery::fromArray(self::map($this->api->json(
            'POST',
            '/v1/webhooks/' . Support::segment($id) . '/deliveries/' . Support::segment($deliveryId) . '/replay',
            options: $options,
        )));
    }

    /**
     * Same as `WebhookSignature::verify()`.
     *
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    public function verify(string $payload, array $headers, string $secret, int $toleranceSeconds = WebhookSignature::TOLERANCE_SECONDS, ?int $now = null): array
    {
        return WebhookSignature::verify($payload, $headers, $secret, $toleranceSeconds, $now);
    }
}
