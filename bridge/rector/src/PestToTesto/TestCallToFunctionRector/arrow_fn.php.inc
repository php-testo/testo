<?php

test('is truthy', fn () => expect(true)->toBeTrue());
-----
<?php

/** is truthy */
#[\Testo\Test]
function test_is_truthy(): void
{
    expect(true)->toBeTrue();
}
