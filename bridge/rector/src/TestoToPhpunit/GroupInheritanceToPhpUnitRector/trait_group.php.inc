<?php

#[\Testo\Filter\Group('shared')]
trait GroupTrait
{
}

#[\Testo\Filter\Group('leaf')]
final class BarTest
{
    use GroupTrait;
}
-----
<?php

#[\Testo\Filter\Group('shared')]
trait GroupTrait
{
}

#[\Testo\Filter\Group('leaf')]
#[\PHPUnit\Framework\Attributes\Group('shared')]
final class BarTest
{
    use GroupTrait;
}
