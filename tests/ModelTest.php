<?php

declare(strict_types=1);

namespace Runearc\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Runearc\Model\AddedDomain;
use Runearc\Model\Conversation;
use Runearc\Model\Email;
use Runearc\Model\EmailDetail;
use Runearc\Model\Event;
use Runearc\Model\ListResponse;
use Runearc\Model\Message;
use Runearc\Model\Page;
use Runearc\Model\Waited;

final class ModelTest extends TestCase
{
    public function testEmailFromArrayIgnoresUnknownKeys(): void
    {
        $email = Email::fromArray([
            'id' => 'msg_01abc', 'subject' => 'Hi', 'status' => 'queued',
            'a_field_added_later' => 1,
        ]);
        self::assertSame('msg_01abc', $email->id);
        self::assertSame('queued', $email->status);
    }

    public function testNullableFieldsAreNullWhenTheApiSendsNullOrNothing(): void
    {
        $email = Email::fromArray(['id' => 'eml_1', 'domain_id' => null, 'from_name' => null]);
        self::assertNull($email->domain_id);
        self::assertNull($email->from_name);
        self::assertNull($email->scheduled_at);
        self::assertSame([], $email->to);
        self::assertSame([], $email->tags);
        self::assertSame(0, $email->size_bytes);
    }

    public function testNestedObjectsAndListsBecomeModels(): void
    {
        $detail = EmailDetail::fromArray([
            'id' => 'eml_1',
            'object' => 'email',
            'to' => ['a@example.com'],
            'tags' => [['name' => 'kind', 'value' => 'test', 'extra' => true]],
            'headers' => ['X-A' => 'b'],
            'attachments' => [['id' => 'att_1', 'filename' => 'a.txt', 'content_type' => 'text/plain', 'content_id' => null, 'size_bytes' => 2]],
            'recipients' => [['address' => 'a@example.com', 'kind' => 'to', 'status' => 'delivered', 'response' => null, 'updated_at' => '2026-11-09T10:00:00Z']],
        ]);
        self::assertSame('kind', $detail->tags[0]->name);
        self::assertSame(['X-A' => 'b'], $detail->headers);
        self::assertNull($detail->attachments[0]->content_id);
        self::assertSame(2, $detail->attachments[0]->size_bytes);
        self::assertNull($detail->recipients[0]->response);
        self::assertSame('delivered', $detail->recipients[0]->status);
    }

    public function testConversationAssigneeIsNullable(): void
    {
        $base = ['id' => 'thr_1', 'project' => ['id' => 'prj_1', 'name' => 'P'], 'inbox' => ['id' => 'ibx_1', 'address' => 'a@example.com'], 'unread' => true];
        $open = Conversation::fromArray($base + ['assignee' => null]);
        self::assertNull($open->assignee);
        self::assertSame('P', $open->project->name);
        self::assertTrue($open->unread);

        $assigned = Conversation::fromArray($base + ['assignee' => ['id' => 'usr_1', 'email' => 'me@example.com']]);
        self::assertSame('me@example.com', $assigned->assignee?->email);
    }

    public function testAddedDomainExistingMxIsNullable(): void
    {
        self::assertNull(AddedDomain::fromArray(['id' => 'dom_1', 'existing_mx' => null])->existing_mx);
        $mx = AddedDomain::fromArray(['id' => 'dom_1', 'existing_mx' => ['hosts' => ['mx.example.com'], 'suggestion' => 's', 'message' => 'm']])->existing_mx;
        self::assertSame(['mx.example.com'], $mx?->hosts);
    }

    public function testMessageKeepsAuthResultsAsGiven(): void
    {
        self::assertNull(Message::fromArray(['id' => 'msg_1', 'auth_results' => null])->auth_results);
        self::assertSame(['spf' => 'pass'], Message::fromArray(['id' => 'msg_1', 'auth_results' => ['spf' => 'pass']])->auth_results);
    }

    public function testWaitedHasMessageCodesAndLinks(): void
    {
        $waited = Waited::fromArray(['message' => ['id' => 'msg_1', 'subject' => 'Code'], 'codes' => ['123456'], 'links' => ['https://example.com/v']]);
        self::assertSame('Code', $waited->message->subject);
        self::assertSame(['123456'], $waited->codes);
        self::assertSame(['https://example.com/v'], $waited->links);
    }

    public function testPageAndListResponse(): void
    {
        $page = Page::of(['data' => [['id' => 'eml_1'], ['id' => 'eml_2']], 'next_cursor' => 'c'], Email::fromArray(...));
        self::assertSame(['eml_1', 'eml_2'], array_map(static fn (Email $e): string => $e->id, $page->data));
        self::assertSame('c', $page->next_cursor);
        self::assertNull(Page::of(['data' => [], 'next_cursor' => null], Email::fromArray(...))->next_cursor);

        $list = ListResponse::of(['data' => [['id' => 'eml_1']], 'next_cursor' => 'ignored'], Email::fromArray(...));
        self::assertCount(1, $list->data);
    }

    public function testEventDataStaysAnArray(): void
    {
        $event = Event::fromArray(['id' => 'evt_1', 'type' => 'message.delivered', 'data' => ['email_id' => 'eml_1']]);
        self::assertSame(['email_id' => 'eml_1'], $event->data);
    }

    public function testEveryModelIsFinalAndReadonly(): void
    {
        $files = glob(dirname(__DIR__) . '/src/Model/*.php');
        self::assertNotFalse($files);
        self::assertGreaterThan(30, count($files));
        foreach ($files as $file) {
            $name = 'Runearc\\Model\\' . basename($file, '.php');
            self::assertTrue(class_exists($name));
            $class = new ReflectionClass($name);
            self::assertTrue($class->isFinal(), $class->getName() . ' must be final');
            self::assertTrue($class->isReadOnly(), $class->getName() . ' must be readonly');
        }
    }
}
