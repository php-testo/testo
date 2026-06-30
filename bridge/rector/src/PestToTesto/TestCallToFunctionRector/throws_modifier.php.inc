<?php

it('throws on bad input', function () {
    doThing();
})->throws(InvalidArgumentException::class);
-----
<?php

/** throws on bad input */
#[\Testo\Test]
function it_throws_on_bad_input(): never
{
    \Testo\Expect::exception(InvalidArgumentException::class);
    doThing();
}
