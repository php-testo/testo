<?php

declare(strict_types=1);

namespace Testo\Core\Context;

use Testo\Core\Context\Identity\TestIdentity;
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
     * Address of this test — or of the data set of it that is running. Tells tests apart when their
     * events and output interleave, and names them the same way from one run to the next.
     */
    public TestIdentity $identity;

    /**
     * @param non-empty-string $name
     * @param array<array-key, mixed> $arguments Arguments to pass to the test method.
     * @param array<non-empty-string, mixed> $attributes
     * @param TestIdentity|null $identity Address to carry; derived from the case when omitted. Pass one
     *        to keep an address across a derived info — {@see with()} does, and a data-set address is
     *        made with {@see TestIdentity::with()}.
     */
    public function __construct(
        public string $name,
        public CaseInfo $caseInfo,
        public TestDefinition $testDefinition,
        public array $arguments = [],
        public array $attributes = [],
        ?TestIdentity $identity = null,
    ) {
        $this->identity = $identity ?? $caseInfo->identity->toTestIdentity($name);
    }

    /**
     * @param TestIdentity|null $identity Address for the derived info; keeps this one's when omitted.
     *        A data-set runner passes {@see TestIdentity::with()} here.
     */
    public function with(
        ?array $arguments = null,
        ?TestIdentity $identity = null,
    ): self {
        return new self(
            name: $this->name,
            caseInfo: $this->caseInfo,
            testDefinition: $this->testDefinition,
            arguments: $arguments ?? $this->arguments,
            attributes: $this->attributes,
            identity: $identity ?? $this->identity,
        );
    }
}
