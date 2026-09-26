<?php

declare(strict_types=1);

namespace Testo\Output\JUnit\Internal;

/**
 * A discarded attempt of a retried test, written in Maven Surefire's shape: `<flakyFailure>` /
 * `<flakyError>` when a later attempt passed, `<rerunFailure>` / `<rerunError>` when none did.
 *
 * @internal
 */
final readonly class JUnitRerun
{
    public function __construct(
        /**
         * @var non-empty-string One of 'flakyFailure', 'flakyError', 'rerunFailure', 'rerunError'.
         */
        public string $element,
        public string $type,
        public string $message,
        public string $stackTrace,
        public string $systemOut = '',
        public string $systemErr = '',
    ) {}
}
