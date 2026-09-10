<?php

declare(strict_types=1);

namespace Testo\Filter\Internal;

use Internal\Path;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Filter;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\SuiteLocatorInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Filters test suites by name (`--suite`) and by location (`--path`).
 *
 * Suites whose name is not selected are dropped. With a path filter, each suite keeps only the
 * include roots touching the filtered paths, narrowed down to them; a suite left without include
 * roots is dropped.
 *
 * @internal
 * @psalm-internal Testo\Filter
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_FILTER, onConflict: ConflictPolicy::First)]
final readonly class SuiteFilterInterceptor implements SuiteLocatorInterceptor
{
    public function __construct(
        private Filter $filter,
    ) {}

    #[\Override]
    public function locateTestSuites(ApplicationConfig $config, callable $next): array
    {
        $suites = $next($config);
        if ($this->filter->suites === [] && $this->filter->paths === []) {
            return $suites;
        }

        $result = [];
        foreach ($suites as $suite) {
            if ($this->filter->suites !== [] && !\in_array($suite->name, $this->filter->suites, true)) {
                continue;
            }

            if ($this->filter->paths === []) {
                $result[] = $suite;
                continue;
            }

            $includes = $this->narrowIncludes($suite->location->includes);
            $includes === [] or $result[] = $suite->with(
                location: new FinderConfig($includes, $suite->location->excludes),
            );
        }

        return $result;
    }

    /**
     * Intersect include roots with the path filter: a filtered path inside a root replaces the root,
     * a root inside a filtered path stays as is, a root touching no filtered path is dropped.
     *
     * @param Path[] $includes
     * @return list<Path>
     */
    private function narrowIncludes(array $includes): array
    {
        $result = [];
        foreach ($includes as $include) {
            foreach ($this->filter->paths as $path) {
                $match = match (true) {
                    $path->isWithin($include) => $path,
                    $include->isWithin($path) => $include,
                    default => null,
                };
                $match === null or $result[(string) $match] = $match;
            }
        }

        return \array_values($result);
    }
}
