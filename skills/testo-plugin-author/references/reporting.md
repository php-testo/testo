# Reporting from a plugin — identity, TeamCity, report files

Reference for plugins that *report*: custom reporters, TeamCity/IDE consumers, report-file writers.
Assumes the interceptor/listener basics from `SKILL.md`.

## Identity — where a test is, and which run of it

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

## TeamCity output — what the built-in renderer emits

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

## Report files — announce, don't print

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

A reporter writing a large file on `SessionFinished` registers its listener with a *negative* priority
(`addListener()`'s third argument, highest first) so the run's summary, printed from a listener of the
same event, reaches the reader before the serialization starts, and its path lands with the other
artifact paths at the end of the output.

Listening rather than writing? Subscribe to both events; a single listener on `ReportEvent`, the shared
parent, hears every announcement but sees only the format and the label — where a report *is* belongs to
the concrete event, since a report published to a service has no path to give you.
