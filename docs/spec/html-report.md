# HTML Report

## Overview

Testo gains a first-class HTML report that exposes **every** Testo feature — not a generic test-list page. It is produced
by a reporter/renderer configured in `testo.php`, running alongside the existing outputs, and written to a file when the
run ends. The IDE plugin learns its location from a service message and turns that into a button opening the report in an
embedded WebView or an external browser.

This document states the task and the contracts. Implementation details (which events to hook, where the reporter lives,
how statuses and channels are modelled) are to be derived from the codebase.

## Goal

An ordinary run produces the report. No separate command, no post-processing step: the reporter is configured like the
terminal, TeamCity, JUnit and JSON outputs, coexists with them in a single run, and may additionally be switchable by a
CLI flag for ad-hoc use.

## Deliverables

1. **Data model + serializer** — the whole run as a versioned, machine-readable JSON document.
2. **Renderer** — the HTML report built from that document.
3. **Wiring** — registration as a plugin/reporter, configuration in `testo.php`, the optional flag, output path handling.
4. **Announcement** — a service message carrying the report location (see *IDE integration contract*).

The IDE button itself lives in the plugin repository (`j-plugins/testo-plugin`) and is out of scope here; this document
only fixes the protocol it consumes.

## Report content

The report must cover what Testo can express. Discover the exact set from the code; at minimum:

- **Statuses** — every case of the status enum, not a passed/failed reduction. Flaky, risky and the rest must stay
  distinguishable in counters, filters and per-test views.
- **Channels** — the defining feature. Per-test channel output, preserving channel identity, log level, icon and colour.
  Rendered as separate views plus a merged one, with level filtering.
- **Assertions** — count and outcome per test; failure details with the actual/expected diff where available.
- **Failures** — message, exception type, stack trace, the failing source line.
- **Data providers and data sets** — dataset coordinates (provider index, dataset index) and the parameters of each case,
  grouped under their test.
- **Inline tests**, **benches** (including bench diagnostics/severity), and any other test types the runner supports.
- **Retries and repeats** — attempts must be visible as attempts, not as separate tests.
- **Structure** — suites, test types, groups, files/classes/functions.
- **Timings** — per-test duration plus run phases (startup, discovery, tests, teardown). The `tests` phase splits into
  execution (the wall the bodies occupied, overlaps counted once) and the pipeline overhead around them; summed test time
  over that execution is the concurrency boost. Concurrency means summed test time exceeds wall-clock; the report states
  the boost rather than presenting the difference as an error.
- **Environment** — PHP version, Testo version, OS, and the effective run configuration (filters, groups, parallelism).

## UI requirements

- **Tabbed navigation**: an overview with aggregate charts/counters, a test list, and dedicated views for channels,
  timeline and benches. Exact tab set is the implementer's call, but tab switching must not reload or lose state.
- **Per-test detail view** — status, timings, parameters/dataset, assertions, failure with trace, and channel output.
- **Filtering and search** — by status, suite, type, group, and free text. Filter state must be reflected in the URL.
- **Deep links** — every test addressable by a stable fragment (e.g. `#/test/<id>`) so the IDE can open the report
  focused on one test later.
- **Light and dark themes**, following the OS preference by default.
- Readable on a laptop screen; no horizontal scrolling in the primary views.

## Output requirements

- **Layout is open.** A single self-contained HTML file, or `index.html` + `css` + `js` + data as separate assets — both
  are acceptable. Pick whatever keeps the renderer simpler to build and maintain; a single-file mode may also be an
  option on top of a multi-file default.
- **Hard constraint: the report opens over `file://` with no server.** `<link rel=stylesheet>` and `<script src>` work
  there, so a multi-file layout is fine — but `file://` blocks more than fetching: `fetch`/`XHR` on local files, ES
  modules (`<script type="module">`), dynamic `import()` and Web Workers are all unavailable, because the origin is
  `null`. A multi-file report must therefore ship a classic-script bundle (IIFE/UMD, not ESM), relative asset paths, and
  its data as a script assigning a global (`data.js`) rather than a fetched `.json`. Note that common toolchains emit
  ESM and absolute asset paths by default and must be configured away from both. Inlining everything into one document
  removes all four restrictions at once and is the cheapest way to stay safe. The same rules apply to the IDE's embedded
  WebView.
- **No network access at all** — no CDN, no external fonts, no analytics, no phone-home.
- **Data is also a standalone output.** The JSON document must be obtainable on its own: the report is a view over the
  data, and the data is a supported artifact in its own right (CI, external tooling, the plugin itself).
- **Versioned schema.** The document carries a schema version; the renderer refuses unknown majors rather than rendering
  a half-broken page.
- **Deterministic.** Same run, same input → byte-identical output (ordering, ids, no timestamps outside declared
  fields). Reports must be diffable and testable with golden files.
- **Bounded size.** Channel output and attachments are truncated at a configurable limit, with truncation stated in the
  UI.
- **Concurrency-safe.** Generated from the completed run's data; the order in which events arrived during execution must
  not affect the result.
- **Output path configurable** through `testo.php` and the flag, with a sensible default under the project's runtime
  directory.

## IDE integration contract

As the run starts, Testo emits one service message per report it is going to write. It is non-standard — the IDE plugin
parses it explicitly — and must be emitted **only** in TeamCity output mode.

The message is not specific to this report: every reporter announces its file the same way, so a run also states its
JUnit XML, its `--log-json` file and each coverage report through it. `format` is what tells them apart — `html` and
`testo-report` for the report and the document behind it, `json` for the run summary, `junit`, `clover`, `cobertura`,
`coverage-xml`. A consumer that only wants the HTML report filters on the format rather than on the message.

```
##teamcity[testoReport format='html' path='<absolute path in the execution environment>'
           relativePath='<path relative to the working directory, when inside it>'
           name='<human-readable label>']
```

Requirements:

- `path` and `relativePath` point at the **entry file** (e.g. `index.html`), never at the directory, so the plugin opens
  it directly whatever the layout is.
- Standard TeamCity escaping for all attribute values (`|'`, `|n`, `|r`, `|]`, `|[`).
- `path` is absolute **inside the execution environment**. Under a remote interpreter or a container that is not a host
  path, which is why `relativePath` is also sent — the plugin maps it back to a local file.
- Emitted at the start of the run, before the first `testSuiteStarted` and therefore well before the last
  `testSuiteFinished`: a message that arrives after the run tree is closed has no node left to attach to. The location
  comes from the configuration, so it needs nothing from the run — but the file is not readable until the run ends, and
  a run that dies leaves the promise unfulfilled.
- Multiple messages are allowed (several formats or several reports); the plugin lists them all.
- The message carries no schema version: the document states its own in a `schemaVersion` field, so a plugin that means
  to read the data reads the version out of the data it is reading. A copy in the message is a second place to keep in
  step for no reader that would benefit.
- Outside TeamCity mode, print a plain human-readable line with the report path instead — that one waits for the write
  to finish, since a path printed for a human is only worth printing once it opens.

On the plugin side (informative, implemented elsewhere): the message enables a split button — *Open in WebView* (JCEF)
and *Open in Browser* — available for the finished run.

## Phases

1. **Data model and JSON output.** Full feature coverage, versioned schema, golden tests. Useful on its own.
2. **Renderer.** The HTML report and its UI.
3. **Wiring.** Reporter registration, `testo.php` configuration, the optional flag, default paths.
4. **Announcement.** The service message, plus the plain-text fallback.
5. **Plugin button.** Separate repository, consumes the contract above.

## Non-goals

- History and trends across runs.
- Any server, hosting or upload.
- Interoperability with third-party report formats — a separate task, deliberately not mixed in.
- Replacing the terminal or TeamCity output.

## Resolved

The open questions were settled while building it; recorded here so the reasoning is not re-litigated.

- **Renderer stack** — hand-written CSS and one classic script under `core/Output/Html/resources`. No
  node toolchain, nothing vendored, nothing built: a prototype proved that tabs without state loss,
  URL-reflected filters, hover previews, themes, diffs and a timeline all fit in vanilla JS, and the
  assets stay golden-testable as plain strings.
- **Default layout** — a directory (`index.html` + `assets/`), with the single-file mode chosen by the
  output path ending in `.html` rather than by a flag of its own. One template serves both; keeping two
  in step by hand is how a report ends up working in one layout only.
- **Attachments** — no sidecar. Channel output is capped per test at a configurable byte limit and the
  cut is stated in the document, so a report has a bounded size without a second place to look.
- **Where it lives** — `core/Output/Html`, next to the JUnit, TeamCity, terminal and JSON renderers, and
  registered in the application defaults like them. It reads bench, data, assert, retry and filter data
  directly where those plugins already leave it, each behind a `class_exists` guard — the same thing the
  terminal renderer does with assert and data — so a project that installed none of them still gets a
  report.
- **Core additions** — the announcement contract (`Testo\Event\Report\ReportFileGenerating` and
  `ReportFileGenerated` over a shared `ReportEvent`, printed by the TeamCity and terminal renderers
  respectively), run phases on `RunResult`, and `RunConfiguration` for the effective CLI input.
- **Two announcements, not one** — the service message goes out when the session starts, because the IDE
  can only attach it while the run tree is open; the human-readable line waits for the file to exist.
  Same payload in both, so nothing has to be corrected afterwards.
- **What the event carries** — a format, a label and one path, and nothing derived from them. The two
  forms the service message needs are computed by the TeamCity renderer, which is the only place that
  knows why it needs both; the schema version stays inside the document. An event that shipped every
  form a consumer might want would make each reporter compute all of them for the consumers that want
  none.
- **Named after the kind of report, not just the moment** — a file has a path, a report published to a
  service would have a URL, and the payload differs accordingly. `ReportEvent` therefore holds only
  format and label, and a non-file report becomes its own pair rather than nullable fields on this one.
- **Retry attempts** — collected from `TestRetrying` during the run and keyed by
  `Identity::$runtimeId`, because the retry interceptor drops the discarded attempts and no finished
  result keeps them. Same mechanism supplies per-test start offsets and data-set labels, which results
  also do not carry.
