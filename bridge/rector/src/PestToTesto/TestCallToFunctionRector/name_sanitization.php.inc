<?php

test('it handles 3 weird chars: $, %, &!', function () {
    doThing();
});
-----
<?php

/** it handles 3 weird chars: $, %, &! */
#[\Testo\Test]
function test_it_handles_3_weird_chars(): void
{
    doThing();
}
