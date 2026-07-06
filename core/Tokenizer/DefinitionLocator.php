<?php

declare(strict_types=1);

namespace Testo\Tokenizer;

use Reflector;
use Testo\Common\ErrorReporter;
use Testo\Core\Log\Level;
use Testo\Tokenizer\Exception\LocatorException;
use Testo\Tokenizer\Reflection\TokenizedFile;

/**
 * Extracts PHP definitions from a tokenized file.
 *
 * Exposes built-in PHP reflections for classes, interfaces, enums, traits, and functions from a given file.
 */
final class DefinitionLocator
{
    /**
     * Get all function reflections defined in the file.
     *
     * @return array<string, \ReflectionFunction>
     */
    public static function getFunctions(TokenizedFile $file, ?ErrorReporter $reporter = null): array
    {
        $result = [];

        // todo rethink including files here
        include_once $file->path->__toString();

        foreach ($file->getFunctions() as $name) {
            try {
                $result[$name] = self::loadReflection(
                    static fn(): \ReflectionFunction => new \ReflectionFunction($name),
                    $file,
                );
            } catch (LocatorException $e) {
                # A function could not be reflected — skip it, but breadcrumb the cause on stderr (debug).
                $reporter?->report($e, Level::Debug);
                continue;
            }
        }

        return $result;
    }

    /**
     * Get all interface reflections defined in the file.
     *
     * @return array<class-string, \ReflectionClass>
     */
    public static function getInterfaces(TokenizedFile $file, ?ErrorReporter $reporter = null): array
    {
        $result = [];
        foreach ($file->getInterfaces() as $name) {
            try {
                $ref = self::loadReflection(
                    static fn(): \ReflectionClass => new \ReflectionClass($name),
                    $file,
                );
                $ref->isInterface() and $result[$name] = $ref;
            } catch (LocatorException $e) {
                # An interface could not be reflected — skip it, but breadcrumb the cause on stderr (debug).
                $reporter?->report($e, Level::Debug);
                continue;
            }
        }

        return $result;
    }

    /**
     * Get all class reflections defined in the file.
     *
     * @return array<class-string, \ReflectionClass>
     */
    public static function getClasses(TokenizedFile $file, ?ErrorReporter $reporter = null): array
    {
        $result = [];
        foreach ($file->getClasses() as $name) {
            try {
                $ref = self::loadReflection(
                    static fn(): \ReflectionClass => new \ReflectionClass($name),
                    $file,
                );
                $ref->isInterface() or $ref->isEnum() or $ref->isTrait() or $result[$name] = $ref;
            } catch (LocatorException $e) {
                # A class could not be reflected — skip it, but breadcrumb the cause on stderr (debug).
                $reporter?->report($e, Level::Debug);
                continue;
            }
        }

        return $result;
    }

    /**
     * Get all enum reflections defined in the file.
     *
     * @return array<class-string, \ReflectionEnum>
     */
    public static function getEnums(TokenizedFile $file, ?ErrorReporter $reporter = null): array
    {
        $result = [];
        foreach ($file->getEnums() as $name) {
            try {
                $result[$name] = self::loadReflection(
                    static fn(): \ReflectionEnum => new \ReflectionEnum($name),
                    $file,
                );
            } catch (LocatorException $e) {
                # An enum could not be reflected — skip it, but breadcrumb the cause on stderr (debug).
                $reporter?->report($e, Level::Debug);
                continue;
            }
        }

        return $result;
    }

    /**
     * @template T of Reflector
     * @param \Closure(): T $callable
     * @return T
     */
    private static function loadReflection(
        \Closure $callable,
        TokenizedFile $file,
    ): \Reflector {
        // Try to include the file to load the instance
        $includer = static function () use ($file): void {
            try {
                include_once $file->path->__toString();
            } catch (\Throwable) {
                // Ignoring include errors
            }
        };

        // Throw exception if the instance can not be loaded
        $loader = static function (string $class): void {
            if ($class === LocatorException::class) {
                return;
            }

            throw new LocatorException(\sprintf("Definition of `%s` can not be loaded.", $class));
        };

        //To suspend class dependency exception
        \spl_autoload_register($includer);
        \spl_autoload_register($loader);
        try {
            return $callable();
        } catch (\Throwable $e) {
            if ($e instanceof LocatorException && $e->getPrevious() != null) {
                $e = $e->getPrevious();
            }

            throw new LocatorException($e->getMessage(), (int) $e->getCode(), $e);
        } finally {
            \spl_autoload_unregister($loader);
            \spl_autoload_unregister($includer);
        }
    }
}
