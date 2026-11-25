<?php

/**
 * Multi-line expressions whose operands PHP may skip, adapted from the shipmonk-rnd/turbocov
 * test fixtures (MIT).
 *
 * Kept in the global namespace: unqualified `strlen()`, `assert()` and the like compile to special
 * opcodes only here, and those opcodes are part of what the drivers disagree on.
 */

function probe_or_chain($a, $b, $c) {
    if ($a
        || $b
        || $c
    ) {
        return 1;
    }
    return 0;
}
function probe_and_return($a, $b) {
    return $a
        && $b;
}
function probe_ternary($a) {
    return $a
        ? 'then'
        : 'else';
}
function probe_coalesce($a) {
    return $a
        ?? strtoupper('b');
}
function probe_short_ternary_chain($a, $b) {
    return $a
        ?: $b
        ?: 'c';
}
function probe_match_arms($a) {
    return match ($a) {
        1 => 'one',
        2 => 'two',
        default => 'other',
    };
}
function probe_nullsafe($a) {
    return $a
        ?->getIterator()
        ?->count();
}
function probe_while_condition($a) {
    $i = 0;
    while ($a
        && $i < 1
    ) {
        $i++;
    }
    return $i;
}
function probe_array_item($a) {
    return [
        'x' => $a
            ?? 'none',
        'y' => 2,
    ];
}
function probe_method_argument($a) {
    return (new ArrayObject([]))->offsetExists(
        $a
            ?? 'none'
    );
}
function probe_call_in_condition($a) {
    if (trim(
        $a
            ?? 'none'
    )) {
        return 1;
    }
    return 0;
}
function probe_special_function($a) {
    return strlen($a
        ?? 'none');
}
function probe_concat($a) {
    return 'x' . ($a
        ? 'then'
        : 'else');
}
function probe_nested($a, $b) {
    return match (true) {
        $a => $b
            ?? 'none',
        default => 'other',
    };
}
function probe_list_assign($a) {
    [$x, $y] = $a
        ? [1, 2]
        : [3, 4];
    return $x + $y;
}
function probe_and_in_if($a, $b, $c) {
    if ($a
        && $b
        && $c
    ) {
        return 1;
    }
    return 0;
}
function probe_else_if($a, $b, $c) {
    if ($a === null) {
        return 0;
    } elseif ($a
        || $b
        || $c
    ) {
        return 1;
    }
    return 2;
}
function probe_throwing_arm($a) {
    return match ($a) {
        1 => 'one',
        default => throw new LogicException('none'),
    };
}
enum ProbeSuit {
    case Hearts;
    case Spades;
}
function probe_enum_arms(ProbeSuit $a) {
    return match ($a) {
        ProbeSuit::Hearts => 'red',
        ProbeSuit::Spades => 'black',
    };
}
function probe_spread_match($a, $b) {
    return [
        ...$a,
        ...match ($b) {
            1 => ['one'],
            2 => ['two'],
        },
    ];
}
function probe_fallback_throw($a) {
    return $a
        ?: throw new LogicException('none');
}
function probe_multi_line_else($a, $b) {
    return $a
        ? [1]
        : [
            $b,
            3,
        ];
}
function probe_inner_ternary($a, $b) {
    return strtoupper($a ?: (
        $b
            ? 'b'
            : 'c'
    ));
}

function probe_break_after($a) {
    foreach ([1, 2] as $v) {
        $x = $a ? 1
            : 2; break;
    }
    return $x;
}
function probe_operand_after($a, $b) {
    return ($a
        ?? 0) > $b;
}
function probe_variable_argument($a, $b) {
    return max($a
        ?? 0, $b);
}
function probe_constant_argument($a) {
    return (new ArrayObject([]))->offsetSet($a
        ?? 0, 5);
}
function probe_statement_after($a) {
    $x = $a
        ?: 0; $y = 1;
    return $x + $y;
}
function probe_taken($a, $b) {
    return $a
        || $b;
}
function probe_equal_false_after($a, $b) {
    return ($a
        || $b) == false;
}
function probe_equal_after($a, $b) {
    return ($a
        || $b) == true;
}
function probe_identical_after($a, $b, $c) {
    return ($a
        || $b) === $c;
}
function probe_multiply_after($a, $b) {
    return ($a
        ?? 1) * $b;
}
function probe_plus_after($a, $b) {
    return ($a
        ?: 1) + $b;
}
function probe_concat_after($a, $b) {
    return ($a
        ?? 'x') . $b;
}
function probe_call_after($a) {
    return strlen($a
        ?? 'x') + strlen('yy');
}
function probe_next_argument($a, $b, $c) {
    return (new ArrayObject([]))->offsetSet($a
        ?? $b, $c);
}
function probe_assignment_line($a) {
    $x =
        $a ?? 'none';
    return $x;
}
function probe_negation($a, $b) {
    return !($a
        || $b);
}
function probe_bool_cast($a, $b) {
    return (bool) ($a
        ?? $b);
}
