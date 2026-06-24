<?php

// The same namespace is declared twice (braced form). The second block must preserve
// the `use` imports collected in the first block — this exercises the "existing namespace"
// branch in registerNamespace (line 349) and the use-import merge.

namespace Tests\Tokenizer\Stub\Repeated {
    use ArrayObject;

    final class RepeatedFirst {}
}

namespace Tests\Tokenizer\Stub\Repeated {
    final class RepeatedSecond {}
}
