<?php

afterEach(function () {
    truncateDatabase();
});
-----
<?php

#[\Testo\Lifecycle\AfterTest]
function after_each(): void
{
    truncateDatabase();
}
