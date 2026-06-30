<?php

#[\Testo\Filter\Group('db', 'slow')]
class FooTest
{
}
-----
<?php

#[\PHPUnit\Framework\Attributes\Group('db'), \PHPUnit\Framework\Attributes\Group('slow')]
class FooTest
{
}
