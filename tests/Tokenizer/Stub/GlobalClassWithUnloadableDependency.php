<?php

declare(strict_types=1);

/**
 * Stub in global namespace (no PSR-4 prefix) so Composer's autoloader does NOT
 * intercept it. DefinitionLocator::loadReflection() will fire its own $includer
 * and $loader autoload callbacks, covering those branches.
 *
 * Implementing a non-existent interface ensures the include fails silently (the
 * class is never defined), so the $loader callback fires and throws LocatorException.
 */
class GlobalClassWithUnloadableDependency9876 implements NonExistentGlobalInterface9876 {}
