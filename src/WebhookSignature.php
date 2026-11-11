<?php

declare(strict_types=1);

namespace Runearc;

use JsonException;
use Runearc\Exception\WebhookVerificationException;

/**
 * Standard Webhooks verification (https://www.standardwebhooks.com). Mirrors
 * `runearc_core::webhooks::sign`:
 * signature = "v1," + base64(HMAC-SHA256(base64decode(secret without "whsec_"),
 * "{webhook-id}.{webhook-timestamp}.{body}")).
 */
final class WebhookSignature
{
    /** Signatures older or newer than this are refused (replay protection). */
    public const TOLERANCE_SECONDS = 300;

    /**
     * Checks that a webhook request came from Runearc and is fresh, then returns the
     * decoded event (`id`, `type`, `timestamp`, `data`).
     *
     * @param string $payload The raw request body, exactly as received. Do not decode and encode it again.
     * @param array<string, mixed> $headers The request headers (any case), or `$_SERVER`.
     * @param string $secret The endpoint's signing secret, `whsec_...`, from `webhooks->create()`.
     * @param int $toleranceSeconds Seconds of clock difference allowed.
     * @param int|null $now For tests: the current time in seconds since the epoch.
     * @return array<string, mixed>
     * @throws WebhookVerificationException
     */
    public static function verify(
        string $payload,
        array $headers,
        #[\SensitiveParameter]
        string $secret,
        int $toleranceSeconds = self::TOLERANCE_SECONDS,
        ?int $now = null,
    ): array {
        $id = self::header($headers, 'webhook-id');
        $ts = self::header($headers, 'webhook-timestamp');
        $sigs = self::header($headers, 'webhook-signature');
        if ($id === null || $ts === null || $sigs === null || $id === '' || $ts === '' || $sigs === '') {
            throw new WebhookVerificationException(
                'missing_headers',
                'the webhook-id, webhook-timestamp and webhook-signature headers are required',
                'Pass the request headers unchanged; check that a proxy does not strip them',
            );
        }
        $ts = trim($ts);
        if (preg_match('/^\d{1,15}$/', $ts) !== 1) {
            throw new WebhookVerificationException('invalid_timestamp', 'webhook-timestamp is not a number of seconds');
        }
        $now ??= time();
        if (abs($now - (int) $ts) > $toleranceSeconds) {
            throw new WebhookVerificationException(
                'timestamp_out_of_range',
                sprintf('the webhook timestamp is more than %d seconds from now', $toleranceSeconds),
                'Check your server clock; old deliveries are refused to stop replays',
            );
        }

        $secret = trim($secret);
        $key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);
        if ($key === false) {
            throw new WebhookVerificationException(
                'invalid_secret',
                'the webhook secret is not valid base64',
                'Use the secret returned by webhooks->create, which starts with whsec_',
            );
        }
        $expected = hash_hmac('sha256', $id . '.' . $ts . '.' . $payload, $key, true);

        foreach (explode(' ', $sigs) as $part) {
            $pair = explode(',', $part, 2);
            if (count($pair) !== 2 || $pair[0] !== 'v1' || $pair[1] === '') {
                continue;
            }
            $given = base64_decode($pair[1], true);
            if ($given === false) {
                continue;
            }
            if (hash_equals($expected, $given)) {
                return self::decode($payload);
            }
        }
        throw new WebhookVerificationException(
            'invalid_signature',
            'no webhook-signature matches the payload',
            "Verify the raw body (not JSON decoded and encoded again) with this endpoint's whsec_ secret",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $payload): array
    {
        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $event = null;
        }
        if (!is_array($event)) {
            throw new WebhookVerificationException('invalid_payload', 'the webhook body is not JSON');
        }
        /** @var array<string, mixed> $event */
        return $event;
    }

    /**
     * A header by lower case name from a plain header array or `$_SERVER` style keys.
     *
     * @param array<string, mixed> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            $normal = str_replace('_', '-', strtolower($key));
            if (str_starts_with($normal, 'http-')) {
                $normal = substr($normal, 5);
            }
            if ($normal !== $name) {
                continue;
            }
            if (is_array($value)) {
                return implode(' ', array_filter($value, is_string(...)));
            }

            return is_string($value) ? $value : null;
        }

        return null;
    }
}
