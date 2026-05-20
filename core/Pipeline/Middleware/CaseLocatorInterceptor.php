<?php

declare(strict_types=1);

namespace Testo\Pipeline\Middleware;

use Testo\Core\Definition\CaseDefinitions;
use Testo\Pipeline\Interceptor;
use Testo\Tokenizer\Reflection\FileDefinitions;

/**
 * Intercept locating test cases in a file.
 *
 * @extends \Testo\Pipeline\Interceptor<FileDefinitions, CaseDefinitions>
 *
 * @api
 */
interface CaseLocatorInterceptor extends Interceptor
{
    /**
     * Locate test cases in the given file.
     *
     * Class and function reflections are available there.
     *
     * @param FileDefinitions $file File to locate test cases in.
     * @param callable(FileDefinitions): CaseDefinitions $next Next interceptor or core logic to locate test cases.
     */
    public function locateTestCases(FileDefinitions $file, callable $next): CaseDefinitions;
}
