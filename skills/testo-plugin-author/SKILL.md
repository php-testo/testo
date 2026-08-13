---
name: testo-plugin-author
description: Author a Testo plugin — interceptors (middleware), event listeners, container bindings, custom attributes, or full test-environment provisioning. Use when the user wants to extend how Testo runs tests (wrap every test, provision a database/service, custom reporters, attribute-driven behaviour, integrating an external system) rather than writing a single test.
---

# Authoring a Testo plugin

A Testo plugin is a class implementing `Testo\Common\PluginConfigurator`. Its one method,
`configure(Container $container)`, runs **once per suite** and wires the plugin into that suite's DI
container. From there a plugin can:

- Register **interceptors** (middleware) that wrap test / test-case / discovery execution.
- Subscribe to lifecycle **events** (PSR-14) — `TestFinished`, `TestSuiteStarting`, …
- **Bind / scope services** in the container — provision resources, replace Testo defaults.
- Define and act on **custom attributes** placed on test classes/methods.

`llms.txt` covers test authoring; this skill covers the plugin surface. Escalate to
`https://php-testo.github.io/llms-full.txt` only for things not here. **Verify type/namespace names
against the installed `vendor/testo/` before relying on memory** — the APIs below are stable but
version-specific.

## Vocabulary (get this right)

- **Test** — one `#[Test]` method / function / inline case.
- **Test Case** — file-scope group: the methods of one class (or functions of one file).
- **Test Suite** — a configured collection of cases (`SuiteConfig`). **The suite is the smallest unit
  a plugin applies to.** Each suite gets its own container, so different suites can run different plugins.

Pipeline events fire top-down: `Session → Worker → TestSuite → TestCase → TestPipeline → TestBatch → Test`.

## Registering a plugin

```php
// testo.php
new SuiteConfig(
    name: 'Acceptance',
    location: new FinderConfig(include: ['tests/Acceptance/Driver']),
    plugins: [new DatabasePlugin()],           // this suite only
);
// or ApplicationConfig(plugins: [...]) for every suite (coverage, JUnit, …)
```

## The container

`configure()` receives `Internal\Container\Container`, which **extends PSR-11
`Psr\Container\ContainerInterface`** — so the container itself can be handed to anything expecting a
PSR-11 container (e.g. a framework Facade). Its surface:

```php
$c->get(Foo::class, $args = []);             // resolve (lazy-instantiate + cache); $args used on first build
$c->has(Foo::class): bool;                   // is there a binding or cached instance?
$c->set($instance, Foo::class);              // register an existing instance under an id
$c->make(Foo::class, $args = []);            // build WITHOUT caching
$c->bind(Foo::class, fn(Container $c) => …); // factory / alias / arg-array for lazy construction
$c->scope(fn(Container $scope) => …);        // run a closure in a CHILD scope (see below)
```

> Importing `Internal\Container\Container` is unavoidable — it is the declared `configure()` parameter
> type. Otherwise avoid `Internal\*` types; prefer `Testo\*`.

## Interceptors (middleware) — the main tool

Interceptors wrap execution at a chosen pipeline level. All live in `Testo\Pipeline\Middleware`; a
single class may implement several. Register them in `configure()`:

```php
$container->get(InterceptorCollector::class)->addInterceptor(new MyInterceptor(/* deps */));
```

| Interface                | Method                                                                 | Wraps                    |
|--------------------------|------------------------------------------------------------------------|--------------------------|
| `TestRunInterceptor`     | `runTest(TestInfo $i, callable $next): TestResult`                     | one test                 |
| `TestCaseRunInterceptor` | `runTestCase(CaseInfo $i, callable $next): CaseResult`                 | one case (all its tests) |
| `CaseLocatorInterceptor` | `locateTestCases(FileDefinitions $f, callable $next): CaseDefinitions` | discovery                |

`$next` is the rest of the chain (and ultimately the test). **Always call it once** unless you are
deliberately short-circuiting (see skipping). Use `try { return $next($i); } finally { … }` for
cleanup — later interceptors can throw.

### Ordering & scoping with `#[InterceptorOptions]`

```php
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Core\Value\TestType;

#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, testType: TestType::Test)]
final readonly class MyInterceptor implements TestRunInterceptor { … }
```

- `order` — **higher = closer to the test**. Constants: `ORDER_FILTER`, `ORDER_DATA_PROVIDER`,
  `ORDER_DEFAULT` (0), `ORDER_ASSERTIONS`, `ORDER_CLOSE_TO_TEST`, `ORDER_RIGHT_BEFORE_TEST`.
- `testType` — restrict to a kind of test (e.g. `TestType::Test`, not benches/inline). Omit to apply to all.

### Context objects you read/return

```php
TestInfo  { string $name; CaseInfo $caseInfo; TestDefinition $testDefinition;
            array $arguments; array $attributes; TestIdentity $identity; }
                                                         // testDefinition->reflection: ReflectionFunctionAbstract
CaseInfo  { CaseDefinition $definition; ?CaseInstance $instance; array $attributes;
            CaseIdentity $identity; }                    // definition->reflection: ?ReflectionClass
SuiteInfo { string $name; CaseDefinitions $testCases; array $attributes; SuiteIdentity $identity; }
TestResult{ TestInfo $info; Status $status; mixed $result; ?\Throwable $failure; … }
```

Read the test method via `$info->testDefinition->reflection`; the test class via
`$info->caseInfo->definition->reflection` (null for function-based cases — guard it).

### Identity — where a test is, and which run of it

Every context object carries its address. `abstract readonly class Identity` in `Testo\Core\Context`
contributes `runtimeId`, `?parentId` and `fqn()`; each level declares exactly the fields it has, and lives in
`Testo\Core\Context\Identity\*` — `SuiteIdentity { suite }`, `CaseIdentity { suite, ?case, type, file }`,
`TestIdentity { suite, ?case, type, file, test, ?dataProvider, ?dataSet, pipelineId }`. No level references the one
above it, so reading any part of an address never walks a chain of objects, and no level carries a field
that does not apply to it.

`case` is the **class FQN**, and `null` for a case of free functions — there is no class, so `file`
(an `Internal\Path`) names the case instead. `test` is the name **relative to `case`**: a bare method
name when there is a class, the function's own FQN when there is not.

Step down with `SuiteIdentity::toCase($case, $type, $file)`, `CaseIdentity::toTest($test)`, and
`TestIdentity::toDataSet(dataProvider:, dataSet:)` for a data set.

```php
$info->identity->fqn();        // 'Tests\Foo\BarTest::itWorks:0:1' — null only at the case level,
                               // 'Tests\Foo\freeTest' for a function (namespace, no class)
$info->identity->suite;        // 'Core/Unit'
$info->identity->runtimeId;    // this run; a data set has its own
$info->identity->pipelineId;   // the test run it belongs to; a data set answers its batch's
$info->identity->parentId;     // the run it opened inside: suite ← case ← test ← data set
```

Two independent things live on it:

- **The address** (`fqn()` and the fields) says *which* test this is and is stable from
  run to run. `dataProvider`/`dataSet` are set only for a data set, and address it by **index** —
  provider keys may repeat, so only the index tells two data sets apart. `fqn()` is the machine-facing
  form: no suite, no type, pastes straight into `--filter`, and is the tail of TeamCity's
  `locationHint` (`php_qn://<file>::\<fqn>`). A case of free functions has no `fqn()` — no class to
  qualify — and is hinted as `file://<file>` instead, so its node stays clickable.
- **`runtimeId`** says *which run of it* is in flight, **`pipelineId`** which test run that one is part
  of — its own for a test, the batch's for each of its data sets — and **`parentId`** which run it
  opened inside (`null` at a suite). All three are process-local: never persist them or match on them.
  Repeats and retries keep `runtimeId`, since they re-attempt one run.

Key per-test state in a reporter by one of the two rather than by a single "current test" field: when
tests run concurrently (fibers, an event loop) their events and output interleave, and a scalar gets
clobbered. Key by **`pipelineId`** for anything a whole test owns — a report block, a channel grouping,
a TeamCity flow — or a batch would break into as many pieces as it has data sets; by **`runtimeId`**
only for state that is genuinely per data set. `MessageReceived` carries the identity of the test (or
data set) that emitted it as `?TestIdentity $identity` (`null` when the message belongs to no test).

To report a **tree** rather than a stream — an IDE that wants `nodeId`/`parentNodeId` — take the node
from `runtimeId` and its parent from `parentId`, the same two fields at every level. Do not read the
tree off the order events arrive in: concurrent tests interleave, so a consumer that nests by "whatever
opened last" puts one test's node inside another's.

The built-in TeamCity output carries the **exact** `Status` as a `status` attribute (lowercased case
name: `passed`, `failed`, `skipped`, `error`, `risky`, `flaky`, `cancelled`, `aborted`) on every
`testFinished`, and the aggregated one on `testSuiteFinished` for a suite, a case and a DataProvider
batch. The standard protocol collapses those eight into ignored/failed/neither, so a consumer that
needs `Flaky` apart from `Passed`, or `Risky` apart from a clean pass, reads them there; standard
parsers ignore the attribute. `testFinished` also carries `assertions` — the count the Assert plugin
records under that metric name — omitted entirely when no plugin counted them, which is not the same
as `assertions='0'` for a test that asserted nothing.

Every opening message — `testSuiteStarted` for a suite, a case or a DataProvider batch, and
`testStarted` for a test or a data set — carries `testSuite` and `testType`, the two things `--suite`
and `--type` select on. A suite of the run states only `testSuite`: it holds cases of several types
and has none of its own.

Each suite opens with `##teamcity[testCount count='N']` — the tests located for it, read off
`SuiteInfo::$testCases` before the first one runs. Counts accumulate across suites in IntelliJ-based
IDEs (the TeamCity server ignores the message), so one per suite is the intended shape. A DataProvider
test counts once but reports a node per data set, so the number is a lower bound.

### Passing state down the pipeline — prefer attributes over mutable fields

`TestInfo`, `CaseInfo`, and `TestResult` use the `Attributed` trait: `withAttribute(string $name,
mixed $value): static` and `getAttribute(string $name, mixed $default = null)`. Attach state in
`runTestCase`, read it in `runTest` — a `CaseInfo` modified and passed to `$next` reaches each test as
`$info->caseInfo`:

```php
public function runTestCase(CaseInfo $info, callable $next): CaseResult {
    $fixture = $this->buildFixture(...);
    return $next($info->withAttribute('db.fixture', $fixture));
}
public function runTest(TestInfo $info, callable $next): TestResult {
    $fixture = $info->caseInfo->getAttribute('db.fixture');
    …
}
```

This keeps the interceptor **stateless** — safer than mutable `$this->current` fields. A container
`scope` (below) is an even cleaner carrier when the state is a set of services.

### Skipping from an interceptor — return, do not throw

`throw new SkipTest(...)` only works inside the test body; from an interceptor it bubbles past the
handler and becomes `Status::Aborted`. To skip, **return a `TestResult` without calling `$next`**:

```php
use Testo\Core\Value\Status;
use Testo\Core\Exception\SkipTest;

if (!$reachable) {
    return new TestResult(info: $info, status: Status::Skipped, failure: new SkipTest('db down'));
}
```

## Container scopes — provision per-case / per-suite resources

`$container->scope($closure)` runs `$closure` in a **child scope**: services bound inside live only for
the closure, and `$container` resolves the active scope while it runs. This is the clean way to build a
resource once, expose it (even to a PSR-11 consumer), and tear it down automatically.

```php
public function runTestCase(CaseInfo $info, callable $next): CaseResult {
    return $this->container->scope(function (Container $scope) use ($info, $next) {
        $service = $this->build(...);
        $scope->set($service, Service::class);          // visible to this case's tests
        try {
            return $next($info);                         // tests run inside the scope
        } finally {
            // scope (and its bindings) discarded automatically afterwards
        }
    });
}

public function runTest(TestInfo $info, callable $next): TestResult {
    if (!$this->container->has(Service::class)) { /* scope not opened → skip or next */ }
    $service = $this->container->get(Service::class);    // same instance, no rebuild per test
    …
}
```

Because the container resolves the **current** scope, `runTest` reads back exactly what `runTestCase`
bound — no need to thread the objects through attributes. Use this to build expensive things **once per
case** and only do cheap per-test work (e.g. reset state) in `runTest`.

## Event listeners — observe, don't mutate

```php
use Testo\Common\EventListenerCollector;
use Testo\Event\Test\TestFinished;

public function configure(Container $container): void {
    $logger = $container->get(MyLogger::class);                 // resolve deps HERE
    $container->get(EventListenerCollector::class)
        ->addListener(TestFinished::class, static fn(TestFinished $e) => $logger->record($e));
}
```

Listeners are observers. To **change** behaviour (skip, wrap, retry, inject), write an interceptor.
**Never capture `$container` inside the listener closure** — resolve services in `configure()` and close
over those.

`addListener()` takes a third `int $priority` argument, highest first. It matters when several listeners
share one event and the order is part of the behaviour — a reporter writing a large file on
`SessionFinished` uses a *negative* priority so the run's summary, printed from a listener of the same
event, reaches the reader before the serialization starts, and its path lands with the other artifact
paths at the end of the output.

Writing a report file? Announce it instead of printing anything yourself: the active renderer states it
in its own terms. Both events carry the same payload — `ReportFileGenerating` as soon as the destination
is known, which for a reporter built from a whole run means `SessionStarting`, and `ReportFileGenerated`
once the file is closed. The early one becomes the `##teamcity[testoReport …]` service message an IDE
turns into a button, and it has to be early: after the last `testSuiteFinished` there is no node in the
run tree to attach it to. The late one is what a terminal prints as a plain path.

```php
// once the destination is settled — in configure(), for a reporter built from a whole run
$this->info = new ReportInfo('html', 'My report', $entry);   // Testo\Core\Report\ReportInfo

// on SessionStarting — the path comes from the config, so nothing waits for the run
$dispatcher->dispatch(new ReportFileGenerating($this->info));

// on SessionFinished, after the file is closed
$this->write($this->info->path);
$dispatcher->dispatch(new ReportFileGenerated($this->info));
```

Three things and no more in the card: the format id, the label a UI puts on the button, and the entry
file (`index.html`, never the directory). Pass the path as you hold it, relative or absolute — a renderer
that needs the other form derives it, because only the renderer knows which form its reader can resolve.
Both events carry the card as `$event->info`.

Listening rather than writing? Subscribe to both events; a single listener on `ReportEvent`, the shared
parent, hears every announcement but sees only the format and the label — where a report *is* belongs to
the concrete event, since a report published to a service has no path to give you.

## Custom attributes

Define the attribute, then act on it from an interceptor:

```php
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class WithoutTransaction {}
```

Read it hierarchy-aware with `Testo\Common\Reflection` (walks parents + traits; `MERGE_FIRST` =
closest layer wins, like `#[Covers]` resolution), or plain reflection for the simple case:

```php
$method = $info->testDefinition->reflection;
$optedOut = $method->getAttributes(WithoutTransaction::class) !== [];
```

## Worked example — provision a database for every acceptance test

Below, the Testo API is what matters; `OrmFactory`, `Facade`, `ConnectionPool`, `SchemaInterface`,
`ORMInterface`, `DatabaseManager` are just the application's own types, standing in for "an expensive
service and its connection". Compile the heavy bit **once**, store it in the container; build the cheap
per-case service in a scope; do only cheap reset work per test; skip if the dependency is down.

```php
final readonly class DatabasePlugin implements PluginConfigurator
{
    public function configure(Container $container): void
    {
        $schema = $this->compileSchemaOnce();          // expensive, driver-agnostic
        $container->set($schema, SchemaInterface::class);

        $pool = new ConnectionPool();
        $container->set($pool);

        $container->get(InterceptorCollector::class)
            ->addInterceptor(new DatabaseInterceptor($container, $pool, $schema));
    }
}

#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, testType: TestType::Test)]
final readonly class DatabaseInterceptor implements TestCaseRunInterceptor, TestRunInterceptor
{
    public function __construct(
        private Container $container,
        private ConnectionPool $pool,
        private SchemaInterface $schema,
    ) {}

    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        $driver = $this->resolveDriver($info->definition->reflection);   // from a #[Group] / attribute
        if ($driver === null) return $next($info);                       // not a DB case

        $manager = $this->pool->manager($driver);
        try { $manager->getDriver()->connect(); }
        catch (\Throwable) { return $next($info); }                      // unreachable → tests will skip

        $this->pool->prepareOnce($driver, $manager);                     // create tables + seed once

        return $this->container->scope(function (Container $scope) use ($manager, $info, $next) {
            $orm = OrmFactory::build($manager, $this->schema, $scope);
            $scope->set($orm, ORMInterface::class);
            $scope->set($manager);
            Facade::setContainer($scope);                                // PSR-11 consumer gets the scope
            try { return $next($info); } finally { Facade::reset(); }
        });
    }

    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $driver = $this->resolveDriver($info->caseInfo->definition->reflection);
        if ($driver === null) return $next($info);

        if (!$this->container->has(ORMInterface::class)) {               // scope never opened → DB down
            return new TestResult($info, Status::Skipped, failure: new SkipTest("`{$driver->value}` down"));
        }

        $this->container->get(ORMInterface::class)->getHeap()->clean();  // clean identity map per test
        $db = $this->container->get(DatabaseManager::class)->database()->getDriver();
        $db->beginTransaction();
        try { return $next($info); } finally { $db->rollbackTransaction(); }   // isolate without recreating tables
    }
}
```

Plugin lessons this encodes:

- **Compile/build expensive, immutable things once** in `configure()` (→ container); rebuild only cheap
  per-case/per-test things in the interceptor.
- **Split the levels**: `runTestCase` for per-resource setup, `runTest` for cheap per-test reset only.
- **The scoped container is the single source of truth** — both the external consumer and `runTest`
  read services back from it, so there's no separate fixture object to thread around.
- **Skip, don't fail**, when an external dependency is unavailable.
- A **custom attribute** (`#[WithoutTransaction]`) lets individual tests opt out of the wrapping.

## Pitfalls

- **Skipping**: return a `Status::Skipped` `TestResult`; never `throw SkipTest` from an interceptor.
- **Cleanup**: wrap `$next()` in `try/finally`; a later interceptor may throw.
- **State**: prefer pipeline attributes / container scope over mutable interceptor fields.
- **Listeners** observe; **interceptors** change behaviour. Don't try to alter a run from a listener.
- **Don't capture `$container` in listener closures** — resolve and inject in `configure()`.
- **Don't write a plugin to fix one test** — a `#[BeforeTest]` hook in that class is enough. Reach for a
  plugin when behaviour spans every test of a suite.
- **Test the plugin**: mirror Testo's own `plugin/<name>/tests/` layout so it can be extracted later.
