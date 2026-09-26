<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * Recognises a PHPUnit assertion call in any of its spellings: `$this->assertX()`, `self::assertX()`,
 * `static::assertX()`, and the global function `PHPUnit\Framework\assertX()`.
 *
 * Only a call inside a class counts, which is where a test method or a `static` data provider
 * lives; a call in a free function or at namespace level is left alone.
 *
 * @internal
 */
final class PhpunitAssertionCall
{
    public const NODE_TYPES = [MethodCall::class, StaticCall::class, FuncCall::class];
    private const FUNCTION_NAMESPACE = 'PHPUnit\\Framework\\';

    public function __construct(
        private readonly NodeNameResolver $nodeNameResolver,
    ) {}

    /**
     * @return non-empty-string|null The assertion's method name, e.g. `assertSame`.
     */
    public function name(Node $node): ?string
    {
        $name = match (true) {
            $node instanceof MethodCall => $this->nodeNameResolver->isName($node->var, 'this')
                ? $this->nodeNameResolver->getName($node->name)
                : null,
            $node instanceof StaticCall => $this->nodeNameResolver->isNames($node->class, ['self', 'static'])
                ? $this->nodeNameResolver->getName($node->name)
                : null,
            $node instanceof FuncCall => $this->functionName($node),
            default => null,
        };

        if ($name === null || $name === '' || !$this->isInClassScope($node)) {
            return null;
        }

        return $name;
    }

    private function functionName(FuncCall $call): ?string
    {
        $name = $this->nodeNameResolver->getName($call);
        if ($name === null || !\str_starts_with(\ltrim($name, '\\'), self::FUNCTION_NAMESPACE)) {
            return null;
        }

        return \substr(\ltrim($name, '\\'), \strlen(self::FUNCTION_NAMESPACE));
    }

    private function isInClassScope(Node $node): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->isInClass();
    }
}
