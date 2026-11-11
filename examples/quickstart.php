<?php

declare(strict_types=1);

use Runearc\Runearc;
use Runearc\WebhookSignature;

require __DIR__ . '/vendor/autoload.php';

$runearc = new Runearc(); // reads RUNEARC_API_KEY and RUNEARC_BASE_URL

$inbox = $runearc->inboxes->create(['display_name' => 'quickstart']);
echo "inbox {$inbox->address}\n";

$subject = 'Hello from the PHP SDK ' . bin2hex(random_bytes(3));
$sent = $runearc->emails->send([
    'from' => getenv('RUNEARC_FROM'),
    'to' => $inbox->address,
    'subject' => $subject,
    'text' => 'It works.',
]);
echo "sent {$sent->id}\n";

$waited = $runearc->messages->wait($inbox->id, ['subject' => $subject, 'after' => $inbox->created_at, 'timeout' => 30]);
if ($waited === null) {
    fwrite(STDERR, "no message arrived within 30 seconds\n");
    exit(1);
}
echo "received {$waited->message->subject}\n";

// Sign a sample event the way Runearc does (Standard Webhooks), then verify it.
$key = random_bytes(32);
$secret = 'whsec_' . base64_encode($key);
$payload = json_encode(['id' => 'evt_1', 'type' => 'message.delivered', 'data' => ['email_id' => $sent->id]]);
$timestamp = time();
$signature = base64_encode(hash_hmac('sha256', "evt_1.$timestamp.$payload", $key, true));
WebhookSignature::verify($payload, [
    'webhook-id' => 'evt_1',
    'webhook-timestamp' => (string) $timestamp,
    'webhook-signature' => "v1,$signature",
], $secret);
echo "webhook verified\n";

$runearc->inboxes->delete($inbox->id);
