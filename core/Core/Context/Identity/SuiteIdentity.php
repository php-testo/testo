<?php

declare(strict_types=1);

namespace Testo\Core\Context\Identity;

use Testo\Core\Context\Identity;

/**
 * Address of a test suite — where every other {@see Identity} starts.
 *
 * @api
 */
final readonly class SuiteIdentity extends Identity
{
    /**
     * @param non-empty-string $suite Suite name as configured in `testo.php`.
     */
    public function __construct(
        public string $suite,
    ) {
        parent::__construct();
    }

    /**
     * Step down to a case of this suite.
     *
     * @param non-empty-string $caseName Class FQN, or the file for a function-based case.
     * @param non-empty-string $type Case type — `test`, `inline`, `bench`, …
     *        {@see \Testo\Core\Value\TestType}. Part of the address because one file can define cases
     *        of several types.
     */
    public function toCase(string $caseName, string $type): CaseIdentity
    {
        return new CaseIdentity($this->suite, $caseName, $type);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->suite;
    }
}
