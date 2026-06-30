<?php

it('throws with message', function () {
    doThing();
})->throws(RuntimeException::class, 'boom');
-----
<?php

/** throws with message */
#[\Testo\Test]
function it_throws_with_message(): never
{
    \Testo\Expect::exception(RuntimeException::class)->withMessage('boom');
    doThing();
}
