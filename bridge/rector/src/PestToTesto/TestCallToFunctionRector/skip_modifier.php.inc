<?php

it('is work in progress', function () {
    doThing();
})->skip('not ready');
-----
<?php

/** is work in progress */
#[\Testo\Test]
#[\Testo\Skip('not ready')]
function it_is_work_in_progress(): void
{
    doThing();
}
