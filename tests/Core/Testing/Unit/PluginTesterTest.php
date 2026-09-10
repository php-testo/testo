<?php

declare(strict_types=1);

namespace Tests\Core\Testing\Unit;

use Testo\Assert;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Event\Framework\SessionFinished;
use Testo\Test;
use Testo\Testing\Helper\PluginTester;
use Testo\Testing\Internal\Collector\RecordingEventListenerCollector;
use Testo\Testing\Internal\Collector\RecordingInterceptorCollector;
use Testo\Testing\Internal\Mock\MockContainer;
use Tests\Core\Testing\Stub\Plugin\AnotherStubInterceptor;
use Tests\Core\Testing\Stub\Plugin\RecordingStubPlugin;
use Tests\Core\Testing\Stub\Plugin\StubInterceptor;
use Tests\Core\Testing\Stub\Plugin\StubService;
use Tests\Core\Testing\Stub\Plugin\UnusedStubInterceptor;

#[Test]
#[Covers(PluginTester::class)]
#[Covers(MockContainer::class)]
#[Covers(RecordingInterceptorCollector::class)]
#[Covers(RecordingEventListenerCollector::class)]
final class PluginTesterTest
{
    public function capturesInterceptorAddedAsInstance(): void
    {
        PluginTester::for(new RecordingStubPlugin())
            ->addsInterceptor(StubInterceptor::class);
    }

    public function capturesInterceptorAddedAsClassString(): void
    {
        PluginTester::for(new RecordingStubPlugin())
            ->addsInterceptor(AnotherStubInterceptor::class);
    }

    public function countsAllAddedInterceptors(): void
    {
        PluginTester::for(new RecordingStubPlugin())
            ->addsInterceptors(2);
    }

    public function capturesListenerByEvent(): void
    {
        PluginTester::for(new RecordingStubPlugin())
            ->addsListener(SessionFinished::class)
            ->addsListeners(1);
    }

    public function capturesBinding(): void
    {
        PluginTester::for(new RecordingStubPlugin())
            ->binds(StubService::class);
    }

    public function capturesServiceRegistration(): void
    {
        PluginTester::for(new RecordingStubPlugin())
            ->registers(StubService::class);
    }

    public function rawAccessorsExposeEveryRecord(): void
    {
        $tester = PluginTester::for(new RecordingStubPlugin());

        Assert::count($tester->interceptors(), 2);
        Assert::count($tester->listeners(), 1);
        Assert::count($tester->bindings(), 1);
        Assert::same($tester->listeners()[0]['event'], SessionFinished::class);
    }

    public function fluentChecksChainOnOneRun(): void
    {
        PluginTester::for(new RecordingStubPlugin())
            ->addsInterceptor(StubInterceptor::class)
            ->addsInterceptor(AnotherStubInterceptor::class)
            ->addsListener(SessionFinished::class)
            ->binds(StubService::class)
            ->registers(StubService::class);
    }

    public function exposesTheMockContainerForBindingChecks(): void
    {
        $container = PluginTester::for(new RecordingStubPlugin())->container();

        Assert::same($container->bindings[0]['id'], StubService::class);
    }

    public function missingInterceptorFailsTheAssertion(): void
    {
        // The failing check throws AND records a failed expectation on the active state; swap it out so the
        // deliberate failure neither pollutes this test's history nor fails it, then restore.
        $restore = StaticState::swap(null);
        $threw = false;
        try {
            PluginTester::for(new RecordingStubPlugin())
                ->addsInterceptor(UnusedStubInterceptor::class);
        } catch (AssertionException) {
            $threw = true;
        } finally {
            StaticState::swap($restore);
        }

        Assert::true($threw, 'addsInterceptor should fail when the interceptor was never added');
    }
}
