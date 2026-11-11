<?php

declare(strict_types=1);

namespace Runearc\Tests\Support;

use RuntimeException;

/**
 * Starts a real Runearc API (and worker) against a throwaway Postgres database for
 * the test run, then tears both down. Nothing in the mail path is mocked: sends go
 * to the platform's simulated test recipients (`delivered@test.<platform domain>`),
 * which the API records as delivered without an MTA.
 *
 * Needs Postgres on localhost (createdb, dropdb, psql on PATH) and the Rust binaries:
 *   CARGO_TARGET_DIR=../../target/agent-sdk cargo build -p runearc-api -p runearc-worker
 * Override the binary directory with RUNEARC_BIN_DIR.
 */
final class Environment
{
    public static string $apiUrl = '';
    public static string $apiKey = '';
    public static string $dbUrl = '';
    public static string $orgId = '';
    public static string $domain = '';
    public static string $domainId = '';
    public static string $testDomain = '';
    public static string $sharedDomain = '';

    /** @var list<resource> */
    private static array $children = [];
    private static string $dbName = '';
    private static string $blobDir = '';
    private static string $logDir = '';
    private static bool $started = false;

    public static function start(): void
    {
        $repo = dirname(__DIR__, 4);
        $binDir = getenv('RUNEARC_BIN_DIR') ?: $repo . '/target/agent-sdk/debug';
        foreach (['runearc-api', 'runearc-worker'] as $bin) {
            if (!is_file($binDir . '/' . $bin)) {
                throw new RuntimeException(sprintf(
                    '%s/%s is missing. Build it: CARGO_TARGET_DIR=%s/target/agent-sdk cargo build -p runearc-api -p runearc-worker',
                    $binDir,
                    $bin,
                    $repo,
                ));
            }
        }
        self::$started = true;
        self::$dbName = 'runearc_sdk_test_' . bin2hex(random_bytes(6));
        self::run(['createdb', '-h', 'localhost', self::$dbName]);
        self::$dbUrl = 'postgres://localhost/' . self::$dbName;
        self::$blobDir = self::tempDir('runearc-sdk-blobs-');
        self::$logDir = self::tempDir('runearc-sdk-logs-');
        $port = self::freePort();
        self::$apiUrl = 'http://127.0.0.1:' . $port;

        // A clean environment: none of the developer's RUNEARC_* or KUMO_* settings leak in.
        $env = [
            'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
            'HOME' => getenv('HOME') ?: sys_get_temp_dir(),
            'RUST_LOG' => 'warn',
            'DATABASE_URL' => self::$dbUrl,
            'BLOB_URL' => 'file://' . self::$blobDir,
            'RUNEARC_SECRET_KEY' => bin2hex(random_bytes(32)),
            'LISTEN_ADDR' => '127.0.0.1:' . $port,
            'RUNEARC_ALLOW_LOCAL_WEBHOOKS' => '1',
            // The platform refuses to start without a provider. Test recipients are recorded
            // as delivered without touching it, so this relay is never connected to.
            'RUNEARC_OUTBOUND_PROVIDER' => 'smtp',
            'RUNEARC_SMTP_RELAY' => '127.0.0.1:1',
        ];

        // Bootstrap first: it runs the migrations and prints the key once (never logged here).
        $out = self::run([$binDir . '/runearc-api', 'bootstrap', 'sdk-' . bin2hex(random_bytes(3))], $env);
        if (preg_match('/api key \(shown once\): (\S+)/', $out, $key) !== 1 || preg_match('/org: (org_\S+)/', $out, $org) !== 1) {
            throw new RuntimeException('bootstrap did not print an org and a key');
        }
        self::$apiKey = $key[1];
        self::$orgId = $org[1];
        // Room for the CRUD tests: the free plan allows 3 inboxes and 3 domains.
        self::psql(sprintf("UPDATE orgs SET plan = 'scale' WHERE id = '%s'", self::$orgId));

        self::spawn($binDir . '/runearc-api', $env, 'api');
        self::waitHealthy();
        self::spawn($binDir . '/runearc-worker', $env, 'worker');

        $platform = self::request('GET', '/v1/platform');
        self::$testDomain = self::string($platform['test_domain'] ?? null);
        self::$sharedDomain = self::string($platform['shared_domain'] ?? null);

        // One verified sending domain for the whole run, as the Rust tests do it:
        // added through the API, marked verified with one UPDATE (verification needs public DNS).
        self::$domain = 'sdk-' . bin2hex(random_bytes(4)) . '.example.com';
        $added = self::request('POST', '/v1/domains', ['name' => self::$domain], 201);
        self::$domainId = self::string($added['id'] ?? null);
        self::psql(sprintf("UPDATE domains SET status = 'verified', verified_at = now() WHERE id = '%s'", self::$domainId));
    }

    public static function stop(): void
    {
        if (!self::$started) {
            return;
        }
        self::$started = false;
        foreach (self::$children as $child) {
            $status = proc_get_status($child);
            if ($status['running']) {
                proc_terminate($child);
            }
        }
        $until = microtime(true) + 5;
        foreach (self::$children as $child) {
            while (proc_get_status($child)['running'] && microtime(true) < $until) {
                usleep(50_000);
            }
            if (proc_get_status($child)['running']) {
                proc_terminate($child, 9);
            }
            proc_close($child);
        }
        self::$children = [];
        if (getenv('RUNEARC_KEEP_TEST_DB') !== '1') {
            self::run(['dropdb', '-h', 'localhost', '--force', '--if-exists', self::$dbName]);
        }
        foreach ([self::$blobDir, self::$logDir] as $dir) {
            self::removeTree($dir);
        }
    }

    public static function psql(string $sql): string
    {
        return trim(self::run(['psql', self::$dbUrl, '-v', 'ON_ERROR_STOP=1', '-tA', '-c', $sql]));
    }

    /**
     * @param list<string> $command
     * @param array<string, string>|null $env
     */
    private static function run(array $command, ?array $env = null): string
    {
        $proc = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($proc)) {
            throw new RuntimeException('could not run ' . $command[0]);
        }
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        if (proc_close($proc) !== 0) {
            throw new RuntimeException(sprintf('%s failed: %s', $command[0], $err));
        }

        return $out;
    }

    /**
     * @param array<string, string> $env
     */
    private static function spawn(string $binary, array $env, string $name): void
    {
        $proc = proc_open([$binary], [0 => ['file', '/dev/null', 'r'], 1 => ['file', self::$logDir . "/$name.log", 'a'], 2 => ['file', self::$logDir . "/$name.log", 'a']], $pipes, null, $env);
        if (!is_resource($proc)) {
            throw new RuntimeException("could not start $name");
        }
        self::$children[] = $proc;
    }

    private static function waitHealthy(): void
    {
        $until = microtime(true) + 60;
        while (microtime(true) < $until) {
            $api = self::$children[0];
            if (!proc_get_status($api)['running']) {
                throw new RuntimeException("runearc-api exited:\n" . self::log('api'));
            }
            $curl = curl_init(self::$apiUrl . '/healthz');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 2);
            curl_exec($curl);
            if (curl_getinfo($curl, CURLINFO_RESPONSE_CODE) === 200) {
                return;
            }
            usleep(200_000);
        }
        throw new RuntimeException("runearc-api did not become healthy:\n" . self::log('api'));
    }

    private static function log(string $name): string
    {
        return substr((string) @file_get_contents(self::$logDir . "/$name.log"), -20_000);
    }

    /**
     * A plain API call for setup only; the SDK is what the tests exercise.
     *
     * @param non-empty-string $method
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private static function request(string $method, string $path, ?array $body = null, int $expect = 200): array
    {
        $curl = curl_init(self::$apiUrl . $path);
        $headers = ['Authorization: Bearer ' . self::$apiKey];
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $raw = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if (!is_string($raw) || $status !== $expect) {
            throw new RuntimeException(sprintf('%s %s failed: %d %s', $method, $path, $status, (string) $raw));
        }
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException("$path did not answer with an object");
        }
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private static function string(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('the API did not send an expected field');
        }

        return $value;
    }

    private static function freePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($server === false) {
            throw new RuntimeException("no free port: $error");
        }
        $name = (string) stream_socket_get_name($server, false);
        fclose($server);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private static function tempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);

        return $dir;
    }

    private static function removeTree(string $dir): void
    {
        foreach (glob($dir . '/{,.}*[!.]*', GLOB_BRACE) ?: [] as $entry) {
            is_dir($entry) ? self::removeTree($entry) : unlink($entry);
        }
        rmdir($dir);
    }
}
