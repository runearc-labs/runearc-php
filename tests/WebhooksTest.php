<?php

declare(strict_types=1);

namespace Runearc\Tests;

use Runearc\Exception\WebhookVerificationException;
use Runearc\Model\Delivery;
use Runearc\Model\ListResponse;
use Runearc\Model\Webhook;
use Runearc\Tests\Support\StubServer;
use Runearc\WebhookSignature;

final class WebhooksTest extends TestCase
{
    // Computed independently of the SDK, the way runearc_core::webhooks::sign does it.
    private static function sign(string $secret, string $id, int $ts, string $body): string
    {
        $key = base64_decode(preg_replace('/^whsec_/', '', $secret) ?? '', true);
        self::assertIsString($key);

        return 'v1,' . base64_encode(hash_hmac('sha256', "$id.$ts.$body", $key, true));
    }

    private static function secret(): string
    {
        return 'whsec_' . base64_encode(random_bytes(32));
    }

    private const BODY = '{"id":"evt_1","type":"message.delivered","timestamp":"2026-10-14T10:00:00.000Z","data":{"email_id":"eml_1"}}';

    /**
     * @return array<string, string>
     */
    private static function headers(string $secret, int $ts): array
    {
        return ['webhook-id' => 'evt_1', 'webhook-timestamp' => (string) $ts, 'webhook-signature' => self::sign($secret, 'evt_1', $ts, self::BODY)];
    }

    public function testMatchesTheStandardWebhooksTestVector(): void
    {
        $event = WebhookSignature::verify(
            '{"test": 2432232314}',
            [
                'webhook-id' => 'msg_p5jXN8AQM9LWM0D4loKWxJek',
                'webhook-timestamp' => '1614265330',
                'webhook-signature' => 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=',
            ],
            'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw',
            now: 1614265330,
        );
        self::assertSame(['test' => 2432232314], $event);
    }

    public function testAcceptsAValidSignatureInAnyHeaderShape(): void
    {
        $secret = self::secret();
        $now = time();
        $headers = self::headers($secret, $now);
        $event = WebhookSignature::verify(self::BODY, $headers, $secret);
        self::assertSame('message.delivered', $event['type']);

        $upper = array_change_key_case($headers, CASE_UPPER);
        self::assertSame($event, WebhookSignature::verify(self::BODY, $upper, $secret));

        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        self::assertSame($event, WebhookSignature::verify(self::BODY, $server, $secret));

        $listed = [...$headers, 'webhook-signature' => [$headers['webhook-signature']]];
        self::assertSame($event, WebhookSignature::verify(self::BODY, $listed, $secret));

        // Several signatures (key rotation): any match passes.
        $multi = [...$headers, 'webhook-signature' => 'v1,AAAA ' . $headers['webhook-signature']];
        self::assertSame($event, WebhookSignature::verify(self::BODY, $multi, $secret));
    }

    public function testRefusesTamperedBodiesWrongSecretsStaleTimestampsAndMissingHeaders(): void
    {
        $secret = self::secret();
        $now = time();
        $headers = self::headers($secret, $now);

        $tampered = $this->failure(fn () => WebhookSignature::verify(str_replace('eml_1', 'eml_2', self::BODY), $headers, $secret));
        self::assertInstanceOf(WebhookVerificationException::class, $tampered);
        self::assertSame('invalid_signature', $tampered->errorCode);
        self::assertSame('webhook', $tampered->kind);

        self::assertSame('invalid_signature', $this->failure(fn () => WebhookSignature::verify(self::BODY, $headers, self::secret()))->errorCode);

        $old = $now - 301;
        $stale = $this->failure(fn () => WebhookSignature::verify(self::BODY, self::headers($secret, $old), $secret));
        self::assertSame('timestamp_out_of_range', $stale->errorCode);
        // The same timestamp passes with a wider tolerance.
        self::assertSame('evt_1', WebhookSignature::verify(self::BODY, self::headers($secret, $old), $secret, toleranceSeconds: 600)['id']);

        $future = $this->failure(fn () => WebhookSignature::verify(self::BODY, self::headers($secret, $now + 301), $secret));
        self::assertSame('timestamp_out_of_range', $future->errorCode);

        $missing = $this->failure(fn () => WebhookSignature::verify(self::BODY, ['webhook-id' => 'evt_1'], $secret));
        self::assertSame('missing_headers', $missing->errorCode);

        $badTs = $this->failure(fn () => WebhookSignature::verify(self::BODY, [...$headers, 'webhook-timestamp' => 'soon'], $secret));
        self::assertSame('invalid_timestamp', $badTs->errorCode);

        $badSecret = $this->failure(fn () => WebhookSignature::verify(self::BODY, $headers, 'whsec_***'));
        self::assertSame('invalid_secret', $badSecret->errorCode);

        $notJson = 'not json';
        $signed = ['webhook-id' => 'evt_1', 'webhook-timestamp' => (string) $now, 'webhook-signature' => self::sign($secret, 'evt_1', $now, $notJson)];
        self::assertSame('invalid_payload', $this->failure(fn () => WebhookSignature::verify($notJson, $signed, $secret))->errorCode);
    }

    public function testCreatesAWebhookReceivesARealSignedDeliveryVerifiesListsReplaysAndDeletes(): void
    {
        $receiver = new StubServer([['action' => 'respond', 'status' => 200, 'body' => 'ok']]);
        try {
            $mb = self::client();
            $hook = $mb->webhooks->create(['url' => $receiver->url . '/hook', 'event_types' => ['message.delivered']]);
            self::assertNotEmpty($hook->id);
            self::assertStringStartsWith('whsec_', $hook->secret);
            self::assertSame(['message.delivered'], $hook->event_types);
            self::assertContains($hook->id, array_map(static fn (Webhook $w): string => $w->id, $mb->webhooks->list()->data));

            $sent = $mb->emails->send(['from' => self::from(), 'to' => self::testAddress(), 'subject' => self::unique('hook'), 'text' => 'hi']);
            $id = $sent->id;
            $first = self::eventually(static function () use ($receiver, $id): ?array {
                foreach ($receiver->requests() as $r) {
                    if (str_contains($r['body'], $id)) {
                        return $r;
                    }
                }

                return null;
            }, 30_000);
            $event = $mb->webhooks->verify($first['body'], $first['headers'], $hook->secret);
            self::assertSame('message.delivered', $event['type']);
            self::assertSame($first['headers']['webhook-id'], $event['id']);
            self::assertStringContainsString($id, (string) json_encode($event['data']));

            $deliveries = self::eventually(static function () use ($mb, $hook): ?ListResponse {
                $d = $mb->webhooks->deliveries($hook->id, ['limit' => 10]);
                $pending = array_filter($d->data, static fn (Delivery $x): bool => $x->status === 'pending');

                return $d->data !== [] && $pending === [] ? $d : null;
            });
            $delivery = $deliveries->data[0];
            $before = count($receiver->requests());
            $replay = $mb->webhooks->replay($hook->id, $delivery->id);
            self::assertSame($delivery->id, $replay->replay_of);
            $again = self::eventually(static function () use ($receiver, $before): ?array {
                $all = $receiver->requests();

                return count($all) > $before ? $all[count($all) - 1] : null;
            }, 30_000);
            self::assertSame('message.delivered', WebhookSignature::verify($again['body'], $again['headers'], $hook->secret)['type']);

            $mb->webhooks->delete($hook->id);
            self::assertNotContains($hook->id, array_map(static fn (Webhook $w): string => $w->id, $mb->webhooks->list()->data));
        } finally {
            $receiver->stop();
        }
    }
}
