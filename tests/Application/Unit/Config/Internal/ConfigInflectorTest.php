<?php

declare(strict_types=1);

namespace Tests\Application\Unit\Config\Internal;

use Internal\Container\ObjectContainer;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Config\Internal\Attribute\ConfigAttribute;
use Testo\Application\Config\Internal\Attribute\Env;
use Testo\Application\Config\Internal\Attribute\InflectableConfig;
use Testo\Application\Config\Internal\Attribute\InputArgument;
use Testo\Application\Config\Internal\Attribute\InputOption;
use Testo\Application\Config\Internal\Attribute\PhpIni;
use Testo\Application\Config\Internal\Attribute\XPath;
use Testo\Application\Config\Internal\Attribute\XPathEmbed;
use Testo\Application\Config\Internal\Attribute\XPathEmbedList;
use Testo\Application\Config\Internal\ConfigInflector;
use Testo\Application\Internal\MessengerHub;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\ErrorReporter;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
#[Covers(ConfigInflector::class)]
final class ConfigInflectorTest
{
    public function nonInflectableObjectIsReturnedUntouched(): void
    {
        $config = new PlainConfig();

        $result = (new ConfigInflector(env: ['X' => 'changed']))->inflect($config, new ObjectContainer());

        Assert::same($result, $config);
        Assert::same($config->x, 'orig');
    }

    public function propertiesWithoutAConfigAttributeAreSkipped(): void
    {
        $config = new EnvConfig();

        (new ConfigInflector(env: ['STR' => 'hello']))->inflect($config, new ObjectContainer());

        Assert::same($config->plain, 'plain');
        Assert::same($config->str, 'hello');
    }

    public function missingSourceValueLeavesTheDefault(): void
    {
        $config = new EnvConfig();

        (new ConfigInflector(env: []))->inflect($config, new ObjectContainer());

        Assert::same($config->str, 'default');
    }

    #[DataSet(['string', 'S', 'hello', 'hello'], 'string keeps the raw value')]
    #[DataSet(['int', 'I', '42', 42], 'int is cast')]
    #[DataSet(['float', 'F', '3.5', 3.5], 'float is cast')]
    #[DataSet(['bool', 'B', '1', true], 'bool is validated (truthy)')]
    #[DataSet(['bool', 'B', 'off', false], 'bool is validated (falsy)')]
    #[DataSet(['array', 'A', 'x,y,z', ['x', 'y', 'z']], 'string splits into an array')]
    #[DataSet(['intEnum', 'IE', '2', IntEnum::Two], 'int-backed enum from backing value')]
    #[DataSet(['stringEnum', 'SE', 'beta', StringEnum::Beta], 'string-backed enum from backing value')]
    #[DataSet(['pureEnum', 'UE', 'bar', PureEnum::Bar], 'pure enum matched by case name')]
    #[DataSet(['nullable', 'N', '', null], 'empty string becomes null for a nullable type')]
    #[DataSet(['union', 'U', 'xyz', 'xyz'], 'union type keeps the raw value')]
    public function envValueIsCoercedToThePropertyType(string $prop, string $env, string $raw, mixed $expected): void
    {
        $config = new TypedConfig();

        (new ConfigInflector(env: [$env => $raw]))->inflect($config, new ObjectContainer());

        Assert::same($config->$prop, $expected);
    }

    public function inputOptionSource(): void
    {
        $config = new InputConfig();

        (new ConfigInflector(inputOptions: ['opt' => 'from-option']))->inflect($config, new ObjectContainer());

        Assert::same($config->option, 'from-option');
    }

    public function inputArgumentSource(): void
    {
        $config = new InputConfig();

        (new ConfigInflector(inputArguments: ['arg' => 'from-argument']))->inflect($config, new ObjectContainer());

        Assert::same($config->argument, 'from-argument');
    }

    public function phpIniSourceReadsAnExistingOption(): void
    {
        $config = new IniConfig();

        (new ConfigInflector())->inflect($config, new ObjectContainer());

        Assert::same($config->precision, \ini_get('precision'));
    }

    public function phpIniSourceLeavesDefaultForAnUnknownOption(): void
    {
        $config = new IniConfig();

        (new ConfigInflector())->inflect($config, new ObjectContainer());

        Assert::same($config->unknown, 'untouched');
    }

    public function customConfigAttributeYieldsNoValue(): void
    {
        $config = new CustomAttrConfig();

        (new ConfigInflector())->inflect($config, new ObjectContainer());

        Assert::same($config->value, 'untouched');
    }

    public function xPathReadsAMatchingNode(): void
    {
        $xml = '<config><item name="hello"/></config>';
        $config = new XPathConfig();

        (new ConfigInflector(xml: $xml))->inflect($config, new ObjectContainer());

        Assert::count($config->tag, 1);
        Assert::same((string) $config->tag[0], 'hello');
    }

    public function xPathReturnsNothingWhenTheKeyIsOutOfRange(): void
    {
        $xml = '<config><item name="hello"/></config>';
        $config = new XPathConfig();

        (new ConfigInflector(xml: $xml))->inflect($config, new ObjectContainer());

        Assert::same($config->missing, []);
    }

    public function xPathEmbedHydratesANestedObject(): void
    {
        $xml = '<config><server host="localhost"/></config>';
        $config = new EmbedConfig();

        (new ConfigInflector(xml: $xml))->inflect($config, new ObjectContainer());

        Assert::instanceOf($config->server, ServerFixture::class);
        Assert::same((string) $config->server->host, 'localhost');
    }

    public function xPathEmbedYieldsNullWhenNoXmlIsConfigured(): void
    {
        $config = new EmbedConfig();

        (new ConfigInflector())->inflect($config, new ObjectContainer());

        Assert::null($config->server);
    }

    public function xPathEmbedYieldsNullWhenNoElementMatches(): void
    {
        $config = new EmbedConfig();

        (new ConfigInflector(xml: '<config><other/></config>'))->inflect($config, new ObjectContainer());

        Assert::null($config->server);
    }

    public function xPathEmbedListHydratesEachMatchingElement(): void
    {
        $xml = '<config><plugin name="a"/><plugin name="b"/></config>';
        $config = new EmbedListConfig();

        (new ConfigInflector(xml: $xml))->inflect($config, new ObjectContainer());

        Assert::count($config->plugins, 2);
        Assert::same((string) $config->plugins[0]->name, 'a');
        Assert::same((string) $config->plugins[1]->name, 'b');
    }

    public function xPathEmbedListYieldsEmptyWhenNoXmlIsConfigured(): void
    {
        $config = new EmbedListConfig();

        (new ConfigInflector())->inflect($config, new ObjectContainer());

        Assert::same($config->plugins, []);
    }

    public function xPathEmbedListWithAnInvalidExpressionIsSwallowed(): void
    {
        $config = new BadEmbedListConfig();

        (new ConfigInflector(xml: '<config><plugin/></config>'))->inflect($config, new ObjectContainer());

        Assert::same($config->plugins, []);
    }

    public function injectionFailureIsSwallowedWhenNoReporterIsAvailable(): void
    {
        $config = new EnumConfig();

        // Container has no ErrorReporter binding: resolving it fails, and the failure is ignored.
        (new ConfigInflector(env: ['UE' => 'not-a-case']))->inflect($config, new ObjectContainer());

        Assert::same($config->pureEnum, PureEnum::Foo);
    }

    public function injectionFailureIsReportedThroughTheContainerErrorReporter(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        };
        $hub = new MessengerHub($dispatcher);
        $container = new ObjectContainer();
        $container->set(new ErrorReporter($hub), ErrorReporter::class);

        $config = new EnumConfig();
        (new ConfigInflector(env: ['UE' => 'not-a-case']))->inflect($config, $container);

        Assert::same($config->pureEnum, PureEnum::Foo);
        Assert::true($hub->getMessages()->all() !== [], 'the failure is reported into the messenger');
    }
}

enum IntEnum: int
{
    case Zero = 0;
    case Two = 2;
}

enum StringEnum: string
{
    case Alpha = 'alpha';
    case Beta = 'beta';
}

enum PureEnum
{
    case Foo;
    case Bar;
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CustomAttr implements ConfigAttribute {}

final class PlainConfig
{
    #[Env('X')]
    public string $x = 'orig';
}

#[InflectableConfig]
final class EnvConfig
{
    #[Env('STR')]
    public string $str = 'default';

    public string $plain = 'plain';
}

#[InflectableConfig]
final class TypedConfig
{
    #[Env('S')]
    public string $string = 'orig';

    #[Env('I')]
    public int $int = -1;

    #[Env('F')]
    public float $float = -1.0;

    #[Env('B')]
    public bool $bool = false;

    #[Env('A')]
    public array $array = [];

    #[Env('IE')]
    public IntEnum $intEnum = IntEnum::Zero;

    #[Env('SE')]
    public StringEnum $stringEnum = StringEnum::Alpha;

    #[Env('UE')]
    public PureEnum $pureEnum = PureEnum::Foo;

    #[Env('N')]
    public ?string $nullable = 'orig';

    #[Env('U')]
    public int|string $union = 'orig';
}

#[InflectableConfig]
final class InputConfig
{
    #[InputOption('opt')]
    public string $option = 'orig';

    #[InputArgument('arg')]
    public string $argument = 'orig';
}

#[InflectableConfig]
final class IniConfig
{
    #[PhpIni('precision')]
    public string $precision = 'untouched';

    #[PhpIni('testo.no.such.ini.option')]
    public string $unknown = 'untouched';
}

#[InflectableConfig]
final class CustomAttrConfig
{
    #[CustomAttr]
    public string $value = 'untouched';
}

#[InflectableConfig]
final class XPathConfig
{
    #[XPath('//item/@name')]
    public array $tag = [];

    #[XPath('//item/@name', key: 5)]
    public array $missing = [];
}

#[InflectableConfig]
final class ServerFixture
{
    #[XPath('@host')]
    public mixed $host = null;
}

#[InflectableConfig]
final class EmbedConfig
{
    #[XPathEmbed('//server', ServerFixture::class)]
    public ?ServerFixture $server = null;
}

#[InflectableConfig]
final class PluginFixture
{
    #[XPath('@name')]
    public mixed $name = null;
}

#[InflectableConfig]
final class EmbedListConfig
{
    #[XPathEmbedList('//plugin', PluginFixture::class)]
    public array $plugins = [];
}

#[InflectableConfig]
final class BadEmbedListConfig
{
    #[XPathEmbedList('//plugin[', PluginFixture::class)]
    public array $plugins = [];
}

#[InflectableConfig]
final class EnumConfig
{
    #[Env('UE')]
    public PureEnum $pureEnum = PureEnum::Foo;
}
