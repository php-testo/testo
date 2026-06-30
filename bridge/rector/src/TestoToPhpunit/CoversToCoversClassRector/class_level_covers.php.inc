<?php

#[\Testo\Codecov\Covers(Foo::class)]
class FooTest
{
}
-----
<?php

#[\PHPUnit\Framework\Attributes\CoversClass(Foo::class)]
class FooTest
{
}
