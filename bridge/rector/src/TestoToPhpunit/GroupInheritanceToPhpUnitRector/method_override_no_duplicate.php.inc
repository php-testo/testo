<?php

abstract class DupBase
{
    #[\Testo\Filter\Group('shared', 'extra')]
    public function probe(): void
    {
    }
}

final class DupLeafTest extends DupBase
{
    #[\PHPUnit\Framework\Attributes\Group('shared')]
    public function probe(): void
    {
    }
}
-----
<?php

abstract class DupBase
{
    #[\Testo\Filter\Group('shared', 'extra')]
    public function probe(): void
    {
    }
}

final class DupLeafTest extends DupBase
{
    #[\PHPUnit\Framework\Attributes\Group('shared')]
    #[\PHPUnit\Framework\Attributes\Group('extra')]
    public function probe(): void
    {
    }
}
