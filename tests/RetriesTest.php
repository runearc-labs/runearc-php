<?php

declare(strict_types=1);

namespace Runearc\Tests;

use Runearc\Exception\ConfigurationException;
use Runearc\Exception\ConnectionException;
use Runearc\Exception\InvalidRequestException;
use Runearc\Exception\RateLimitException;
use Runearc\Exception\ServerException;
use Runearc\Model\Email;
use Runearc\Runearc;
use Runearc\Tests\Support\Environment;
use Runearc\Tests\Support\StubServer;

/**
 * Retry behaviour. A tiny local HTTP server stands in for failures the real API
 * cannot be made to produce on demand. The idempotency test puts that server in
 * front of the REAL API as a proxy: the first send reaches the API and sends,
 * then the proxy answers 503 as if the response was lost; the SDK's retry must
 * reuse the same Idempotency-Key so the API returns the first email instead of
 * sending a second one.
 */
final class RetriesTest extends TestCase
{
    private ?StubServer $stub = null;

    protected function tearDown(): void
    {
        $this->stub?->stop();
        $this->stub = null;
    }

    /**
     * @param list<array<string, mixed>> $steps
     */
    private function stub(array $steps): StubServer
    {
        return $this->stub = new StubServer($steps);
    }

    private const UNAVAILABLE = '{"error":{"code":"unavailable","message":"try again","fix":null}}';

    public function testRetriesAGetOn503AndHonoursRetryAfter(): void
    {
        $stub = $this->stub([
            ['action' => 'respond', 'status' => 503, 'headers' => ['retry-after' => '1'], 'body' => self::UNAVAILABLE],
            ['action' => 'respond', 'status' => 200, 'body' => '{"data":[]}'],
        ]);
        $mb = new Runearc('mb_test', baseUrl: $stub->url);
        self::assertSame([], $mb->inboxes->list()->data);
        $requests = $stub->requests();
        self::assertCount(2, $requests);
        self::assertGreaterThanOrEqual(0.95, $requests[1]['at'] - $requests[0]['at']);
    }

    public function testRetries429EvenOnAPostWithoutAKeyAndGivesUpAfterMaxRetries(): void
    {
        $stub = $this->stub([[
            'action' => 'respond',
            'status' => 429,
            'headers' => ['retry-after' => '0'],
            'body' => '{"error":{"code":"rate_limited","message":"slow down","fix":"wait"}}',
        ]]);
        $mb = new Runearc('mb_test', baseUrl: $stub->url, maxRetries: 3);
        $err = $this->failure(fn () => $mb->inboxes->create());
        self::assertCount(4, $stub->requests());
        self::assertInstanceOf(RateLimitException::class, $err);
        self::assertSame(429, $err->status);
        self::assertSame('rate_limited', $err->kind);
        self::assertSame('rate_limited', $err->errorCode);
        self::assertSame(0, $err->retryAfter);
    }

    public function testNeverRetriesOther4xx(): void
    {
        $stub = $this->stub([[
            'action' => 'respond',
            'status' => 400,
            'body' => '{"error":{"code":"invalid_body","message":"bad","fix":"fix it"}}',
        ]]);
        $mb = new Runearc('mb_test', baseUrl: $stub->url);
        $err = $this->failure(fn () => $mb->emails->send(['from' => 'a@b.c', 'to' => 'd@e.f', 'subject' => 's']));
        self::assertCount(1, $stub->requests());
        self::assertInstanceOf(InvalidRequestException::class, $err);
        self::assertSame('invalid_body', $err->errorCode);
        self::assertSame('fix it', $err->fix);
    }

    public function testDoesNotRepeatAPostThatCouldCreateTwiceOn5xx(): void
    {
        $stub = $this->stub([['action' => 'respond', 'status' => 503, 'body' => self::UNAVAILABLE]]);
        $mb = new Runearc('mb_test', baseUrl: $stub->url);
        $err = $this->failure(fn () => $mb->inboxes->create(['local_part' => 'x']));
        self::assertCount(1, $stub->requests());
        self::assertInstanceOf(ServerException::class, $err);
        self::assertSame(503, $err->status);
        self::assertSame('unavailable', $err->kind);
    }

    public function testRetriesNetworkErrorsAndReportsThemTypedWhenTheyPersist(): void
    {
        $stub = $this->stub([['action' => 'drop']]);
        $mb = new Runearc('mb_test', baseUrl: $stub->url, maxRetries: 1);
        $err = $this->failure(fn () => $mb->domains->list());
        self::assertCount(2, $stub->requests());
        self::assertInstanceOf(ConnectionException::class, $err);
        self::assertSame(0, $err->status);
        self::assertSame('network_error', $err->errorCode);
    }

    public function testTimesOutPerAttempt(): void
    {
        $stub = $this->stub([['action' => 'hang']]);
        $mb = new Runearc('mb_test', baseUrl: $stub->url, maxRetries: 0, timeoutMs: 200);
        $err = $this->failure(fn () => $mb->events->list());
        self::assertInstanceOf(ConnectionException::class, $err);
        self::assertSame('timeout', $err->errorCode);
    }

    public function testARetriedSendReusesItsIdempotencyKeySoTheRealApiSendsOnce(): void
    {
        $stub = $this->stub([
            ['action' => 'proxy', 'upstream' => Environment::$apiUrl, 'lose' => true],
            ['action' => 'proxy', 'upstream' => Environment::$apiUrl],
        ]);
        $proxied = new Runearc(Environment::$apiKey, baseUrl: $stub->url);
        $subject = self::unique('retry once');
        $sent = $proxied->emails->send(['from' => self::from(), 'to' => self::testAddress(), 'subject' => $subject, 'text' => 'once']);
        $requests = $stub->requests();
        self::assertCount(2, $requests);
        $first = $requests[0]['headers']['idempotency-key'] ?? '';
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first);
        self::assertSame($first, $requests[1]['headers']['idempotency-key'] ?? null);
        $stored = array_filter(self::client()->emails->list(['limit' => 100])->data, static fn (Email $e): bool => $e->subject === $subject);
        self::assertSame([$sent->id], array_values(array_map(static fn (Email $e): string => $e->id, $stored)));
    }

    public function testSendsTheHeadersTheApiExpects(): void
    {
        $stub = $this->stub([['action' => 'respond', 'status' => 200, 'body' => '{"data":[]}']]);
        $mb = new Runearc('mb_secret', baseUrl: $stub->url . '/v1/', headers: ['Runearc-Project' => 'prj_1']);
        $mb->senders->list();
        $request = $stub->requests()[0];
        self::assertSame('/v1/senders', $request['target']);
        self::assertSame('Bearer mb_secret', $request['headers']['authorization']);
        self::assertSame('prj_1', $request['headers']['runearc-project']);
        self::assertSame('runearc-php/' . Runearc::VERSION, $request['headers']['user-agent']);
        self::assertSame('application/json', $request['headers']['accept']);
    }

    public function testReadsRunearcApiKeyAndRunearcBaseUrlFromTheEnvironment(): void
    {
        $saved = [getenv('RUNEARC_API_KEY'), getenv('RUNEARC_BASE_URL')];
        putenv('RUNEARC_API_KEY');
        putenv('RUNEARC_BASE_URL');
        try {
            try {
                new Runearc();
                self::fail('a missing key must throw');
            } catch (ConfigurationException $e) {
                self::assertSame('missing_api_key', $e->errorCode);
                self::assertNotEmpty($e->fix);
            }
            putenv('RUNEARC_API_KEY=mb_from_env');
            self::assertSame('https://api.runearc.com', (new Runearc())->baseUrl);
            self::assertSame('https://api.runearc.com', (new Runearc(baseUrl: 'https://api.runearc.com/v1'))->baseUrl);
            putenv('RUNEARC_BASE_URL=http://127.0.0.1:1/v1/');
            self::assertSame('http://127.0.0.1:1', (new Runearc())->baseUrl);
        } finally {
            putenv($saved[0] === false ? 'RUNEARC_API_KEY' : 'RUNEARC_API_KEY=' . $saved[0]);
            putenv($saved[1] === false ? 'RUNEARC_BASE_URL' : 'RUNEARC_BASE_URL=' . $saved[1]);
        }
    }
}
