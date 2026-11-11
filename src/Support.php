<?php

declare(strict_types=1);

namespace Runearc;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use stdClass;

/** @internal Small helpers shared by the resources. */
final class Support
{
    /** An RFC 3339 UTC time for a `DateTimeInterface`; strings and null pass through. */
    public static function time(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.v\Z');
        }

        return $value;
    }

    public static function segment(string $id): string
    {
        return rawurlencode($id);
    }

    /**
     * Keeps an empty map a JSON object instead of `[]`.
     *
     * @param array<string, mixed> $map
     * @return array<string, mixed>|stdClass
     */
    public static function object(array $map): array|stdClass
    {
        return $map === [] ? new stdClass() : $map;
    }

    /**
     * The JSON body of an email: `scheduledAt` becomes `scheduled_at`, times become strings.
     *
     * @param array<string, mixed> $email
     * @return array<string, mixed>
     */
    public static function emailBody(array $email): array
    {
        $when = $email['scheduled_at'] ?? $email['scheduledAt'] ?? null;
        unset($email['scheduledAt']);
        if ($when !== null) {
            $email['scheduled_at'] = self::time($when);
        }
        if (($email['headers'] ?? null) === []) {
            unset($email['headers']);
        }

        return $email;
    }

    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);

        return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20));
    }
}
