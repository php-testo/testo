<?php

#[\Testo\Filter\Group('base')]
abstract class CombinedBase
{
    #[\Testo\Filter\Group('proto')]
    public function inherited(): void
    {
    }
}

#[\Testo\Filter\Group('leaf')]
final class CombinedLeafTest extends CombinedBase
{
    public function inherited(): void
    {
    }

    public function childOnly(): void
    {
    }
}
-----
<?php

#[\Testo\Filter\Group('base')]
abstract class CombinedBase
{
    #[\Testo\Filter\Group('proto')]
    public function inherited(): void
    {
    }
}

#[\Testo\Filter\Group('leaf')]
#[\PHPUnit\Framework\Attributes\Group('base')]
final class CombinedLeafTest extends CombinedBase
{
    #[\PHPUnit\Framework\Attributes\Group('proto')]
    public function inherited(): void
    {
    }

    public function childOnly(): void
    {
    }
}
