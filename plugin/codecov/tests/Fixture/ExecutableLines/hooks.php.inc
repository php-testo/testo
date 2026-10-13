<?php
final class HookedProperties
{
    public string $short {
        get => \strtoupper($this->short);
        set => \trim($value);
    }

    public string $block {
        get {
            return $this->block;
        }
        set(string $v) {
            $this->block = $v;
        }
    }

    public int $multiLine {
        get => $this->a
            + $this->b;
    }

    public int $withDefault = 3 {
        set(int $v) {
            $this->withDefault = $v * 2;
        }
    }

    public int $byReference {
        &get {
            return $this->a;
        }
    }

    public function f(): int
    {
        return 1;
    }
}

final class HookedPromotion
{
    public function __construct(
        public string $plain,
        public string $hooked = 'p' {
            set => \trim($value);
        },
    ) {}
}

final class AsymmetricPromotion
{
    public function __construct(
        private(set) string $a,
    ) {}
}
