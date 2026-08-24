<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;
use Tests\Assert\Stub\CustomFieldException;

/**
 * Characterization of how {@see Expect::exception()} treats a specimen's custom fields TODAY
 * (issue #265). These tests document current behavior, not desired behavior — several of them are
 * green precisely because the custom field is *not* checked.
 *
 * @see Expect::exception()
 */
#[Test]
#[Covers(Expect::class, 'exception')]
final class CustomFieldSpecimenTest
{
    /**
     * A specimen with a matching custom field passes — but only because message/class match;
     * the field itself is never consulted.
     */
    public function specimenWithMatchingCustomField(): never
    {
        Expect::exception(new CustomFieldException('boom', 'the cause'));

        throw new CustomFieldException('boom', 'the cause');
    }

    /**
     * THE ISSUE: a specimen whose custom `details` field differs from the thrown one still passes.
     * The mismatch is silently ignored — this test is green today, which is the bug.
     */
    public function specimenWithMismatchedCustomFieldStillPasses(): never
    {
        Expect::exception(new CustomFieldException('boom', 'EXPECTED details'));

        throw new CustomFieldException('boom', 'TOTALLY DIFFERENT details');
    }

    /**
     * Same gap for a non-public field exposed via a getter: the specimen's `severity` is not compared.
     */
    public function specimenWithMismatchedNonPublicFieldStillPasses(): never
    {
        Expect::exception(new CustomFieldException('boom', severity: 5));

        throw new CustomFieldException('boom', severity: 99);
    }
}
