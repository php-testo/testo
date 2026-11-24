<?php
namespace App;

use Lib\{function helper};

final class Operands
{
    private const A = 'a';
    private const B = 'b';

    public function __construct(
        private string $name,
    ) {}

    public function glued(): string
    {
        return 'a' . "\n"
            . 'b' . "\n"
            . 'c';
    }

    public function gluedVariable(string $x): string
    {
        $sql = 'SELECT * '
            . 'FROM t '
            . 'WHERE x = ' . $x;
        return $sql;
    }

    public function condition(int $a, int $b): int
    {
        if (
            $a > 0
            && $b > 0
        ) {
            return 1;
        }
        return 0;
    }

    public function format(string $a, string $b): string
    {
        return \sprintf(
            '%s %s',
            $a,
            $b,
        );
    }

    public function arguments(object $o, string $x): void
    {
        $o->run(
            'literal',
            self::A,
            $x,
            name: true,
        );
        $this->own(
            'literal',
            $x,
        );
        \in_array(
            $x,
            [self::A, self::B],
            true,
        );
    }

    private function own(string $a, string $b): void {}

    public function ternary(bool $flag): string
    {
        $value = $flag
            ? 'yes'
            : 'no';
        return $value;
    }

    public function cases(string $rule): int
    {
        switch ($rule) {
            case self::A:
                return 1;
            case self::B:
                return 2;
        }
        return 0;
    }

    public function exhaustive(int $x): int
    {
        switch ($x) {
            case 1:
                return 1;
            default:
                return 0;
        }
    }

    public function unreachable(?int $x): int
    {
        $y = $x ?? throw new \LogicException('none');
        return $y;
    }

    public function afterReturn(): \Generator
    {
        return;
        yield;
    }

    public function constantCondition(object $o): int
    {
        if (\PHP_INT_SIZE < 4
            || $o->has('x')) {
            return 1;
        }
        return 0;
    }

    public function endless(object $o): void
    {
        while (true) {
            $o->tick();
        }
    }

    public function elseIf(bool $a, bool $b): int
    {
        if ($a) {
            return 1;
        } else if ($b) {
            return 2;
        }
        return 3;
    }

    public function strings(string $t, string $a, string $b): string
    {
        $sql = "SELECT *
            FROM {$t}
            WHERE id = ?";
        $list = "$a,$b"
            . $t;
        return $sql . $list;
    }

    public function longTarget(string $value): void
    {
        $this->name
            = $value;
    }

    public function arrowDefault(): \Closure
    {
        return fn(
            int $x = 0,
        ) => $x;
    }

    public function versioned(bool $x): int
    {
        if (\PHP_VERSION_ID < 80400 && $x) {
            return \strlen('old');
        }
        return 0;
    }

    public function imported(): void
    {
        helper(
            'literal',
        );
    }

    public function revived(int $x): int
    {
        goto done;
        $x++;
        done:
        return $x;
    }

    public function breakInBlock(array $items): int
    {
        foreach ($items as $item) {
            if ($item) {
                break;
            }
        }
        return 0;
    }

    public function constantOperand(object $o): bool
    {
        $ok = \PHP_INT_SIZE < 4
            || $o->has('x');
        return $ok;
    }

    public function closures(): array
    {
        $arrow = fn() => $this->own(
            'literal',
            'other',
        );
        $closure = function () {
            $this->own(
                'literal',
                'other',
            );
        };
        return [$arrow, $closure];
    }

    public function constantLoop(): int
    {
        $n = 0;
        while (self::A === 'b') {
            $n++;
        }
        return $n;
    }

    public function operands(object $o, string $a, string $b): string
    {
        $x = $o->wrap(
            $a,
        ) . 'x';
        $y = !($a) . ($b);
        $z = $a . "v{$b}";
        $o->{$a} = 1;
        $w = $o
            ->prop;
        return $x . $y . $z . $w;
    }

    public function lateConstants(): array
    {
        return [
            static::A,
            $this::B,
        ];
    }

    public function versionChains(object $o): int
    {
        if (\PHP_INT_SIZE > 100) $o->a();
        if (\PHP_INT_SIZE > 100) {
            $o->b();
        } elseif ($o->c()) {
            $o->d();
        }
        if (\PHP_INT_SIZE > 100) {
            $o->e();
        } else if ($o->f()) {
            $o->g();
        }
        if (\PHP_INT_SIZE > 100) {
            $o->h();
        } else {
            $o->i();
        }
        if (\PHP_INT_SIZE > 100) {
            $o->j();
        } else $o->k();
        if (\PHP_INT_SIZE > 100):
            $o->l();
        endif;
        return 0;
    }
}
