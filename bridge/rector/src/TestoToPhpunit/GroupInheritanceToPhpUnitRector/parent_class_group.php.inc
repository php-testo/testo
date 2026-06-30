<?php

#[\Testo\Filter\Group('base')]
abstract class Base
{
}

#[\Testo\Filter\Group('leaf')]
final class FooTest extends Base
{
}
-----
<?php

#[\Testo\Filter\Group('base')]
abstract class Base
{
}

#[\Testo\Filter\Group('leaf')]
#[\PHPUnit\Framework\Attributes\Group('base')]
final class FooTest extends Base
{
}
