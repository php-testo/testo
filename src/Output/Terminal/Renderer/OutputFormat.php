<?php

declare(strict_types=1);

namespace Testo\Output\Terminal\Renderer;

/**
 * Output format for terminal renderer.
 */
enum OutputFormat
{
    /**
     * Compact format: shows test cases with indented test results.
     * Default format.
     *
     * Example:
     * UserServiceTest
     *   ✓ test_can_create_user (12ms)
     *   ✗ test_validates_email (8ms)
     */
    case Compact;

    /**
     * Verbose format: shows full hierarchy with suites, cases, and summaries.
     *
     * Example:
     * Suite: Integration Tests
     *   Case: UserServiceTest
     *     ✓ test_can_create_user (12ms)
     *   Summary: 1 passed
     */
    case Verbose;

    /**
     * Dots format: minimal output showing only progress dots.
     *
     * Example:
     * UserServiceTest .F-
     * AuthServiceTest ..
     */
    case Dots;
}
