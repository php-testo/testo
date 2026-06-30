<?php

beforeEach(function () {
    seedDatabase();
});
-----
<?php

#[\Testo\Lifecycle\BeforeTest]
function before_each(): void
{
    seedDatabase();
}
