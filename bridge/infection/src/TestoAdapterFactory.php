<?php

declare(strict_types=1);

namespace Testo\Bridge\Infection;

use Infection\AbstractTestFramework\TestFrameworkAdapter;
use Infection\AbstractTestFramework\TestFrameworkAdapterFactory;

/**
 * Registers {@see TestoAdapter} with Infection's extension installer.
 *
 * @internal
 */
final class TestoAdapterFactory implements TestFrameworkAdapterFactory
{
    #[\Override]
    public static function create(
        string $testFrameworkExecutable,
        string $tmpDir,
        string $testFrameworkConfigPath,
        ?string $testFrameworkConfigDir,
        string $jUnitFilePath,
        string $projectDir,
        array $sourceDirectories,
        bool $skipCoverage,
    ): TestFrameworkAdapter {
        return new TestoAdapter(
            testFrameworkExecutable: $testFrameworkExecutable,
            projectDir: $projectDir,
            tmpDir: $tmpDir,
        );
    }

    #[\Override]
    public static function getAdapterName(): string
    {
        return 'testo';
    }

    #[\Override]
    public static function getExecutableName(): string
    {
        return 'testo';
    }
}
