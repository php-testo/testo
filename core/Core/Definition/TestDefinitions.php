<?php

declare(strict_types=1);

namespace Testo\Core\Definition;

/**
 * Collection of test definitions located in a Test Case
 *
 * @api
 */
final class TestDefinitions
{
    /**
     * Function definitions with its name as key.
     * @var array<non-empty-string, TestDefinition>
     */
    private array $tests = [];

    /**
     * Create TestDefinitions from an array of TestDefinition.
     *
     * @note You must use named non-empty-string arguments to preserve keys.
     */
    public static function fromArray(TestDefinition ...$values): self
    {
        $self = new self();
        $self->tests = $values;
        return $self;
    }

    public function define(\ReflectionFunctionAbstract $reflection): TestDefinition
    {
        return $this->tests[$reflection->getShortName()] = new TestDefinition($reflection);
    }

    /**
     * Remove a previously defined test by its short name.
     */
    public function undefine(string $name): void
    {
        unset($this->tests[$name]);
    }

    /**
     * Get all located tests.
     *
     * @return array<non-empty-string, TestDefinition>
     */
    public function getTests(): array
    {
        return $this->tests;
    }
}
