<?php

declare(strict_types=1);

namespace Runearc;

use Closure;

/**
 * @internal Reads the API's decoded JSON for the models: a field that is missing or of
 * another type becomes the type's empty value, so a model never throws on a new answer.
 */
final class Fields
{
    public static function string(mixed $value): string
    {
        return is_string($value) ? $value : (is_int($value) || is_float($value) ? (string) $value : '');
    }

    public static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : self::string($value);
    }

    public static function int(mixed $value): int
    {
        return is_int($value) ? $value : (is_float($value) || is_numeric($value) ? (int) $value : 0);
    }

    public static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : self::int($value);
    }

    public static function bool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === 'true';
    }

    /** @return list<string> */
    public static function strings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(self::string(...), $value));
    }

    /** @return array<string, string> */
    public static function stringMap(mixed $value): array
    {
        $out = [];
        foreach (self::map($value) as $key => $item) {
            $out[$key] = self::string($item);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function map(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $key => $item) {
            $out[(string) $key] = $item;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public static function objects(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(self::map(...), $value));
    }

    /**
     * @template T
     * @param Closure(array<string, mixed>): T $build
     * @return T|null
     */
    public static function optional(mixed $value, Closure $build): mixed
    {
        return is_array($value) ? $build(self::map($value)) : null;
    }
}
