<?php

declare(strict_types=1);

namespace Runearc\Tests;

use DateTimeImmutable;
use Runearc\Exception\NotFoundException;
use Runearc\Exception\PermissionException;
use Runearc\Exception\AuthenticationException;
use Runearc\Model\ApiKey;
use Runearc\Model\Domain;
use Runearc\Model\Event;
use Runearc\Model\Inbox;
use Runearc\Model\Message;
use Runearc\Model\Suppression;
use Runearc\Model\Thread;
use Runearc\RequestOptions;
use Runearc\Runearc;
use Runearc\Tests\Support\Environment;

final class ResourcesTest extends TestCase
{
    public function testInboxesMessagesThreadsAndConversations(): void
    {
        $mb = self::client();
        $local = 'sdk-' . bin2hex(random_bytes(4));
        $inbox = $mb->inboxes->create(['local_part' => $local, 'display_name' => 'SDK']);
        self::assertStringStartsWith('ibx_', $inbox->id);
        self::assertSame($local, $inbox->local_part);
        self::assertSame('SDK', $inbox->display_name);
        self::assertFalse($inbox->catch_all);
        self::assertTrue(str_ends_with($inbox->domain, Environment::$sharedDomain));
        self::assertSame($inbox->address, $mb->inboxes->get($inbox->id)->address);
        self::assertContains($inbox->id, array_map(static fn (Inbox $i): string => $i->id, $mb->inboxes->list()->data));

        $subject = self::unique('inbox send');
        $sent = $mb->messages->send($inbox->id, ['to' => self::testAddress(), 'subject' => $subject, 'text' => 'Hello from an inbox']);
        self::assertStringStartsWith('msg_', $sent->id);
        self::assertSame($subject, $sent->subject);
        self::assertSame('out', $sent->direction);
        self::assertNull($sent->in_reply_to);

        // Same key twice: one message.
        $key = self::uuid();
        $a = $mb->messages->send($inbox->id, ['to' => self::testAddress(), 'subject' => self::unique('inbox idem'), 'text' => 'x'], new RequestOptions(idempotencyKey: $key));
        $b = $mb->messages->send($inbox->id, ['to' => self::testAddress(), 'subject' => $a->subject, 'text' => 'x'], new RequestOptions(idempotencyKey: $key));
        self::assertSame($a->id, $b->id);

        self::assertContains($sent->id, array_map(static fn (Message $m): string => $m->id, $mb->messages->list($inbox->id, ['limit' => 10])->data));
        self::assertSame($sent->thread_id, $mb->messages->get($sent->id)->thread_id);
        self::assertStringContainsString('Subject: ' . $subject, $mb->messages->raw($sent->id));

        $reply = $mb->messages->reply($sent->id, ['text' => 'A follow up']);
        self::assertSame($sent->thread_id, $reply->thread_id);
        self::assertSame($sent->rfc_message_id, $reply->in_reply_to);

        self::assertContains($sent->thread_id, array_map(static fn (Thread $t): string => $t->id, $mb->threads->list($inbox->id)->data));
        $thread = $mb->threads->get($sent->thread_id);
        $ids = array_map(static fn (Message $m): string => $m->id, $thread->messages);
        self::assertContains($sent->id, $ids);
        self::assertContains($reply->id, $ids);
        self::assertSame('open', $thread->status);

        // Nothing inbound arrives (no SMTP server in this run): wait returns null.
        self::assertNull($mb->messages->wait($inbox->id, ['timeout' => 1]));

        $conversations = $mb->conversations->list(['inbox_id' => $inbox->id, 'status' => 'all']);
        self::assertNotEmpty($conversations->data);
        self::assertSame($inbox->address, $conversations->data[0]->inbox->address);
        self::assertGreaterThanOrEqual(0, $mb->conversations->counts()->open);
        self::assertSame('closed', $mb->conversations->update($sent->thread_id, ['status' => 'closed'])->status);
        self::assertSame('open', $mb->conversations->update($sent->thread_id, ['status' => 'open'])->status);
        $snoozed = $mb->conversations->update($sent->thread_id, ['snoozed_until' => new DateTimeImmutable('+1 hour')]);
        self::assertNotNull($snoozed->snoozed_until);
        $woken = $mb->conversations->update($sent->thread_id, ['snoozed_until' => null]);
        self::assertNull($woken->snoozed_until);
        $mb->conversations->read($sent->thread_id);
        $mb->conversations->unread($sent->thread_id);

        $mb->inboxes->delete($inbox->id);
        $gone = $this->failure(fn () => $mb->inboxes->get($inbox->id));
        self::assertInstanceOf(NotFoundException::class, $gone);
        self::assertSame(404, $gone->status);
    }

    public function testInboxCreateWithNoArgumentsSendsAnObject(): void
    {
        $mb = self::client();
        $inbox = $mb->inboxes->create();
        self::assertNotSame('', $inbox->local_part);
        $mb->inboxes->delete($inbox->id);
    }

    public function testDomainsAddListGetByIdAndNameVerifyAndDelete(): void
    {
        $mb = self::client();
        $name = 'sdk-crud-' . bin2hex(random_bytes(4)) . '.example.com';
        $added = $mb->domains->create(['name' => $name]);
        self::assertSame($name, $added->name);
        self::assertSame('pending', $added->status);
        self::assertNotEmpty($added->records);
        self::assertNotSame('', $added->records[0]->name);
        self::assertNotSame('', $added->next_step);
        self::assertContains($added->id, array_map(static fn (Domain $d): string => $d->id, $mb->domains->list()->data));
        self::assertSame($name, $mb->domains->get($added->id)->name);
        self::assertSame($added->id, $mb->domains->get($name)->id);
        $checked = $mb->domains->verify($added->id);
        self::assertSame($added->id, $checked->id);
        self::assertNotSame('verified', $checked->status);
        $mb->domains->delete($added->id);
        self::assertSame(404, $this->failure(fn () => $mb->domains->get($added->id))->status);
    }

    public function testKeysCreateASendingKeyThatWorksListItAndRevokeIt(): void
    {
        $mb = self::client();
        $created = $mb->keys->create(['name' => 'sdk sending', 'permissions' => 'sending', 'domain_id' => Environment::$domainId]);
        self::assertNotEmpty($created->secret);
        self::assertSame('sending', $created->key->permissions);
        self::assertContains($created->key->id, array_map(static fn (ApiKey $k): string => $k->id, $mb->keys->list()->data));

        $sender = new Runearc($created->secret, baseUrl: Environment::$apiUrl);
        $sent = $sender->emails->send([
            'from' => 'app@' . Environment::$domain,
            'to' => self::testAddress(),
            'subject' => self::unique('sending key'),
            'text' => 'x',
        ]);
        self::assertStringStartsWith('eml_', $sent->id);
        // A sending key cannot manage inboxes.
        $denied = $this->failure(fn () => $sender->inboxes->create());
        self::assertInstanceOf(PermissionException::class, $denied);
        self::assertSame(403, $denied->status);

        $mb->keys->delete($created->key->id);
        $revoked = $this->failure(fn () => $sender->emails->list());
        self::assertInstanceOf(AuthenticationException::class, $revoked);
        self::assertSame(401, $revoked->status);
    }

    public function testSuppressionsAndEvents(): void
    {
        // The API has no POST for suppressions (bounces and complaints add them, and
        // simulated test recipients are never suppressed), so one row is added with SQL,
        // the way core's add_suppression stores it.
        $mb = self::client();
        $sent = $mb->emails->send(['from' => 'app@' . Environment::$domain, 'to' => self::testAddress(), 'subject' => self::unique('events'), 'text' => 'x']);
        $projectId = $mb->emails->get($sent->id)->project_id;
        $address = 'blocked-' . bin2hex(random_bytes(4)) . '@example.org';
        Environment::psql(sprintf(
            "INSERT INTO suppressions (org_id, project_id, address, reason) VALUES ('%s', '%s', '%s', 'manual')",
            Environment::$orgId,
            $projectId,
            $address,
        ));
        $found = array_values(array_filter($mb->suppressions->list()->data, static fn (Suppression $s): bool => $s->address === $address));
        self::assertSame('manual', $found[0]->reason);
        $mb->suppressions->delete($address);
        self::assertNotContains($address, array_map(static fn (Suppression $s): string => $s->address, $mb->suppressions->list()->data));

        $first = $mb->events->list(['limit' => 100]);
        self::assertNotEmpty($first->data);
        $types = array_map(static fn (Event $e): string => $e->type, $first->data);
        self::assertNotEmpty(array_filter($types, static fn (string $t): bool => str_starts_with($t, 'message.')));
        self::assertSame($first->data[count($first->data) - 1]->id, $first->next_after);
        self::assertNotNull($first->next_after);
        $next = $mb->events->list(['after' => $first->next_after, 'limit' => 100]);
        foreach ($next->data as $event) {
            self::assertGreaterThan($first->next_after, $event->id);
        }
    }
}
