<?php

it('uses groups', function () {
    doThing();
})->group('math', 'fast');
-----
<?php

/** uses groups */
#[\Testo\Test]
#[\Testo\Filter\Group('math', 'fast')]
function it_uses_groups(): void
{
    doThing();
}
