<?php

declare(strict_types=1);

/**
 * Stub in global namespace (no PSR-4 prefix) so Composer's autoloader does NOT
 * intercept it. Used to exercise the reporter/skip path in
 * DefinitionLocator::getEnums().
 *
 * Implementing a non-existent interface ensures the include fails silently (the
 * enum is never defined), so LocatorException is thrown and caught.
 */
enum GlobalEnumWithUnloadableDependency9876: string implements NonExistentGlobalInterface9876
{
    case One = 'one';
}
