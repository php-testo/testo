<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Stub;

/**
 * Stub: declares a class that implements a non-existent interface so that
 * DefinitionLocator::loadReflection() fails with a LocatorException when trying
 * to reflect it. Used to exercise the reporter/skip path in getClasses().
 */
class ClassWithUnloadableDependency implements \Tests\Tokenizer\Stub\NonExistentInterface8675309 {}
