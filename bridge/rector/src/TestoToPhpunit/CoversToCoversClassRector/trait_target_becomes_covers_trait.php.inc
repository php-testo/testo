<?php

#[\Testo\Codecov\Covers(\Testo\Codecov\Internal\Driver\NormalizePath::class)]
class FooTest
{
}
-----
<?php

#[\PHPUnit\Framework\Attributes\CoversTrait(\Testo\Codecov\Internal\Driver\NormalizePath::class)]
class FooTest
{
}
