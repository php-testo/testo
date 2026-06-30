<?php

it('covers a class', function () {
    doThing();
})->covers(App\Calculator::class);
-----
<?php

/** covers a class */
#[\Testo\Test]
#[\Testo\Codecov\Covers(App\Calculator::class)]
function it_covers_a_class(): void
{
    doThing();
}
