<?php

declare(strict_types=1);

namespace Runearc;

use Runearc\Exception\ConfigurationException;
use Runearc\Http\Api;
use Runearc\Resources\Conversations;
use Runearc\Resources\Domains;
use Runearc\Resources\Emails;
use Runearc\Resources\Events;
use Runearc\Resources\Inboxes;
use Runearc\Resources\Keys;
use Runearc\Resources\Messages;
use Runearc\Resources\Senders;
use Runearc\Resources\Suppressions;
use Runearc\Resources\Threads;
use Runearc\Resources\Webhooks;

/** The Runearc API client. */
final class Runearc
{
    public const VERSION = '0.1.0';
    public const DEFAULT_BASE_URL = 'https://api.runearc.com';

    public readonly string $baseUrl;
    public readonly int $maxRetries;
    public readonly Emails $emails;
    public readonly Senders $senders;
    public readonly Inboxes $inboxes;
    public readonly Messages $messages;
    public readonly Threads $threads;
    public readonly Conversations $conversations;
    public readonly Domains $domains;
    public readonly Webhooks $webhooks;
    public readonly Keys $keys;
    public readonly Suppressions $suppressions;
    public readonly Events $events;

    /**
     * @param string|null $apiKey Default: the RUNEARC_API_KEY environment variable.
     * @param string|null $baseUrl API origin. Default: RUNEARC_BASE_URL, else https://api.runearc.com. A trailing `/v1` is fine.
     * @param int $maxRetries Retries after the first attempt, on 429, 5xx and network errors. Default 2.
     * @param int $timeoutMs Per attempt timeout in milliseconds. Default 60000.
     * @param array<string, string> $headers Extra headers sent with every request, such as `Runearc-Project`.
     * @throws ConfigurationException When there is no API key.
     */
    public function __construct(
        #[\SensitiveParameter]
        ?string $apiKey = null,
        ?string $baseUrl = null,
        int $maxRetries = 2,
        public readonly int $timeoutMs = 60_000,
        array $headers = [],
    ) {
        $key = $apiKey ?? self::env('RUNEARC_API_KEY');
        if ($key === null || $key === '') {
            throw new ConfigurationException(
                'no Runearc API key was given',
                'missing_api_key',
                'config',
                0,
                "Pass it as new Runearc('mb_...') or set RUNEARC_API_KEY",
            );
        }
        $base = $baseUrl ?? self::env('RUNEARC_BASE_URL') ?? self::DEFAULT_BASE_URL;
        $this->baseUrl = (string) preg_replace('#/v1$#', '', rtrim($base, '/'));
        $this->maxRetries = max(0, $maxRetries);

        $api = new Api(
            $this->baseUrl,
            $key,
            'runearc-php/' . self::VERSION,
            $this->maxRetries,
            $this->timeoutMs,
            $headers,
        );
        $this->emails = new Emails($api);
        $this->senders = new Senders($api);
        $this->inboxes = new Inboxes($api);
        $this->messages = new Messages($api);
        $this->threads = new Threads($api);
        $this->conversations = new Conversations($api);
        $this->domains = new Domains($api);
        $this->webhooks = new Webhooks($api);
        $this->keys = new Keys($api);
        $this->suppressions = new Suppressions($api);
        $this->events = new Events($api);
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return $value === false ? null : $value;
    }
}
