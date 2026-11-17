<?php

#[\PHPUnit\Framework\Attributes\CoversClass(className: Service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(class: Other::class)]
class FooTest
{
}
-----
<?php

#[\Testo\Codecov\Covers(Service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(class: Other::class)]
class FooTest
{
}
