<?php

declare(strict_types=1);

namespace Testo;

use Internal\Container\Container;
use Testo\Common\Environment;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Core\Value\Status;
use Testo\Event\Framework\SessionFinished;
use Testo\Event\Test\TestPipelineFinished;

final class FlakyPRCommentPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        // Check that we're in GitHub Actions and this is a PR
        $token = \getenv('GITHUB_TOKEN');
        $repo = \getenv('GITHUB_REPOSITORY'); // owner/repo
        $ref = (string) \getenv('GITHUB_REF'); // refs/pull/123/merge
        if (!$token || !$repo || !\preg_match('#^refs/pull/(\d+)/#', $ref, $m)) {
            return; // not in CI or not a PR — do nothing
        }

        $prNumber = $m[1];

        $listeners = $container->get(EventListenerCollector::class);
        $listeners->addListener(TestPipelineFinished::class, $this->onTestFinished(...));
        $listeners->addListener(
            SessionFinished::class,
            fn(SessionFinished $e) => $this->postComment($token, $repo, $prNumber),
        );
    }

    /** @var list<string> */
    private array $flakyTests = [];

    private function onTestFinished(TestPipelineFinished $event): void
    {
        if ($event->testResult->status !== Status::Flaky) {
            return;
        }

        $case = $event->testInfo->caseInfo->definition->reflection?->getShortName();
        $test = $event->testInfo->testDefinition->reflection->getName();
        $this->flakyTests[] = $case === null ? "{$test}()" : "{$case}::{$test}()";
    }

    private function postComment(string $token, string $repo, string $prNumber): void
    {
        if ($this->flakyTests === []) {
            return;
        }

        $list = \implode("\n", \array_map(
            static fn(string $name) => "- `{$name}`",
            $this->flakyTests,
        ));

        $env = \sprintf(
            "**Environment:** PHP %s (%s, %s, %s)%s%s%s",
            Environment::getPhpVersion(),
            Environment::getThread(),
            Environment::getOs(),
            Environment::getCpu(),
            Environment::hasXDebug()
                ? \sprintf(', Xdebug %s [%s]', Environment::getXDebugVersion(), \implode(', ', Environment::getXDebugMode()))
                : '',
            Environment::isOpCacheEnabled() ? ', OPcache' : '',
            Environment::isJitEnabled() ? ', JIT' : '',
        );

        @\file_get_contents(
            "https://api.github.com/repos/{$repo}/issues/{$prNumber}/comments",
            context: \stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => \implode("\r\n", [
                        "Authorization: Bearer {$token}",
                        'Content-Type: application/json',
                        'User-Agent: Testo',
                    ]),
                    'content' => \json_encode([
                        'body' => "⚠️ **Flaky tests detected**\n\n{$list}\n\n{$env}",
                    ]),
                ],
            ]),
        );
    }
}
