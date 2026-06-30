<?php

it('is work in progress', function () {
    doThing();
})->skip('not ready');
-----
<?php

/** is work in progress */
#[\Testo\Test]
function it_is_work_in_progress(): void
{
    throw new \Testo\Core\Exception\SkipTest('not ready');
    doThing();
}
