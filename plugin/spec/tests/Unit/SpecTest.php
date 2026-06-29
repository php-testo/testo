<?php

declare(strict_types=1);

namespace Tests\Spec\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Spec;
use Testo\Test;

#[Test]
#[Covers(Spec::class)]
final class SpecTest
{
    public function storyIsStored(): void
    {
        $spec = new Spec(story: 'As a user I want X.');

        Assert::same($spec->story, 'As a user I want X.');
    }

    public function tagsDefaultToEmpty(): void
    {
        $spec = new Spec(story: 'story');

        Assert::same($spec->tags, []);
    }

    public function tagsArePreserved(): void
    {
        $spec = new Spec(story: 'story', tags: ['checkout', 'JIRA-1']);

        Assert::same($spec->tags, ['checkout', 'JIRA-1']);
    }

    public function emptyTagsAreFilteredOut(): void
    {
        $spec = new Spec(story: 'story', tags: ['', 'kept', '']);

        Assert::same($spec->tags, ['kept']);
    }

    /**
     * @param string $story Blank stories carry no specification and are rejected.
     */
    #[DataSet([''], 'empty')]
    #[DataSet(['   '], 'whitespace')]
    #[DataSet(["\n\t "], 'newlines')]
    public function blankStoryFails(string $story): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Spec story must not be empty.');

        new Spec(story: $story);
    }
}
