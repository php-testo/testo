<?php

declare(strict_types=1);

namespace Testo\Core\Context\Identity;

use Testo\Core\Context\Identity;

/**
 * Address of a test case within its suite.
 *
 * @api
 */
final readonly class CaseIdentity extends Identity
{
    /**
     * @param non-empty-string $suite Suite name as configured in `testo.php`.
     * @param non-empty-string $case Class FQN, or the file for a function-based case.
     * @param non-empty-string $type Case type — `test`, `inline`, `bench`, …
     *        {@see \Testo\Core\Value\TestType}.
     */
    public function __construct(
        public string $suite,
        public string $case,
        public string $type,
    ) {
        parent::__construct();
    }

    /**
     * Step down to a test of this case.
     *
     * @param non-empty-string $testName Method or function name.
     */
    public function toTestIdentity(string $testName): TestIdentity
    {
        return new TestIdentity($this->suite, $this->case, $this->type, $testName);
    }

    #[\Override]
    public function __toString(): string
    {
        return "{$this->suite} / {$this->case} [{$this->type}]";
    }
}
