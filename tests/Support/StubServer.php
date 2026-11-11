<?php

declare(strict_types=1);

namespace Runearc\Tests\Support;

use RuntimeException;

/** Runs `stub_server.php` as a separate process, so the SDK under test can block on it. */
final class StubServer
{
    /** @var resource */
    private $process;
    private readonly string $dir;
    public readonly string $url;

    /**
     * @param list<array<string, mixed>> $steps
     */
    public function __construct(array $steps)
    {
        $this->dir = sys_get_temp_dir() . '/runearc-stub-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
        file_put_contents($this->dir . '/scenario.json', json_encode($steps, JSON_THROW_ON_ERROR));
        $proc = proc_open(
            [PHP_BINARY, __DIR__ . '/stub_server.php', $this->dir . '/scenario.json', $this->dir . '/port', $this->dir . '/log'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', $this->dir . '/err', 'w']],
            $pipes,
        );
        if (!is_resource($proc)) {
            throw new RuntimeException('could not start the stub server');
        }
        $this->process = $proc;
        $until = microtime(true) + 10;
        while (!is_file($this->dir . '/port') || (string) file_get_contents($this->dir . '/port') === '') {
            if (microtime(true) > $until) {
                throw new RuntimeException('the stub server did not start: ' . (string) @file_get_contents($this->dir . '/err'));
            }
            usleep(20_000);
        }
        $this->url = 'http://127.0.0.1:' . trim((string) file_get_contents($this->dir . '/port'));
    }

    /**
     * @return list<array{method: string, target: string, headers: array<string, string>, body: string, at: float}>
     */
    public function requests(): array
    {
        $out = [];
        if (!is_file($this->dir . '/log')) {
            return $out;
        }
        foreach (file($this->dir . '/log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            /** @var array{method: string, target: string, headers: array<string, string>, body: string, at: float} $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $out[] = $row;
        }

        return $out;
    }

    public function stop(): void
    {
        proc_terminate($this->process, 9);
        proc_close($this->process);
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }
}
