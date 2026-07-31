<?php

declare(strict_types=1);

namespace Testo\Core\Context\Identity;

use Internal\Path;
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
     * @param Path $file Path of the file the case was read from. Carried even when there is a class — a
     *        class does name its own file, but resolving that means loading the class, and TeamCity
     *        wants both parts.
     * @param int<1, max>|null $parentId Run of the suite this case opened inside; passed by
     *        {@see SuiteIdentity::toCase()}. {@see Identity::$parentId}
     */
    public function __construct(
        public string $suite,
        public ?string $case,
        public string $type,
        public Path $file,
        ?int $parentId = null,
    ) {
        # A case of free functions has no class to name it, so its file stands in.
        $node = $case ?? (string) $file;
        $this->display = "{$suite} / {$node} [{$type}]";

        parent::__construct($parentId);
    }

    /**
     * Step down to a test of this case.
     *
     * @param non-empty-string $testName Name relative to this case — a bare method name when the case
     *        has a class, the function's own FQN when it does not. {@see TestIdentity::$test}.
     */
    public function toTestIdentity(string $testName): TestIdentity
    {
        return new TestIdentity(
            $this->suite,
            $this->case,
            $this->type,
            $this->file,
            $testName,
            parentId: $this->runtimeId,
        );
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
