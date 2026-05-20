<?php

declare(strict_types=1);

namespace Tests\Bridge\SymfonyConsole\Acceptance;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;
use Testo\Application\Config\ApplicationConfig;
use Testo\Assert;
use Testo\Bridge\Symfony\Console\Command\Init;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Tests\Bridge\SymfonyConsole\Testo\Sandbox;

/**
 * Acceptance tests for `testo init`.
 *
 * The command resolves `composer.json` and `src/` relative to the process CWD,
 * so each test runs inside a fresh {@see Sandbox} directory. Assertions look at
 * the exit code, files written to disk, and the JSON shape of `composer.json` —
 * the same surface a user would observe after running `vendor/bin/testo init`.
 */
#[Test]
#[Covers(Init::class)]
final class InitCommandTest
{
    private Sandbox $sandbox;

    public function exitsWithSuccessWhenSrcAndComposerExist(): void
    {
        $this->givenMinimalProject();

        $tester = $this->run();

        Assert::same(
            $tester->getStatusCode(),
            Command::SUCCESS,
            'init must succeed on a project with src/ and composer.json; output: ' . $tester->getDisplay(),
        );
    }

    public function createsTestsAndUnitDirectories(): void
    {
        $this->givenMinimalProject();

        $this->run();

        Assert::true(\is_dir($this->sandbox->path('tests')), 'tests/ must be created');
        Assert::true(\is_dir($this->sandbox->path('tests/Unit')), 'tests/Unit/ must be created');
    }

    public function generatesTestoConfigFile(): void
    {
        $this->givenMinimalProject();

        $this->run();

        $configPath = $this->sandbox->path('testo.php');
        Assert::true(\is_file($configPath), 'testo.php must be created at the project root');

        $contents = (string) \file_get_contents($configPath);
        Assert::true(
            \str_contains($contents, 'ApplicationConfig'),
            'generated testo.php must reference ApplicationConfig; got: ' . $contents,
        );
        Assert::true(
            \str_contains($contents, "'src'"),
            'generated testo.php must reference the discovered src directory; got: ' . $contents,
        );
    }

    public function generatedConfigDeclaresEveryDetectedSuite(): void
    {
        $this->givenMinimalProject();
        $this->sandbox->makeDir('tests/Integration');
        $this->sandbox->makeDir('tests/Functional');

        $this->run();

        $contents = (string) \file_get_contents($this->sandbox->path('testo.php'));
        Assert::true(\str_contains($contents, "name: 'Unit'"), 'Unit suite must be wired in: ' . $contents);
        Assert::true(\str_contains($contents, "name: 'Integration'"), 'Integration suite must be wired in: ' . $contents);
        Assert::true(\str_contains($contents, "name: 'Functional'"), 'Functional suite must be wired in: ' . $contents);
    }

    public function registersTestAndTestUnitScriptsInComposerJson(): void
    {
        $this->givenMinimalProject();

        $this->run();

        $scripts = $this->readComposerScripts();
        Assert::same($scripts['test'] ?? null, 'vendor/bin/testo');
        Assert::same($scripts['test:unit'] ?? null, 'vendor/bin/testo --suite=Unit');
    }

    public function registersScriptForEachDetectedSuite(): void
    {
        $this->givenMinimalProject();
        $this->sandbox->makeDir('tests/Integration');
        $this->sandbox->makeDir('tests/Acceptance');

        $this->run();

        $scripts = $this->readComposerScripts();
        Assert::same($scripts['test:integration'] ?? null, 'vendor/bin/testo --suite=Integration');
        Assert::same($scripts['test:acceptance'] ?? null, 'vendor/bin/testo --suite=Acceptance');
    }

    public function preservesUnrelatedComposerScripts(): void
    {
        $this->givenMinimalProject([
            'scripts' => [
                'lint' => 'php-cs-fixer fix',
                'analyse' => 'psalm',
            ],
        ]);

        $this->run();

        $scripts = $this->readComposerScripts();
        Assert::same($scripts['lint'] ?? null, 'php-cs-fixer fix', 'pre-existing scripts must remain');
        Assert::same($scripts['analyse'] ?? null, 'psalm');
    }

    public function doesNotOverwriteExistingRootTestScript(): void
    {
        $this->givenMinimalProject([
            'scripts' => [
                'test' => 'phpunit && ecs',
            ],
        ]);

        $this->run();

        $scripts = $this->readComposerScripts();
        Assert::same(
            $scripts['test'] ?? null,
            'phpunit && ecs',
            'pre-existing root "test" script must not be replaced',
        );
    }

    public function doesNotOverwriteExistingSuiteSpecificScript(): void
    {
        $this->givenMinimalProject([
            'scripts' => [
                'test:unit' => 'custom-runner --suite=Unit',
            ],
        ]);

        $this->run();

        $scripts = $this->readComposerScripts();
        Assert::same(
            $scripts['test:unit'] ?? null,
            'custom-runner --suite=Unit',
            'pre-existing test:unit script must not be replaced',
        );
    }

    public function failsWhenSrcDirectoryMissingInNonInteractiveMode(): void
    {
        $this->sandbox->writeFile('composer.json', "{}\n");

        $tester = $this->run();

        Assert::same(
            $tester->getStatusCode(),
            Command::FAILURE,
            'init must fail when src/ is absent and the input is non-interactive',
        );
        Assert::true(
            \str_contains($tester->getDisplay(), 'src/'),
            'failure message must mention the missing src/ directory; got: ' . $tester->getDisplay(),
        );
        Assert::false(
            \is_file($this->sandbox->path('testo.php')),
            'no testo.php should be written when initialization fails',
        );
    }

    public function skipsOverwritingExistingTestoConfigInNonInteractiveMode(): void
    {
        $this->givenMinimalProject();
        $existing = "<?php\n// user-edited config — must not be clobbered\nreturn 'sentinel';\n";
        $this->sandbox->writeFile('testo.php', $existing);

        $tester = $this->run();

        Assert::same(
            $tester->getStatusCode(),
            Command::SUCCESS,
            'init must report success even when it declines to overwrite testo.php',
        );
        Assert::same(
            \file_get_contents($this->sandbox->path('testo.php')),
            $existing,
            'existing testo.php must be left untouched in non-interactive mode',
        );
    }

    public function customPathPlacesTestsAndConfigUnderSubdirectory(): void
    {
        $this->sandbox->makeDir('src');
        $this->sandbox->writeFile(
            'app/composer.json',
            \json_encode(['name' => 'acme/sub-app'], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n",
        );
        $rootComposer = \json_encode(['name' => 'acme/root'], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n";
        $this->sandbox->writeFile('composer.json', $rootComposer);

        $tester = $this->run(['--path' => 'app']);

        Assert::same(
            $tester->getStatusCode(),
            Command::SUCCESS,
            'init must succeed when --path points at a subdirectory; output: ' . $tester->getDisplay(),
        );

        Assert::true(\is_dir($this->sandbox->path('app/tests/Unit')), 'tests/Unit/ must be created under --path');
        Assert::true(\is_file($this->sandbox->path('app/testo.php')), 'testo.php must be created under --path');

        Assert::false(
            \is_file($this->sandbox->path('testo.php')),
            'no testo.php must be created at the project root when --path is set',
        );
        Assert::false(
            \is_dir($this->sandbox->path('tests')),
            'no tests/ must be created at the project root when --path is set',
        );

        $subScripts = $this->readComposerScripts('app/composer.json');
        Assert::same(
            $subScripts['test:unit'] ?? null,
            'vendor/bin/testo --suite=Unit',
            'composer scripts must be written to the composer.json colocated with --path',
        );

        Assert::same(
            \file_get_contents($this->sandbox->path('composer.json')),
            $rootComposer,
            'the project-root composer.json must be left untouched when --path is set',
        );
    }

    public function skipsComposerJsonWhenAbsentUnderCustomPath(): void
    {
        $this->sandbox->makeDir('src');
        // composer.json only at project root — irrelevant for --path=app
        $rootComposer = \json_encode(['name' => 'acme/root'], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n";
        $this->sandbox->writeFile('composer.json', $rootComposer);

        $tester = $this->run(['--path' => 'app']);

        Assert::same(
            $tester->getStatusCode(),
            Command::SUCCESS,
            'init must succeed when no composer.json exists under --path; output: ' . $tester->getDisplay(),
        );
        Assert::false(
            \is_file($this->sandbox->path('app/composer.json')),
            'init must not synthesize composer.json under --path',
        );
        Assert::same(
            \file_get_contents($this->sandbox->path('composer.json')),
            $rootComposer,
            'project-root composer.json must be left untouched when --path is set elsewhere',
        );
    }

    public function succeedsWithoutComposerJson(): void
    {
        $this->sandbox->makeDir('src');

        $tester = $this->run();

        Assert::same(
            $tester->getStatusCode(),
            Command::SUCCESS,
            'init must succeed when composer.json is absent; output: ' . $tester->getDisplay(),
        );
        Assert::true(\is_dir($this->sandbox->path('tests/Unit')), 'tests/Unit/ must still be created');
        Assert::true(\is_file($this->sandbox->path('testo.php')), 'testo.php must still be created');
        Assert::false(
            \is_file($this->sandbox->path('composer.json')),
            'init must not synthesize composer.json when none exists',
        );
    }

    public function reRunPicksUpNewlyAddedSuiteWithoutTouchingOldOnes(): void
    {
        $this->givenMinimalProject();
        $this->run();

        $this->sandbox->makeDir('tests/Integration');
        $tester = $this->run();

        Assert::same($tester->getStatusCode(), Command::SUCCESS, 'second init must succeed; output: ' . $tester->getDisplay());

        $scripts = $this->readComposerScripts();
        Assert::same(
            $scripts['test:unit'] ?? null,
            'vendor/bin/testo --suite=Unit',
            'first-run scripts must survive a second init',
        );
        Assert::same(
            $scripts['test:integration'] ?? null,
            'vendor/bin/testo --suite=Integration',
            'second init must register the newly-added Integration suite',
        );
    }

    public function leavesExistingUnitDirectoryIntact(): void
    {
        $this->givenMinimalProject();
        $this->sandbox->writeFile('tests/Unit/marker.txt', 'pre-existing');

        $tester = $this->run();

        Assert::same($tester->getStatusCode(), Command::SUCCESS, 'init must not fail when tests/Unit already exists');
        Assert::same(
            \file_get_contents($this->sandbox->path('tests/Unit/marker.txt')),
            'pre-existing',
            'files inside an existing tests/Unit must not be touched',
        );
    }

    /**
     * Verifies the global `--no-interaction` CLI flag (handled by the Application,
     * not by the Command itself) is honored end-to-end: even with an interactive
     * stream, the flag suppresses overwrite prompts.
     */
    public function noInteractionCliFlagSuppressesOverwritePrompt(): void
    {
        $this->givenMinimalProject();
        $existing = "<?php\n// user-edited config — must not be clobbered\nreturn 'sentinel';\n";
        $this->sandbox->writeFile('testo.php', $existing);

        $app = new Application();
        $app->setAutoExit(false);
        $app->add(new Init());

        $tester = new ApplicationTester($app);
        $exitCode = $tester->run(
            ['command' => 'init', '--path' => '.', '--no-interaction' => true],
            // Note: interactive stream — only --no-interaction should make the command bail out.
            ['interactive' => true, 'capture_stderr_separately' => false],
        );

        Assert::same(
            $exitCode,
            Command::SUCCESS,
            '--no-interaction must produce a clean SUCCESS exit; output: ' . $tester->getDisplay(),
        );
        Assert::same(
            \file_get_contents($this->sandbox->path('testo.php')),
            $existing,
            '--no-interaction must skip the overwrite prompt and leave testo.php untouched',
        );
    }

    /**
     * Guards against the generated stub being broken by special characters in the
     * user-supplied source path (apostrophes, backslashes) — the path must be
     * emitted as a safe PHP literal, not raw-interpolated into single quotes.
     */
    public function escapesSpecialCharactersInSourcePath(): void
    {
        $this->sandbox->makeDir("acme's lib");
        $this->sandbox->writeFile(
            'composer.json',
            \json_encode(['name' => 'acme/example'], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n",
        );

        $tester = new CommandTester(new Init());
        $tester->setInputs(["acme's lib"]);
        $tester->execute(
            ['--path' => '.'],
            ['interactive' => true, 'capture_stderr_separately' => false],
        );

        Assert::same(
            $tester->getStatusCode(),
            Command::SUCCESS,
            'init must succeed with an apostrophe in the src path; output: ' . $tester->getDisplay(),
        );

        $config = require $this->sandbox->path('testo.php');
        Assert::instanceOf(
            $config,
            ApplicationConfig::class,
            'generated testo.php must be valid PHP even with apostrophes in the src path',
        );
    }

    public function failsGracefullyOnMalformedComposerJson(): void
    {
        $this->sandbox->makeDir('src');
        $this->sandbox->writeFile('composer.json', "{ this is not valid json ");

        $tester = $this->run();

        Assert::same(
            $tester->getStatusCode(),
            Command::FAILURE,
            'init must fail when composer.json is malformed instead of writing garbage; output: ' . $tester->getDisplay(),
        );
        Assert::false(
            \is_file($this->sandbox->path('testo.php')),
            'no testo.php must be generated when composer.json parsing fails',
        );
    }

    public function generatedConfigIsValidPhpReturningApplicationConfig(): void
    {
        $this->givenMinimalProject();
        $this->sandbox->makeDir('tests/Integration');

        $this->run();

        $config = require $this->sandbox->path('testo.php');

        Assert::instanceOf(
            $config,
            ApplicationConfig::class,
            'generated testo.php must return an ApplicationConfig instance',
        );

        $suiteNames = \array_map(static fn($suite) => $suite->name, $config->suites);
        Assert::true(
            \in_array('Unit', $suiteNames, true),
            'generated config must declare the Unit suite; got: ' . \implode(', ', $suiteNames),
        );
        Assert::true(
            \in_array('Integration', $suiteNames, true),
            'generated config must declare every detected suite; got: ' . \implode(', ', $suiteNames),
        );
    }

    #[BeforeTest]
    public function setUp(): void
    {
        $this->sandbox = Sandbox::create();
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->sandbox->destroy();
    }

    /**
     * Run the Init command against the current sandbox in non-interactive mode.
     *
     * Pass CLI flags as the standard CommandTester input array, e.g.
     * `['--path' => 'app']`. The default puts everything at the sandbox root.
     *
     * @param array<string, string|bool|null> $input
     */
    private function run(array $input = ['--path' => '.']): CommandTester
    {
        $tester = new CommandTester(new Init());
        $tester->execute(
            $input,
            ['interactive' => false, 'capture_stderr_separately' => false],
        );

        return $tester;
    }

    /**
     * Materialize the smallest project layout that lets `init` succeed:
     * a `src/` directory and a `composer.json` (with optional extra keys merged in).
     *
     * @param array<string, mixed> $composerExtras
     */
    private function givenMinimalProject(array $composerExtras = []): void
    {
        $this->sandbox->makeDir('src');
        $composer = \array_merge(['name' => 'acme/example'], $composerExtras);
        $this->sandbox->writeFile(
            'composer.json',
            \json_encode($composer, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    /**
     * @return array<string, string>
     */
    private function readComposerScripts(string $relative = 'composer.json'): array
    {
        $raw = (string) \file_get_contents($this->sandbox->path($relative));
        $decoded = \json_decode($raw, true);
        Assert::true(\is_array($decoded), $relative . ' must remain valid JSON; got: ' . $raw);

        /** @var array<string, string> $scripts */
        $scripts = $decoded['scripts'] ?? [];

        return $scripts;
    }
}
