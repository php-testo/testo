<?php

declare(strict_types=1);

namespace Testo\Filter;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Filter;
use Testo\Filter\Internal\FilterInput;
use Testo\Filter\Internal\FilterInterceptor;
use Testo\Pipeline\InterceptorProvider;

/**
 * Expose filtering functionality.
 *
 * @api
 */
final readonly class FilterPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorProvider::class)->addInterceptor(FilterInterceptor::class);

        $container->bind(Filter::class, static function (FilterInput $scope): Filter {
            $groups = $excludeGroups = [];
            foreach ($scope->group as $group) {
                if (!\str_starts_with($group, '!')) {
                    $groups[] = $group;
                    continue;
                }

                $name = \substr($group, 1);
                $name === '' or $excludeGroups[] = $name;
            }

            return new Filter(
                suites: $scope->suite,
                names: $scope->filter,
                paths: $scope->path,
                type: $scope->type,
                groups: $groups,
                excludeGroups: $excludeGroups,
            );
        });
    }
}
