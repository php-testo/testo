<?php

declare(strict_types=1);

namespace Tests\Spec\Feature;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Value\Status;
use Testo\Spec;
use Testo\Spec\Internal\SpecInterceptor;
use Testo\Spec\SpecHeader;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Spec\Stub\SpecStub;

/**
 * These feature tests are themselves written Spec-Driven: each one carries the `#[Spec]` story it
 * proves and a `#[SpecHeader]` item title, under a class-level section — dogfooding the plugin.
 */
#[Test]
#[Covers(Spec::class)]
#[Covers(SpecHeader::class)]
#[Covers(SpecInterceptor::class)]
#[SpecHeader('1', 'Attaching specs to tests')]
#[TestingSuite(path: __DIR__ . '/../Stub')]
final class SpecFeatureTest
{
    #[Spec(story: <<<'MD'
        Every `#[Spec]` fragment a test carries is published to the `spec.md` messenger channel, so it
        travels with that test's output — even when the plugin itself is not registered.
        MD)]
    #[SpecHeader(title: 'Fragments reach the channel')]
    public function fragmentIsPublishedToTheChannel(): void
    {
        $result = TestRunner::runTest([SpecStub::class, 'methodLevelSpec']);

        $messages = $result->messages->channel(SpecInterceptor::CHANNEL);
        Assert::same(\count($messages), 1);
        Assert::true(\str_contains($messages[0]->content, 'As a user I want X so that Y.'));
    }

    #[Spec(story: <<<'MD'
        A class-level `#[SpecHeader]` names and numbers the section that every test in the case belongs
        to; the section title and number ride along with each fragment.
        MD)]
    #[SpecHeader(title: 'A class header opens a section')]
    public function classLevelSectionHeaderReachesTheContext(): void
    {
        $result = TestRunner::runTest([SpecStub::class, 'methodLevelSpec']);

        $context = $result->messages->channel(SpecInterceptor::CHANNEL)[0]->context;
        Assert::same($context['sectionTitle'], 'Checkout');
        Assert::same($context['sectionNumber'], '5');
        Assert::null($context['title']);
        Assert::same($context['test'], 'methodLevelSpec');
    }

    #[Spec(story: <<<'MD'
        A method-level `#[SpecHeader]` overrides the item heading, and tags declared on `#[Spec]` are
        carried through to the fragment.
        MD)]
    #[SpecHeader(title: 'A method header titles an item')]
    public function methodLevelItemHeaderReachesTheContext(): void
    {
        $result = TestRunner::runTest([SpecStub::class, 'specWithHeader']);

        $context = $result->messages->channel(SpecInterceptor::CHANNEL)[0]->context;
        Assert::same($context['title'], 'Tax in total');
        Assert::null($context['number']);
        Assert::same($context['tags'], ['checkout']);
    }

    #[Spec(story: 'Documenting a test with `#[Spec]` is observational: it never changes the pass/fail outcome.')]
    #[SpecHeader(title: 'Specs never change the verdict')]
    public function specDoesNotAlterTestOutcome(): void
    {
        $result = TestRunner::runTest([SpecStub::class, 'methodLevelSpec']);

        Assert::same($result->status, Status::Passed);
    }
}
