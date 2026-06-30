<?php

#[\Testo\Filter\Group('grand')]
abstract class GrandParent_
{
}

#[\Testo\Filter\Group('parent')]
abstract class Parent_ extends GrandParent_
{
}

#[\Testo\Filter\Group('leaf')]
final class BazTest extends Parent_
{
}
-----
<?php

#[\Testo\Filter\Group('grand')]
abstract class GrandParent_
{
}

#[\Testo\Filter\Group('parent')]
abstract class Parent_ extends GrandParent_
{
}

#[\Testo\Filter\Group('leaf')]
#[\PHPUnit\Framework\Attributes\Group('parent')]
#[\PHPUnit\Framework\Attributes\Group('grand')]
final class BazTest extends Parent_
{
}
