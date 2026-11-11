<?php

declare(strict_types=1);

namespace Runearc\Tests;

use DateTimeImmutable;
use Runearc\BatchOptions;
use Runearc\Exception\AuthenticationException;
use Runearc\Exception\ConflictException;
use Runearc\Exception\InvalidRequestException;
use Runearc\Exception\NotFoundException;
use Runearc\Exception\PermissionException;
use Runearc\Model\Email;
use Runearc\Model\EmailDetail;
use Runearc\Model\SenderAddress;
use Runearc\RequestOptions;
use Runearc\Runearc;
use Runearc\Tests\Support\Environment;

final class EmailsTest extends TestCase
{
    public function testSendsToASimulatedRecipientAndReadsItBack(): void
    {
        $mb = self::client();
        $subject = self::unique('send');
        $sent = $mb->emails->send([
            'from' => self::from(),
            'to' => self::testAddress(),
            'subject' => $subject,
            'html' => '<p>Hello</p>',
            'text' => 'Hello',
            'tags' => [['name' => 'kind', 'value' => 'test']],
        ]);
        $id = $sent->id;
        self::assertStringStartsWith('eml_', $id);

        $got = self::eventually(static function () use ($mb, $id): ?EmailDetail {
            $e = $mb->emails->get($id);

            return $e->status === 'delivered' ? $e : null;
        });
        self::assertSame('email', $got->object);
        self::assertSame($subject, $got->subject);
        self::assertSame([self::testAddress()], $got->to);
        self::assertSame('delivered', $got->recipients[0]->status);
        self::assertSame('kind', $got->tags[0]->name);
        self::assertSame('test', $got->tags[0]->value);
        self::assertSame($id, $got->id);
        self::assertNull($got->scheduled_at);

        $page = $mb->emails->list(['limit' => 100]);
        self::assertContains($id, array_map(static fn (Email $e): string => $e->id, $page->data));
        self::assertInstanceOf(Email::class, $page->data[0]);
    }

    public function testRepeatedIdempotencyKeyReturnsTheSameIdAndADifferentBodyIsRefused(): void
    {
        $mb = self::client();
        $key = self::uuid();
        $body = ['from' => self::from(), 'to' => self::testAddress(), 'subject' => self::unique('idem'), 'text' => 'once'];
        $a = $mb->emails->send($body, new RequestOptions(idempotencyKey: $key));
        $b = $mb->emails->send($body, new RequestOptions(idempotencyKey: $key));
        self::assertSame($a->id, $b->id);

        $err = $this->failure(fn () => $mb->emails->send([...$body, 'text' => 'different'], new RequestOptions(idempotencyKey: $key)));
        self::assertInstanceOf(ConflictException::class, $err);
        self::assertSame(409, $err->status);
        self::assertSame('conflict', $err->kind);
        self::assertSame('idempotency_conflict', $err->errorCode);
    }

    public function testThrowsTypedErrorsWithACodeAndAFix(): void
    {
        $mb = self::client();
        $err = $this->failure(fn () => $mb->emails->send([
            'from' => 'nobody@not-my-domain.example',
            'to' => self::testAddress(),
            'subject' => 'x',
            'text' => 'x',
        ]));
        self::assertInstanceOf(PermissionException::class, $err);
        self::assertSame(403, $err->status);
        self::assertSame('forbidden', $err->kind);
        self::assertSame('sender_not_verified', $err->errorCode);
        self::assertNotEmpty($err->fix);
        self::assertNotSame('', $err->getMessage());
        self::assertSame('sender_not_verified', $err->toArray()['code']);

        $bad = new Runearc('mb_live_not_a_real_key', baseUrl: Environment::$apiUrl);
        $unauth = $this->failure(fn () => $bad->emails->list());
        self::assertInstanceOf(AuthenticationException::class, $unauth);
        self::assertSame(401, $unauth->status);
        self::assertSame('invalid_api_key', $unauth->errorCode);
        self::assertNotEmpty($unauth->fix);

        $missing = $this->failure(fn () => $mb->emails->get('eml_00000000000000000000000000'));
        self::assertInstanceOf(NotFoundException::class, $missing);
        self::assertSame(404, $missing->status);
    }

    public function testListsSendersIncludingTheVerifiedDomain(): void
    {
        $data = self::client()->senders->list()->data;
        $mine = array_filter($data, static fn (SenderAddress $s): bool => $s->domain === Environment::$domain && $s->verified);
        self::assertNotEmpty($mine);
    }

    public function testBatchSendsEveryValidItemAndReturnsIdsInOrder(): void
    {
        $mb = self::client();
        $subjects = [self::unique('batch a'), self::unique('batch b'), self::unique('batch c')];
        $res = $mb->emails->batch(array_map(
            static fn (string $subject): array => ['from' => self::from(), 'to' => self::testAddress(), 'subject' => $subject, 'text' => 'hi'],
            $subjects,
        ));
        self::assertCount(3, $res->data);
        foreach ($res->data as $i => $item) {
            self::assertSame($subjects[$i], $mb->emails->get($item->id)->subject);
        }
        self::assertSame([], $res->errors);
    }

    public function testStrictBatchRefusesEverythingWithErrors(): void
    {
        $mb = self::client();
        $subject = self::unique('strict');
        $err = $this->failure(fn () => $mb->emails->batch([
            ['from' => self::from(), 'to' => self::testAddress(), 'subject' => $subject, 'text' => 'ok'],
            ['from' => 'nobody@not-my-domain.example', 'to' => self::testAddress(), 'subject' => $subject, 'text' => 'bad'],
            ['from' => self::from(), 'to' => self::testAddress(), 'subject' => $subject, 'text' => 'ok'],
        ]));
        self::assertInstanceOf(InvalidRequestException::class, $err);
        self::assertSame(400, $err->status);
        self::assertSame('invalid_batch', $err->errorCode);
        self::assertStringContainsString('emails[1]', $err->getMessage());
        self::assertNotNull($err->errors);
        self::assertCount(1, $err->errors);
        self::assertSame(1, $err->errors[0]['index']);
        self::assertNotEmpty($err->errors[0]['code']);
        // Nothing was stored.
        $stored = array_filter($mb->emails->list(['limit' => 100])->data, static fn (Email $e): bool => $e->subject === $subject);
        self::assertCount(0, $stored);
    }

    public function testPermissiveBatchSendsValidItemsAndReportsTheRest(): void
    {
        $res = self::client()->emails->batch(
            [
                ['from' => self::from(), 'to' => self::testAddress(), 'subject' => self::unique('perm'), 'text' => 'ok'],
                ['from' => 'nobody@not-my-domain.example', 'to' => self::testAddress(), 'subject' => 'bad', 'text' => 'bad'],
            ],
            new BatchOptions(batchValidation: 'permissive'),
        );
        self::assertCount(1, $res->data);
        self::assertCount(1, $res->errors);
        self::assertSame(1, $res->errors[0]->index);
        self::assertSame('sender_not_verified', $res->errors[0]->code);
    }

    public function testBatchRepeatsWithTheSameKeyReturnTheSameIds(): void
    {
        $mb = self::client();
        $items = [['from' => self::from(), 'to' => self::testAddress(), 'subject' => self::unique('batch idem'), 'text' => 'x']];
        $key = self::uuid();
        $a = $mb->emails->batch($items, new BatchOptions(idempotencyKey: $key));
        $b = $mb->emails->batch($items, new BatchOptions(idempotencyKey: $key));
        self::assertEquals($a->data, $b->data);
    }

    public function testSchedulesReschedulesAndCancels(): void
    {
        $mb = self::client();
        $when = new DateTimeImmutable('+1 hour');
        $sent = $mb->emails->send([
            'from' => self::from(),
            'to' => self::testAddress(),
            'subject' => self::unique('scheduled'),
            'text' => 'later',
            'scheduledAt' => $when,
        ]);
        $id = $sent->id;
        $e = $mb->emails->get($id);
        self::assertSame('scheduled', $e->status);
        self::assertSame('scheduled', $e->last_event);
        self::assertNotNull($e->scheduled_at);
        self::assertEqualsWithDelta($when->getTimestamp(), strtotime($e->scheduled_at), 1);

        $later = new DateTimeImmutable('+2 hours');
        $rescheduled = $mb->emails->reschedule($id, $later);
        self::assertSame('email', $rescheduled->object);
        self::assertSame($id, $rescheduled->id);
        $scheduledAt = $mb->emails->get($id)->scheduled_at;
        self::assertNotNull($scheduledAt);
        self::assertEqualsWithDelta($later->getTimestamp(), strtotime($scheduledAt), 1);

        self::assertSame($id, $mb->emails->cancel($id)->id);
        self::assertSame('canceled', $mb->emails->get($id)->status);

        $err = $this->failure(fn () => $mb->emails->update($id, ['scheduled_at' => 'in 1 hour']));
        self::assertSame(409, $err->status);
        self::assertSame('email_not_scheduled', $err->errorCode);
    }

    public function testAcceptsTheRelativeFormAndRefusesTimesWithoutAnOffset(): void
    {
        $mb = self::client();
        $sent = $mb->emails->send([
            'from' => self::from(),
            'to' => self::testAddress(),
            'subject' => self::unique('relative'),
            'text' => 'later',
            'scheduled_at' => 'in 2 hours',
        ]);
        self::assertSame('scheduled', $mb->emails->get($sent->id)->status);
        $mb->emails->cancel($sent->id);

        $err = $this->failure(fn () => $mb->emails->send([
            'from' => self::from(),
            'to' => self::testAddress(),
            'subject' => 'x',
            'text' => 'x',
            'scheduled_at' => 'tomorrow at 9am',
        ]));
        self::assertSame(400, $err->status);
        self::assertSame('invalid_scheduled_at', $err->errorCode);
        self::assertNotEmpty($err->fix);
    }

    public function testRefusesToCancelAnEmailThatWasSent(): void
    {
        $mb = self::client();
        $sent = $mb->emails->send(['from' => self::from(), 'to' => self::testAddress(), 'subject' => self::unique('now'), 'text' => 'now']);
        $err = $this->failure(fn () => $mb->emails->cancel($sent->id));
        self::assertSame(409, $err->status);
        self::assertSame('email_not_scheduled', $err->errorCode);
    }
}
