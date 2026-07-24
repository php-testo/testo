<?php

declare(strict_types=1);

namespace Testo\Core\Context;

use Testo\Core\Definition\TestDefinition;
use Testo\Core\Internal\Attributed;

/**
 * Information about run test.
 *
 * @api
 */
final readonly class TestInfo
{
    use Attributed;

    /**
     * Identity of this running test, used to tell tests apart when their events and output interleave.
     */
    public TestIdentity $identity;

    /**
     * @param non-empty-string $name
     * @param array<array-key, mixed> $arguments Arguments to pass to the test method.
     * @param array<non-empty-string, mixed> $attributes
     * @param TestIdentity|null $identity Identity to carry; a fresh random one is minted when omitted.
     */
    public function __construct(
        public string $name,
        public CaseInfo $caseInfo,
        public TestDefinition $testDefinition,
        public array $arguments = [],
        public array $attributes = [],
        ?TestIdentity $identity = null,
    ) {
        $this->identity = $identity ?? TestIdentity::generate();
    }

    public function with(
        ?array $arguments = null,
    ): self {
        return new self(
            name: $this->name,
            caseInfo: $this->caseInfo,
            testDefinition: $this->testDefinition,
            arguments: $arguments ?? $this->arguments,
            attributes: $this->attributes,
            identity: $this->identity,
        );
    }
}
