<?php

declare(strict_types=1);

namespace Runearc\Http;

use const CURLE_OPERATION_TIMEDOUT;

/** @internal One HTTP request over ext-curl. */
final class CurlTransport
{
    /**
     * @param array<string, string> $headers
     * @throws TransportException
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutMs): HttpResponse
    {
        $handle = curl_init();
        $lines = ['Expect:'];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $received = [];
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$received): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $received[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        foreach ($options as $option => $value) {
            curl_setopt($handle, $option, $value);
        }

        $raw = curl_exec($handle);
        if (!is_string($raw)) {
            throw new TransportException(curl_error($handle), curl_errno($handle) === CURLE_OPERATION_TIMEDOUT);
        }

        return new HttpResponse((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $received, $raw);
    }
}
