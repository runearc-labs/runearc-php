<?php

declare(strict_types=1);

namespace Runearc\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/** The package metadata: no runtime dependencies beyond ext-json and ext-curl, strict types everywhere. */
final class PackageTest extends BaseTestCase
{
    public function testHasNoRuntimeDependenciesBeyondTheExtensions(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame('runearc/runearc', $composer['name'] ?? null);
        self::assertSame('MIT', $composer['license'] ?? null);
        $require = $composer['require'] ?? null;
        self::assertIsArray($require);
        self::assertSame(['php', 'ext-curl', 'ext-json'], array_keys($require));
        self::assertSame('>=8.2', $require['php']);
    }

    public function testLicenceMatchesTheNodeSdk(): void
    {
        self::assertSame(
            (string) file_get_contents(dirname(__DIR__, 2) . '/sdk-node/LICENSE'),
            (string) file_get_contents(dirname(__DIR__) . '/LICENSE'),
        );
    }

    public function testEverySourceFileDeclaresStrictTypes(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__) . '/src'));
        $count = 0;
        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                self::assertStringContainsString('declare(strict_types=1);', (string) file_get_contents($file->getPathname()), $file->getFilename());
                $count++;
            }
        }
        self::assertGreaterThan(10, $count);
    }
}
