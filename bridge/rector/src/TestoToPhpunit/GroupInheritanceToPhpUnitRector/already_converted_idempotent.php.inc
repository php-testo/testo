<?php

#[\Testo\Filter\Group('base')]
abstract class IdempotentBase
{
}

#[\Testo\Filter\Group('leaf')]
#[\PHPUnit\Framework\Attributes\Group('base')]
final class IdempotentTest extends IdempotentBase
{
}
