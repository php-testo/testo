<?php

it('adds rows', function ($a, $b, $sum) {
    expect($a + $b)->toBe($sum);
})->with([[1, 1, 2], [2, 3, 5]]);
-----
<?php

/** adds rows */
#[\Testo\Test]
#[\Testo\Data\DataSet([1, 1, 2])]
#[\Testo\Data\DataSet([2, 3, 5])]
function it_adds_rows($a, $b, $sum): void
{
    expect($a + $b)->toBe($sum);
}
