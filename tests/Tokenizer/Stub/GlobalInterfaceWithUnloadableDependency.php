<?php

declare(strict_types=1);

/**
 * Stub in global namespace (no PSR-4 prefix) so Composer's autoloader does NOT
 * intercept it. Used to exercise the reporter/skip path in
 * DefinitionLocator::getInterfaces().
 *
 * Extending a non-existent interface ensures the include fails silently (the
 * interface is never defined), so LocatorException is thrown and caught.
 */
interface GlobalInterfaceWithUnloadableDependency9876 extends NonExistentGlobalInterface9876 {}
