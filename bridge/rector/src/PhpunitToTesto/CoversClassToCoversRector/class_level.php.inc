<?php

#[\PHPUnit\Framework\Attributes\CoversClass(Service::class)]
class FooTest
{
}
-----
<?php

#[\Testo\Codecov\Covers(Service::class)]
class FooTest
{
}
