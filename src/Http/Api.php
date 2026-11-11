<?php

declare(strict_types=1);

namespace Runearc\Http;

use JsonException;
use Runearc\Exception\ApiException;
use Runearc\Exception\ConnectionException;
use Runearc\Exception\InvalidResponseException;
use Runearc\RequestOptions;
use Runearc\Support;
use stdClass;

/** @internal Every API call goes through here: headers, retries, backoff and errors. */
final class Api
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $userAgent,
        private readonly int $maxRetries,
        private readonly int $timeoutMs,
        private readonly array $headers,
        private readonly CurlTransport $transport = new CurlTransport(),
    ) {
    }

    /**
     * A call whose answer is JSON. An empty answer (204) is `null`.
     *
     * @param array<string, mixed> $query
     * @param array<mixed>|stdClass|null $body
     * @param array<string, string> $headers
     */
    public function json(
        string $method,
        string $path,
        array $query = [],
        array|stdClass|null $body = null,
        array $headers = [],
        bool $sendsMail = false,
        bool $idempotent = false,
        ?RequestOptions $options = null,
    ): mixed {
        $res = $this->execute($method, $path, $query, $body, $headers, $sendsMail, $idempotent, $options);
        if ($res->body === '') {
            return null;
        }
        try {
            return json_decode($res->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidResponseException(
                'the API sent a response that is not JSON',
                'invalid_response',
                'invalid',
                $res->status,
                'Check that baseUrl points at the Runearc API',
                null,
                null,
                $e,
            );
        }
    }

    /**
     * A GET whose answer is plain text.
     *
     * @param array<string, mixed> $query
     */
    public function text(string $path, array $query = [], ?RequestOptions $options = null): string
    {
        return $this->execute('GET', $path, $query, null, [], false, false, $options)->body;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<mixed>|stdClass|null $body
     * @param array<string, string> $headers
     */
    private function execute(
        string $method,
        string $path,
        array $query,
        array|stdClass|null $body,
        array $headers,
        bool $sendsMail,
        bool $idempotent,
        ?RequestOptions $options,
    ): HttpResponse {
        $url = $this->baseUrl . $path . $this->queryString($query);
        $all = array_merge(
            ['Accept' => 'application/json', 'User-Agent' => $this->userAgent],
            $this->headers,
            $headers,
            ['Authorization' => 'Bearer ' . $this->apiKey],
        );
        // One key for every attempt, so a retry returns the first result instead of sending again.
        $idemKey = $options->idempotencyKey ?? ($sendsMail ? Support::uuid() : null);
        if ($idemKey !== null && $idemKey !== '') {
            $all['Idempotency-Key'] = $idemKey;
        }
        $payload = null;
        if ($body !== null) {
            $all['Content-Type'] = 'application/json';
            $payload = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        // Repeating must not create a second resource: GET, DELETE and PATCH are
        // idempotent, and a POST is when it carries an Idempotency-Key.
        $repeatable = $method !== 'POST' || ($idemKey !== null && $idemKey !== '') || $idempotent;
        $timeoutMs = $options->timeoutMs ?? $this->timeoutMs;

        for ($attempt = 0;; $attempt++) {
            $last = $attempt >= $this->maxRetries;
            try {
                $res = $this->transport->send($method, $url, $all, $payload, $timeoutMs);
            } catch (TransportException $e) {
                if ($repeatable && !$last) {
                    $this->sleepMs($this->backoffMs($attempt));
                    continue;
                }
                throw new ConnectionException(
                    $e->timedOut
                        ? sprintf('no response from %s within %d ms', $this->baseUrl, $timeoutMs)
                        : sprintf('could not reach %s: %s', $this->baseUrl, $e->getMessage()),
                    $e->timedOut ? 'timeout' : 'network_error',
                    'network',
                    0,
                    $idemKey !== null && $idemKey !== ''
                        ? sprintf('Retry with the same idempotencyKey (%s) so the email is not sent twice', $idemKey)
                        : 'Check your network and baseUrl, then retry',
                    null,
                    null,
                    $e,
                );
            }
            if ($res->ok()) {
                return $res;
            }
            $wait = self::retryAfterMs($res->headers['retry-after'] ?? null);
            $retryable = $res->status === 429 || ($res->status >= 500 && $repeatable);
            if ($retryable && !$last) {
                $this->sleepMs(min($wait ?? $this->backoffMs($attempt), 60_000));
                continue;
            }
            throw $this->error($res, $wait);
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function queryString(array $query): string
    {
        $pairs = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            $value = Support::time($value);
            $pairs[$key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_scalar($value) => (string) $value,
                default => throw new \InvalidArgumentException(sprintf('query parameter "%s" must be a scalar or a DateTimeInterface', $key)),
            };
        }

        return $pairs === [] ? '' : '?' . http_build_query($pairs, '', '&', PHP_QUERY_RFC3986);
    }

    /** Milliseconds to wait from a Retry-After header (seconds or an HTTP date). */
    private static function retryAfterMs(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) max(0, (float) $value * 1000);
        }
        $at = strtotime($value);

        return $at === false ? null : max(0, ($at - time()) * 1000);
    }

    private function backoffMs(int $attempt): int
    {
        $cap = min(8_000, 500 * (2 ** $attempt));

        return (int) ($cap / 2 + mt_rand() / mt_getrandmax() * ($cap / 2));
    }

    private function sleepMs(int $ms): void
    {
        usleep($ms * 1000);
    }

    private function error(HttpResponse $res, ?int $waitMs): ApiException
    {
        $error = null;
        $decoded = json_decode($res->body, true);
        if (is_array($decoded) && is_array($decoded['error'] ?? null)) {
            $error = $decoded['error'];
        }
        $code = is_string($error['code'] ?? null) ? $error['code'] : 'http_' . $res->status;
        $message = is_string($error['message'] ?? null)
            ? $error['message']
            : (substr($res->body, 0, 500) ?: 'HTTP ' . $res->status);
        $fix = is_string($error['fix'] ?? null) ? $error['fix'] : null;
        $errors = null;
        if (is_array($error['errors'] ?? null) && array_is_list($error['errors'])) {
            /** @var list<array<string, mixed>> $items */
            $items = $error['errors'];
            $errors = $items;
        }

        return ApiException::fromResponse(
            $res->status,
            $code,
            $message,
            $fix,
            $errors,
            $waitMs === null ? null : (int) ceil($waitMs / 1000),
        );
    }
}
