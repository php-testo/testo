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
     * Rendered form of this address, composed once. {@see __toString()}
     *
     * @var non-empty-string
     */
    private string $display;

    /**
     * @param non-empty-string $suite Suite name as configured in `testo.php`.
     * @param non-empty-string|null $case Class FQN. Null for a case of free functions, which has no
     *        class — {@see $file} is what names such a case instead.
     * @param non-empty-string $type Case type — `test`, `inline`, `bench`, …
     *        {@see \Testo\Core\Value\TestType}.
     * @param non-empty-string|null $file Path of the file the case was read from. Null only for a case
     *        built by hand rather than located. Carried even when there is a class — a class does name
     *        its own file, but resolving that means loading the class, and TeamCity wants both parts.
     */
    public function __construct(
        public string $suite,
        public ?string $case,
        public string $type,
        public ?string $file = null,
    ) {
        # A case of free functions is named by its file; with neither there is nothing to put here.
        $node = $case ?? $file;
        $this->display = $node === null
            ? "{$suite} [{$type}]"
            : "{$suite} / {$node} [{$type}]";

        parent::__construct();
    }

    /**
     * Step down to a test of this case.
     *
     * @param non-empty-string $testName Method or function name.
     * @param non-empty-string|null $namespace Namespace of a free test function.
     *        {@see TestIdentity::$namespace}.
     */
    public function toTestIdentity(string $testName, ?string $namespace = null): TestIdentity
    {
        return new TestIdentity($this->suite, $this->case, $this->type, $this->file, $testName, $namespace);
    }

    #[\Override]
    public function fqn(): ?string
    {
        return $this->case;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->display;
    }
}
