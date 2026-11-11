<?php

declare(strict_types=1);

namespace Runearc\Tests;

use Closure;
use PHPUnit\Framework\TestCase as BaseTestCase;
use RuntimeException;
use Runearc\Exception\RunearcException;
use Runearc\Runearc;
use Runearc\Tests\Support\Environment;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    protected static function client(int $maxRetries = 2): Runearc
    {
        return new Runearc(Environment::$apiKey, baseUrl: Environment::$apiUrl, maxRetries: $maxRetries);
    }

    /** A simulated recipient: delivered (or bounced/complained) without an MTA. */
    protected static function testAddress(string $local = 'delivered'): string
    {
        return $local . '@' . Environment::$testDomain;
    }

    protected static function from(string $local = 'app'): string
    {
        return sprintf('SDK Tests <%s@%s>', $local, Environment::$domain);
    }

    protected static function unique(string $prefix): string
    {
        return $prefix . ' ' . bin2hex(random_bytes(6));
    }

    protected static function uuid(): string
    {
        $h = bin2hex(random_bytes(16));

        return sprintf('%s-%s-4%s-a%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3), substr($h, 17, 3), substr($h, 20, 12));
    }

    /**
     * Polls `$fn` until it returns a truthy value.
     *
     * @template T
     * @param Closure(): (T|null|false) $fn
     * @return T
     */
    protected static function eventually(Closure $fn, int $ms = 15_000): mixed
    {
        $until = microtime(true) + $ms / 1000;
        $last = null;
        while (microtime(true) < $until) {
            try {
                $value = $fn();
                if ($value) {
                    return $value;
                }
            } catch (Throwable $e) {
                $last = $e;
            }
            usleep(150_000);
        }
        throw new RuntimeException(sprintf('condition not met in %d ms%s', $ms, $last === null ? '' : ': ' . $last->getMessage()));
    }

    /** Runs `$fn` and returns the exception it throws. */
    protected function failure(Closure $fn): RunearcException
    {
        try {
            $fn();
        } catch (RunearcException $e) {
            return $e;
        }
        self::fail('expected the call to fail');
    }
}
