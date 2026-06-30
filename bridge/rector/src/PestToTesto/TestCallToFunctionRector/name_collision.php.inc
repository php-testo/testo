<?php

it('works', function () {
    expect(1)->toBe(1);
});

it('works', function () {
    expect(2)->toBe(2);
});
-----
<?php

/** works */
#[\Testo\Test]
function it_works(): void
{
    expect(1)->toBe(1);
}

/** works */
#[\Testo\Test]
function it_works_2(): void
{
    expect(2)->toBe(2);
}
