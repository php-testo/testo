# Worked example — provision a database for every acceptance test

A full plugin combining the interceptor, scope, and skip patterns from `SKILL.md`. The Testo API is
what matters; `OrmFactory`, `Facade`, `ConnectionPool`, `SchemaInterface`, `ORMInterface`,
`DatabaseManager` are just the application's own types, standing in for "an expensive service and its
connection". Compile the heavy bit **once**, store it in the container; build the cheap per-case
service in a scope; do only cheap reset work per test; skip if the dependency is down.

```php
use Testo\Common\Messenger;

final readonly class DatabasePlugin implements PluginConfigurator
{
    public function configure(Container $container): void
    {
        $schema = $this->compileSchemaOnce();          // expensive, driver-agnostic
        $container->set($schema, SchemaInterface::class);

        // A Messenger channel is a PSR-3 logger; hand it to anything that takes one.
        // Every query logged during a test lands in that test's own output.
        $sql = $container->get(Messenger::class)->channel('query.sql');

        $pool = new ConnectionPool(logger: $sql);
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
- **Route diagnostics through a `Messenger` channel**, not `echo`/`error_log`: the channel is a PSR-3
  `LoggerInterface`, and the hub scopes messages per test — each test's queries show up in its own
  report block, even when tests interleave.
