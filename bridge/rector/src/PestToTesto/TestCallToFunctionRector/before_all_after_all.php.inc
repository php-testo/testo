<?php

beforeAll(function () {
    bootKernel();
});

afterAll(function () {
    shutdownKernel();
});
-----
<?php

#[\Testo\Lifecycle\BeforeClass]
function before_all(): void
{
    bootKernel();
}

#[\Testo\Lifecycle\AfterClass]
function after_all(): void
{
    shutdownKernel();
}
