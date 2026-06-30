<?php

test('adds numbers', function () {
    expect(1 + 1)->toBe(2);
});
-----
<?php

/** adds numbers */
#[\Testo\Test]
function test_adds_numbers(): void
{
    expect(1 + 1)->toBe(2);
}
