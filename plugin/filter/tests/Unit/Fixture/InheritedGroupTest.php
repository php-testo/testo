<?php

declare(strict_types=1);

namespace Tests\Filter\Unit\Fixture;

use Testo\Filter\Group;
use Testo\Test;

/**
 * Trait contributing a group to the method it provides.
 */
trait TaggedTrait
{
    #[Group('trait-group')]
    public function fromTrait(): void {}
}

/**
 * Abstract parent: class-level group plus a method that the child overrides.
 * Not a test case itself (abstract classes are skipped by the locator).
 */
#[Group('base')]
abstract class BaseInheritedTest
{
    #[Group('proto')]
    public function inherited(): void {}
}

/**
 * Child test case combining every group source the interceptor merges:
 *
 *   inherited():  own (method) ∪ proto (prototype) ∪ child (class) ∪ base (parent class)
 *   fromTrait():  trait-group (trait) ∪ child (class) ∪ base (parent class)
 *   childOnly():  child (class) ∪ base (parent class)
 */
#[Test]
#[Group('child')]
final class InheritedGroupTest extends BaseInheritedTest
{
    use TaggedTrait;

    #[Group('own')]
    public function inherited(): void {}

    public function childOnly(): void {}
}
