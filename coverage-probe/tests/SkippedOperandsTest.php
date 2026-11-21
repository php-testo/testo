<?php

declare(strict_types=1);

namespace Tests\CoverageProbe;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\CoverageProbe\SkippedOperands;
use Testo\Test;

require_once \dirname(__DIR__) . '/src/skipped-operands.php';
require_once \dirname(__DIR__) . '/src/skipped-operands-namespace.php';

/**
 * Each function runs with one set of arguments only: a second call would execute the operands the
 * first one skipped and hide the difference between the drivers.
 */
#[Test]
final class SkippedOperandsTest
{
    #[Covers('probe_or_chain')]
    public function orChain(): void
    {
        Assert::same(\probe_or_chain(true, false, false), 1);
    }

    #[Covers('probe_and_return')]
    public function andReturn(): void
    {
        Assert::false(\probe_and_return(false, true));
    }

    #[Covers('probe_ternary')]
    public function ternary(): void
    {
        Assert::same(\probe_ternary(true), 'then');
    }

    #[Covers('probe_coalesce')]
    public function coalesce(): void
    {
        Assert::same(\probe_coalesce('a'), 'a');
    }

    #[Covers('probe_short_ternary_chain')]
    public function shortTernaryChain(): void
    {
        Assert::same(\probe_short_ternary_chain('a', 'b'), 'a');
    }

    #[Covers('probe_match_arms')]
    public function matchArms(): void
    {
        Assert::same(\probe_match_arms(1), 'one');
    }

    #[Covers('probe_nullsafe')]
    public function nullsafe(): void
    {
        Assert::null(\probe_nullsafe(null));
    }

    #[Covers('probe_while_condition')]
    public function whileCondition(): void
    {
        Assert::same(\probe_while_condition(false), 0);
    }

    #[Covers('probe_array_item')]
    public function arrayItem(): void
    {
        Assert::same(\probe_array_item('a'), ['x' => 'a', 'y' => 2]);
    }

    #[Covers('probe_method_argument')]
    public function methodArgument(): void
    {
        Assert::false(\probe_method_argument('a'));
    }

    #[Covers('probe_call_in_condition')]
    public function callInCondition(): void
    {
        Assert::same(\probe_call_in_condition('a'), 1);
    }

    #[Covers('probe_special_function')]
    public function specialFunction(): void
    {
        Assert::same(\probe_special_function('a'), 1);
    }

    #[Covers('probe_concat')]
    public function concat(): void
    {
        Assert::same(\probe_concat(true), 'xthen');
    }

    #[Covers('probe_nested')]
    public function nested(): void
    {
        Assert::same(\probe_nested(true, 'b'), 'b');
    }

    #[Covers('probe_list_assign')]
    public function listAssign(): void
    {
        Assert::same(\probe_list_assign(true), 3);
    }

    #[Covers('probe_and_in_if')]
    public function andInIf(): void
    {
        Assert::same(\probe_and_in_if(false, true, true), 0);
    }

    #[Covers('probe_else_if')]
    public function elseIf(): void
    {
        Assert::same(\probe_else_if(true, false, false), 1);
    }

    #[Covers('probe_throwing_arm')]
    public function throwingArm(): void
    {
        Assert::same(\probe_throwing_arm(1), 'one');
    }

    #[Covers('probe_enum_arms')]
    public function enumArms(): void
    {
        Assert::same(\probe_enum_arms(\ProbeSuit::Hearts), 'red');
    }

    #[Covers('probe_spread_match')]
    public function spreadMatch(): void
    {
        Assert::same(\probe_spread_match([0], 1), [0, 'one']);
    }

    #[Covers('Testo\CoverageProbe\SkippedOperands\assertion')]
    public function namespacedAssertion(): void
    {
        Assert::same(SkippedOperands\assertion(true, false), 1);
    }

    #[Covers('Testo\CoverageProbe\SkippedOperands\function_call')]
    public function namespacedFunctionCall(): void
    {
        Assert::same(SkippedOperands\function_call('a'), 1);
    }

    #[Covers('probe_fallback_throw')]
    public function fallbackThrow(): void
    {
        Assert::same(\probe_fallback_throw('a'), 'a');
    }

    #[Covers('probe_multi_line_else')]
    public function multiLineElse(): void
    {
        Assert::same(\probe_multi_line_else(true, 2), [1]);
    }

    #[Covers('probe_inner_ternary')]
    public function innerTernary(): void
    {
        Assert::same(\probe_inner_ternary('a', true), 'A');
    }

    #[Covers('probe_break_after')]
    public function breakAfter(): void
    {
        Assert::same(\probe_break_after(true), 1);
    }

    #[Covers('probe_operand_after')]
    public function operandAfter(): void
    {
        Assert::false(\probe_operand_after(1, 2));
    }

    #[Covers('probe_variable_argument')]
    public function variableArgument(): void
    {
        Assert::same(\probe_variable_argument(1, 2), 2);
    }

    #[Covers('probe_constant_argument')]
    public function constantArgument(): void
    {
        Assert::null(\probe_constant_argument(1));
    }

    #[Covers('probe_statement_after')]
    public function statementAfter(): void
    {
        Assert::same(\probe_statement_after(1), 2);
    }

    #[Covers('probe_taken')]
    public function taken(): void
    {
        Assert::true(\probe_taken(false, true));
    }

    #[Covers('probe_equal_after')]
    public function equalAfter(): void
    {
        Assert::true(\probe_equal_after(true, false));
    }

    #[Covers('probe_equal_false_after')]
    public function equalFalseAfter(): void
    {
        Assert::false(\probe_equal_false_after(true, false));
    }

    #[Covers('probe_identical_after')]
    public function identicalAfter(): void
    {
        Assert::true(\probe_identical_after(true, false, true));
    }

    #[Covers('probe_multiply_after')]
    public function multiplyAfter(): void
    {
        Assert::same(\probe_multiply_after(2, 3), 6);
    }

    #[Covers('probe_plus_after')]
    public function plusAfter(): void
    {
        Assert::same(\probe_plus_after(5, 1), 6);
    }

    #[Covers('probe_concat_after')]
    public function concatAfter(): void
    {
        Assert::same(\probe_concat_after('a', 'b'), 'ab');
    }

    #[Covers('probe_call_after')]
    public function callAfter(): void
    {
        Assert::same(\probe_call_after('a'), 3);
    }

    #[Covers('probe_next_argument')]
    public function nextArgument(): void
    {
        Assert::null(\probe_next_argument(1, 2, 3));
    }

    #[Covers('probe_assignment_line')]
    public function assignmentLine(): void
    {
        Assert::same(\probe_assignment_line('a'), 'a');
    }

    #[Covers('Testo\CoverageProbe\SkippedOperands\next_argument')]
    public function namespacedNextArgument(): void
    {
        Assert::same(SkippedOperands\next_argument(1, 2, 3), 3);
    }

    #[Covers('probe_negation')]
    public function negation(): void
    {
        Assert::false(\probe_negation(true, false));
    }

    #[Covers('probe_bool_cast')]
    public function boolCast(): void
    {
        Assert::true(\probe_bool_cast('a', 'b'));
    }
}
