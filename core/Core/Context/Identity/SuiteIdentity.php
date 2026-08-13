<?php

declare(strict_types=1);

namespace Testo\Core\Context\Identity;

use Internal\Path;
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
     * @param non-empty-string|null $caseName Class FQN, or null for a case of free functions.
     *        {@see CaseIdentity::$case}.
     * @param non-empty-string $type Case type — `test`, `inline`, `bench`, …
     *        {@see \Testo\Core\Value\TestType}. Part of the address because one file can define cases
     *        of several types.
     * @param Path $file Path of the file the case was read from.
     */
    public function toCase(?string $caseName, string $type, Path $file): CaseIdentity
    {
        return new CaseIdentity($this->suite, $caseName, $type, $file, parentId: $this->runtimeId);
    }

    /**
     * A suite is a configuration entry, not code, so there is no narrower form to give: its name is
     * both what it is called and what `--suite` matches.
     */
    #[\Override]
    public function fqn(): string
    {
        return $this->suite;
    }
}
