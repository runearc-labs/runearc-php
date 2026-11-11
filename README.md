# runearc/runearc

Send email and give your agents an inbox, from PHP.

```sh
composer require runearc/runearc
```

```php
use Runearc\Runearc;

$runearc = new Runearc(getenv('RUNEARC_API_KEY'));
$sent = $runearc->emails->send(['from' => 'Acme <noreply@acme.com>', 'to' => 'bob@example.com', 'subject' => 'Welcome to Acme', 'html' => '<p>Thanks for signing up.</p>']);
```

See [runearc.com/integrations/php](https://runearc.com/integrations/php) for the guide and more examples.

The official PHP SDK for [Runearc](https://runearc.com): send, batch and schedule email, create inboxes your agents can read, manage domains and verify webhooks.

PHP 8.2 or later. The only requirements are `ext-curl` and `ext-json`: no HTTP client or other package is installed with it.

## Install

```sh
composer require runearc/runearc
```

## Quick start

```php
<?php

use Runearc\Runearc;

$runearc = new Runearc(getenv('RUNEARC_API_KEY'));

$sent = $runearc->emails->send([
    'from' => 'Acme <noreply@acme.com>',
    'to' => 'bob@example.com',
    'subject' => 'Welcome to Acme',
    'html' => '<p>Thanks for signing up.</p>',
]);
echo $sent->id; // eml_...
```

`new Runearc()` with no key reads `RUNEARC_API_KEY`. Constructor arguments (use named arguments to skip some):

| Argument | Default | |
|---|---|---|
| `apiKey` | `RUNEARC_API_KEY` | Throws `ConfigurationException` (`missing_api_key`) when neither is set. |
| `baseUrl` | `RUNEARC_BASE_URL`, else `https://api.runearc.com` | A trailing `/v1` is fine. |
| `maxRetries` | `2` | Retries after the first attempt (see [Retries](#retries-and-idempotency)). |
| `timeoutMs` | `60000` | Per attempt. |
| `headers` | none | Sent with every request, such as `['Runearc-Project' => 'prj_...']`. |

Requests are plain PHP arrays. Responses are typed, read-only objects (`final readonly class`, in `Runearc\Model`) whose properties carry the API's JSON field names in snake case (`$email->reply_to`, `$email->scheduled_at`, `$page->next_cursor`), so the [API reference](https://runearc.com/docs) and this SDK read the same. Nullable fields are `?type`, lists are `list<Model>`, and keys the API adds later are ignored. Times in requests can be strings or any `DateTimeInterface`; times in responses are RFC 3339 strings. Every resource method takes a last optional `RequestOptions` argument: `new RequestOptions(idempotencyKey: '...', timeoutMs: 5000)`.

To try things without sending real mail, send to `delivered@test.runearc.com`. `bounced@` and `complained@` at the same domain simulate those outcomes.

## Errors

Methods return the decoded response and throw a `Runearc\Exception\RunearcException` when something fails.

```php
use Runearc\Exception\RunearcException;

try {
    $runearc->emails->send(['from' => 'noreply@acme.com', 'to' => 'bob@example.com', 'subject' => 'Hi', 'text' => 'Hi']);
} catch (RunearcException $e) {
    echo $e->status;     // 403
    echo $e->errorCode;  // "sender_not_verified", a stable string to branch on
    echo $e->kind;       // "forbidden"
    echo $e->getMessage();
    echo $e->fix;        // what to do about it, when there is something to do
}
```

| Property | |
|---|---|
| `status` | The HTTP status, or `0` when no response came back. |
| `errorCode` | The API's `code`. Failures before a response use `network_error`, `timeout` and `missing_api_key`. |
| `kind` | The API's error kind: `invalid`, `unauthorized`, `forbidden`, `not_found`, `conflict`, `limit_reached`, `rate_limited`, `unavailable` or `internal`. `network`, `config` and `webhook` for failures without a response. |
| `fix` | What to do about it, or `null`. |
| `retryAfter` | Seconds from the `Retry-After` header, or `null`. |
| `errors` | For a refused batch (`invalid_batch`): every bad item, or `null`. |

Catch the subclass you care about:

| Class | When |
|---|---|
| `AuthenticationException` | 401 |
| `PermissionException` | 403 |
| `NotFoundException` | 404 |
| `ConflictException` | 409 |
| `RateLimitException` | 429 |
| `InvalidRequestException` | any other 4xx (400, 402, 408, ...) |
| `ServerException` | 5xx |
| `ConnectionException` | no response: `network_error` or `timeout` |
| `ConfigurationException` | no API key |
| `InvalidResponseException` | a success answer that is not JSON |
| `WebhookVerificationException` | a webhook that is not genuine or fresh |

`ApiException` is the parent of every class that has an HTTP status; all of them extend `RunearcException`.

## Emails

```php
// Send
$sent = $runearc->emails->send([
    'from' => 'Acme <noreply@acme.com>',
    'to' => ['bob@example.com'],
    'cc' => 'carol@example.com',
    'reply_to' => 'support@acme.com',
    'subject' => 'Your receipt',
    'html' => '<p>Thanks for your order.</p>',
    'text' => 'Thanks for your order.',
    'attachments' => [['filename' => 'receipt.pdf', 'content' => base64_encode($pdf)]],
    'tags' => [['name' => 'kind', 'value' => 'receipt']],
]);

// Read
$email = $runearc->emails->get($sent->id); // status, last_event, recipients, html, text
$page = $runearc->emails->list(['limit' => 50]);
$next = $runearc->emails->list(['cursor' => $page->next_cursor]);
```

### Scheduled send

Pass `scheduled_at` (or `scheduledAt`) as a `DateTimeInterface`, an ISO 8601 time with an offset, or the relative form `in <n> <unit>`. Up to 30 days ahead.

```php
$sent = $runearc->emails->send([
    'from' => 'Acme <noreply@acme.com>',
    'to' => 'bob@example.com',
    'subject' => 'Your trial ends tomorrow',
    'text' => 'Here is what happens next.',
    'scheduled_at' => new DateTimeImmutable('+1 day'), // or '2026-10-20T09:00:00Z', or 'in 1 day'
]);

$runearc->emails->get($sent->id);                  // status "scheduled", scheduled_at
$runearc->emails->reschedule($sent->id, 'in 2 days'); // same as update($id, ['scheduled_at' => ...])
$runearc->emails->cancel($sent->id);               // status "canceled"
```

Rescheduling or canceling an email that is no longer scheduled throws `email_not_scheduled` (409).

### Batch send

Up to 100 emails in one request. Each item has the same shape as `emails->send()`.

```php
$res = $runearc->emails->batch([
    ['from' => 'Acme <noreply@acme.com>', 'to' => 'bob@example.com', 'subject' => 'Hi Bob', 'text' => '...'],
    ['from' => 'Acme <noreply@acme.com>', 'to' => 'carol@example.com', 'subject' => 'Hi Carol', 'text' => '...'],
]);
// $res->data: a SendEmailResponse per email, in request order ($res->data[0]->id)
```

Validation is strict by default: if any item is invalid nothing is sent, and the exception lists every bad item.

```php
try {
    $runearc->emails->batch($emails);
} catch (RunearcException $e) {
    foreach ($e->errors ?? [] as $item) {
        echo $item['index'], ' ', $item['code'], ' ', $item['message'], "\n";
    }
}
```

With `batchValidation: 'permissive'` the valid items are sent and the invalid ones are reported in `errors`:

```php
use Runearc\BatchOptions;

$res = $runearc->emails->batch($emails, new BatchOptions(batchValidation: 'permissive'));
// $res->data and $res->errors (a BatchItemError each: index, code, message, fix)
```

### Senders

```php
$runearc->senders->list()->data; // addresses you can use as `from`
```

## Inboxes and messages

Inboxes receive mail. Give one to an agent, wait for a message, read the code in it, reply.

```php
$inbox = $runearc->inboxes->create(['local_part' => 'signup-bot', 'display_name' => 'Signup bot']);
$runearc->inboxes->list();
$runearc->inboxes->get($inbox->id);

// Wait up to 120 seconds for a matching message. Returns null when none arrived.
$got = $runearc->messages->wait($inbox->id, ['from' => 'github.com', 'subject' => 'verify', 'timeout' => 60]);
if ($got !== null) {
    print_r($got->codes);   // one time codes
    print_r($got->links);
    echo $got->message->subject;
}

$page = $runearc->messages->list($inbox->id, ['limit' => 20]);
$message = $runearc->messages->get($page->data[0]->id);
$rfc822 = $runearc->messages->raw($message->id);

// Send from the inbox, and reply in a thread
$runearc->messages->send($inbox->id, ['to' => 'bob@example.com', 'subject' => 'Hello', 'text' => 'Hi Bob']);
$runearc->messages->reply($message->id, ['text' => 'Thanks, on it', 'reply_all' => false]);

// Threads
$runearc->threads->list($inbox->id);
$thread = $runearc->threads->get($message->thread_id); // with $thread->messages

$runearc->inboxes->delete($inbox->id);
```

## Conversations

The shared support inbox view across every inbox you can read.

```php
$page = $runearc->conversations->list(['status' => 'open', 'assignee' => 'none', 'q' => 'refund']);
$id = $page->data[0]->id;
$runearc->conversations->counts();
$runearc->conversations->update($id, ['status' => 'closed']);
$runearc->conversations->update($id, ['snoozed_until' => new DateTimeImmutable('+1 hour')]);
$runearc->conversations->update($id, ['snoozed_until' => null]); // wake it
$runearc->conversations->read($id);
$runearc->conversations->unread($id);
```

## Domains

```php
$domain = $runearc->domains->create(['name' => 'acme.com']);
print_r($domain->records);   // the DNS records to publish
echo $domain->next_step;

$runearc->domains->verify($domain->id); // checks DNS now
$runearc->domains->list();
$runearc->domains->get('acme.com');       // id or name
$runearc->domains->delete($domain->id);
```

## Webhooks

```php
$hook = $runearc->webhooks->create([
    'url' => 'https://acme.com/runearc/webhook',
    'event_types' => ['message.received', 'message.delivered', 'message.bounced'],
]);
// $hook->secret ("whsec_...") is shown once: store it, you need it to verify deliveries.

$runearc->webhooks->list();
$deliveries = $runearc->webhooks->deliveries($hook->id, ['limit' => 20]);
$runearc->webhooks->replay($hook->id, $deliveries->data[0]->id);
$runearc->webhooks->delete($hook->id);
```

### Verifying webhooks

Runearc signs every delivery with [Standard Webhooks](https://www.standardwebhooks.com): headers `webhook-id`, `webhook-timestamp` and `webhook-signature` (`v1,<base64 HMAC SHA256>`). `WebhookSignature::verify()` checks the signature with `hash_hmac` and `hash_equals`, refuses timestamps more than 5 minutes (300 seconds) from now, and returns the decoded event. It throws `WebhookVerificationException` otherwise, with one of the codes `missing_headers`, `invalid_timestamp`, `timestamp_out_of_range`, `invalid_secret`, `invalid_signature` or `invalid_payload`. Always pass the raw body, not JSON you decoded and encoded again.

Headers can be a plain array with names in any case, or `$_SERVER` itself.

```php
use Runearc\Exception\WebhookVerificationException;
use Runearc\WebhookSignature;

$payload = file_get_contents('php://input');

try {
    $event = WebhookSignature::verify($payload, $_SERVER, getenv('RUNEARC_WEBHOOK_SECRET'));
} catch (WebhookVerificationException $e) {
    http_response_code(400);
    exit($e->getMessage());
}

if ($event['type'] === 'message.received') {
    // $event['data']['message_id'], $event['data']['inbox_id'], ...
}
// $event['id'] is the webhook-id: use it to drop duplicates.
http_response_code(204);
```

The tolerance and the clock are arguments: `WebhookSignature::verify($payload, $headers, $secret, toleranceSeconds: 600)`. `$runearc->webhooks->verify(...)` is the same function.

## API keys, suppressions and events

```php
$created = $runearc->keys->create(['name' => 'app', 'permissions' => 'sending', 'domain_id' => 'acme.com']);
$created->secret; // shown once
$runearc->keys->list();
$runearc->keys->delete($created->key->id); // revokes at once

$runearc->suppressions->list();
$runearc->suppressions->delete('bob@example.com'); // lets mail go to bob again

$page = $runearc->events->list(['limit' => 100]); // oldest first
$page = $runearc->events->list(['after' => $page->next_after]);
```

## Retries and idempotency

The SDK retries a request up to `maxRetries` times (default 2) with exponential backoff and jitter, and waits as long as the API's `Retry-After` header asks (seconds or an HTTP date, at most 60 seconds). It retries:

* 429 (rate limited), always;
* 5xx, network errors and timeouts, when the request is safe to repeat: GET, PATCH and DELETE, and any POST that carries an `Idempotency-Key`.

It never retries other 4xx errors. A POST that could create something twice (such as creating an inbox) is not repeated after a 5xx or a network error; the exception is thrown instead.

Every call that sends mail (`emails->send`, `emails->batch`, `messages->send`, `messages->reply`) gets an `Idempotency-Key` automatically when you do not give one, and every retry reuses it, so a retry never sends twice. Runearc remembers keys for 24 hours. To make your own retries safe too, for example from a job queue, pass a key that is stable for that piece of work:

```php
use Runearc\RequestOptions;

$runearc->emails->send($email, new RequestOptions(idempotencyKey: "welcome/{$user->id}"));
```

Repeating a key with the same body returns the first result. The same key with a different body throws `ConflictException` with code `idempotency_conflict` (409).

## Tests

The suite runs against a real Runearc API, worker and Postgres, started by `tests/bootstrap.php` against a throwaway database (needs `createdb`, `dropdb` and `psql` on the path, a Postgres on localhost, and the `runearc-api` and `runearc-worker` binaries; set `RUNEARC_BIN_DIR` to use a build other than `target/agent-sdk/debug`).

```sh
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
```

## License

MIT. Copyright Runearc. See `LICENSE`.
