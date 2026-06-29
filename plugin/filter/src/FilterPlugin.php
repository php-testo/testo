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
            [$types, $notTypes] = self::splitInclusionExclusion($scope->type);
            [$groups, $excludeGroups] = self::splitInclusionExclusion($scope->group);

            return new Filter(
                suites: $scope->suite,
                names: $scope->filter,
                paths: $scope->path,
                type: $types,
                notType: $notTypes,
                groups: $groups,
                excludeGroups: $excludeGroups,
            );
        });
    }

    /**
     * Split raw filter values into include and exclude sets by the leading `!` marker.
     * A lone `!` (empty name after stripping) is ignored.
     *
     * @param non-empty-string[] $values
     * @return array{list<non-empty-string>, list<non-empty-string>} Tuple of [include, exclude].
     */
    private static function splitInclusionExclusion(array $values): array
    {
        $include = $exclude = [];
        foreach ($values as $value) {
            if (!\str_starts_with($value, '!')) {
                $include[] = $value;
                continue;
            }

            $name = \substr($value, 1);
            $name === '' or $exclude[] = $name;
        }

        return [$include, $exclude];
    }
}
