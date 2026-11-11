<?php

declare(strict_types=1);

namespace Runearc\Resources;

use DateTimeInterface;
use Runearc\BatchOptions;
use Runearc\Model\BatchResponse;
use Runearc\Model\Email;
use Runearc\Model\EmailDetail;
use Runearc\Model\EmailRef;
use Runearc\Model\Page;
use Runearc\Model\SendEmailResponse;
use Runearc\RequestOptions;
use Runearc\Support;

final class Emails extends Resource
{
    /**
     * Sends one email, or schedules it with `scheduled_at` (a string or a DateTimeInterface).
     *
     * @param array<string, mixed> $email
     */
    public function send(array $email, ?RequestOptions $options = null): SendEmailResponse
    {
        return SendEmailResponse::fromArray(self::map($this->api->json('POST', '/v1/emails', body: Support::emailBody($email), sendsMail: true, options: $options)));
    }

    /**
     * Sends up to 100 emails in one request.
     *
     * @param list<array<string, mixed>> $emails
     * @return BatchResponse `data` has the ids in request order; `errors` the refused items (permissive only).
     */
    public function batch(array $emails, ?BatchOptions $options = null): BatchResponse
    {
        $validation = $options?->batchValidation;

        return BatchResponse::fromArray(self::map($this->api->json(
            'POST',
            '/v1/emails/batch',
            body: array_map(Support::emailBody(...), $emails),
            headers: $validation === null ? [] : ['x-batch-validation' => $validation],
            sendsMail: true,
            options: $options,
        )));
    }

    public function get(string $id, ?RequestOptions $options = null): EmailDetail
    {
        return EmailDetail::fromArray(self::map($this->api->json('GET', '/v1/emails/' . Support::segment($id), options: $options)));
    }

    /**
     * @param array{limit?: int, cursor?: string} $params
     * @return Page<Email>
     */
    public function list(array $params = [], ?RequestOptions $options = null): Page
    {
        return Page::of(self::map($this->api->json('GET', '/v1/emails', query: $params, options: $options)), Email::fromArray(...));
    }

    /**
     * Changes when a scheduled email sends.
     *
     * @param array{scheduled_at: string|DateTimeInterface} $update
     */
    public function update(string $id, array $update, ?RequestOptions $options = null): EmailRef
    {
        return EmailRef::fromArray(self::map($this->api->json(
            'PATCH',
            '/v1/emails/' . Support::segment($id),
            body: ['scheduled_at' => Support::time($update['scheduled_at'])],
            options: $options,
        )));
    }

    /**
     * Shorthand for `update($id, ['scheduled_at' => $scheduledAt])`.
     */
    public function reschedule(string $id, string|DateTimeInterface $scheduledAt, ?RequestOptions $options = null): EmailRef
    {
        return $this->update($id, ['scheduled_at' => $scheduledAt], $options);
    }

    /**
     * Cancels a scheduled email.
     */
    public function cancel(string $id, ?RequestOptions $options = null): EmailRef
    {
        return EmailRef::fromArray(self::map($this->api->json('POST', '/v1/emails/' . Support::segment($id) . '/cancel', idempotent: true, options: $options)));
    }
}
