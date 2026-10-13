<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Stub;

// A grouped import carries `function` tokens that declare nothing.
use Tests\Tokenizer\Stub\Imported\{function first, function second};

// Keywords used as constant, case, and alias names declare nothing either.
enum KeywordCases
{
    case function;
}

trait KeywordTrait
{
    public function run(): void {}
}

final class KeywordAlias
{
    use KeywordTrait {
        run as function;
    }

    public const function = 1;
}

// Method names may be semi-reserved keywords or contain non-ASCII letters. A keyword name token is
// not T_STRING, so the locator must check the name by its spelling rather than by token type.
final class KeywordNames
{
    public function größe(): void {}

    public function list(): array
    {
        return [];
    }

    public function print(): void {}

    public function unset(): void {}

    public function function(): void {}

    public function & reference(): array
    {
        static $items = [];
        return $items;
    }

    public function /* name */ commented /* parameters */ (): void {}

    public function call(): void
    {
        // Keywords used as argument names and member names declare nothing.
        $this->run(function: 1, class: 2);
        if (true) {
            KeywordCases::function;
        }
        if (true) {
            self::function();
            $this->function();
        }
        $this->run(function: (1));
        $same = KeywordAlias::function === (1);
        $both = KeywordAlias::function and ($same);
        $mask = KeywordAlias::function & intdiv(4, 2);
        $spaced = KeywordAlias:: /* constant */ function and ($same);
    }

    public function closure(): \Closure
    {
        return function /* anonymous */ () {};
    }
}
