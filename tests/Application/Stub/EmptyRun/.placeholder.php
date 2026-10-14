<?php

declare(strict_types=1);

// Intentionally empty — fixture directory for EmptyRunTest.
// This file exists solely so the PHPUnit mirror build (bin/build-phpunit.php)
// copies it to tests/PhpUnit/Application/Stub/EmptyRun/, creating the directory
// that the mirrored EmptyRunTest references via __DIR__ . '/../../Stub/EmptyRun'.
// The file contains no classes or tests; the Testo application run in the test
// finds zero tests here and correctly reports Status::Risky.
