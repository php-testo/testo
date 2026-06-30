<?php

it('subtracts numbers', function () {
    expect(2 - 1)->toBe(1);
});
-----
<?php

/** subtracts numbers */
#[\Testo\Test]
function it_subtracts_numbers(): void
{
    expect(2 - 1)->toBe(1);
}
