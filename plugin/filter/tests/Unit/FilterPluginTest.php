<?php

declare(strict_types=1);

namespace Tests\Filter\Unit;

use Internal\Container\ObjectContainer;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Filter;
use Testo\Filter\FilterPlugin;
use Testo\Filter\Internal\FilterInput;
use Testo\Filter\Internal\FilterInterceptor;
use Testo\Pipeline\InterceptorProvider;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Test;

/**
 * Verifies that {@see FilterPlugin} binds a {@see Filter} from the raw {@see FilterInput},
 * splitting `--group` values into include/exclude sets by the leading `!` marker.
 */
#[Test]
#[Covers(FilterPlugin::class)]
final class FilterPluginTest
{
    public function registersFilterInterceptorAsCaseLocator(): void
    {
        $container = new ObjectContainer();
        $input = new FilterInput();
        $container->set($input, FilterInput::class);

        (new FilterPlugin())->configure($container);

        $locators = $container->get(InterceptorProvider::class)->fromConfig(CaseLocatorInterceptor::class);

        $hasFilter = false;
        foreach ($locators as $locator) {
            $locator instanceof FilterInterceptor and $hasFilter = true;
        }

        Assert::true($hasFilter, 'FilterPlugin must register FilterInterceptor as a case locator');
    }

    public function splitsGroupOptionIntoIncludeAndExclude(): void
    {
        $filter = $this->buildFilter(['db', '!slow', 'integration']);

        Assert::same($filter->groups, ['db', 'integration']);
        Assert::same($filter->excludeGroups, ['slow']);
    }

    public function plainGroupsGoToInclude(): void
    {
        $filter = $this->buildFilter(['db', 'fast']);

        Assert::same($filter->groups, ['db', 'fast']);
        Assert::same($filter->excludeGroups, []);
    }

    public function exclamationOnlyGroupsGoToExclude(): void
    {
        $filter = $this->buildFilter(['!db', '!slow']);

        Assert::same($filter->groups, []);
        Assert::same($filter->excludeGroups, ['db', 'slow']);
    }

    public function loneExclamationMarkIsIgnored(): void
    {
        $filter = $this->buildFilter(['!']);

        Assert::same($filter->groups, []);
        Assert::same($filter->excludeGroups, []);
    }

    public function emptyGroupOptionProducesEmptySets(): void
    {
        $filter = $this->buildFilter([]);

        Assert::same($filter->groups, []);
        Assert::same($filter->excludeGroups, []);
    }

    public function splitsTypeOptionIntoIncludeAndExclude(): void
    {
        $filter = $this->buildFilterFromType(['test', '!bench', 'inline']);

        Assert::same($filter->type, ['test', 'inline']);
        Assert::same($filter->notType, ['bench']);
    }

    public function plainTypesGoToInclude(): void
    {
        $filter = $this->buildFilterFromType(['test', 'inline']);

        Assert::same($filter->type, ['test', 'inline']);
        Assert::same($filter->notType, []);
    }

    public function exclamationOnlyTypesGoToExclude(): void
    {
        $filter = $this->buildFilterFromType(['!bench', '!profile']);

        Assert::same($filter->type, []);
        Assert::same($filter->notType, ['bench', 'profile']);
    }

    public function loneExclamationMarkInTypeIsIgnored(): void
    {
        $filter = $this->buildFilterFromType(['!']);

        Assert::same($filter->type, []);
        Assert::same($filter->notType, []);
    }

    public function emptyTypeOptionProducesEmptySets(): void
    {
        $filter = $this->buildFilterFromType([]);

        Assert::same($filter->type, []);
        Assert::same($filter->notType, []);
    }

    /**
     * Configure the plugin against a container holding a prefilled {@see FilterInput}
     * and resolve the {@see Filter} it binds.
     *
     * @param list<non-empty-string> $group Raw `--group` values.
     */
    private function buildFilter(array $group): Filter
    {
        $container = new ObjectContainer();

        $input = new FilterInput();
        $input->group = $group;
        $container->set($input, FilterInput::class);

        (new FilterPlugin())->configure($container);

        return $container->get(Filter::class);
    }

    /**
     * Configure the plugin against a container holding a prefilled {@see FilterInput}
     * and resolve the {@see Filter} it binds.
     *
     * @param list<non-empty-string> $type Raw `--type` values.
     */
    private function buildFilterFromType(array $type): Filter
    {
        $container = new ObjectContainer();

        $input = new FilterInput();
        $input->type = $type;
        $container->set($input, FilterInput::class);

        (new FilterPlugin())->configure($container);

        return $container->get(Filter::class);
    }
}
