<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Token;

/**
 * Reads the original tokens of the file a rule is processing.
 *
 * Rector before 2.8 exposes the file as the `$file` property, later versions only via `getFile()`.
 *
 * @internal
 */
trait OriginalTokens
{
    /**
     * @return array<Token>
     */
    private function originalTokens(): array
    {
        /** @psalm-suppress UndefinedThisPropertyFetch, UndefinedMethod */
        return \method_exists($this, 'getFile')
            ? $this->getFile()->getOldTokens()
            : $this->file->getOldTokens();
    }
}
