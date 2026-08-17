/**
 * Testo HTML report — prototype renderer.
 *
 * Classic IIFE script: no ES modules, no fetch, no workers — everything the `file://`
 * origin forbids. The payload arrives as `window.TESTO_REPORT` (assets/data.js).
 */
(function () {
    'use strict';

    var SUPPORTED_SCHEMA_MAJOR = 1;

    /* ---------------------------------------------------------------- vocabulary */

    /**
     * Every Status case of the enum, kept distinguishable: colour never carries the
     * meaning alone — each status ships an icon and a label.
     */
    var STATUS = {
        passed: {label: 'Passed', icon: '✓', order: 1},
        failed: {label: 'Failed', icon: '✕', order: 2},
        error: {label: 'Error', icon: '!', order: 3, textured: true},
        risky: {label: 'Risky', icon: '▲', order: 4},
        flaky: {label: 'Flaky', icon: '↻', order: 5},
        skipped: {label: 'Skipped', icon: '↷', order: 6},
        cancelled: {label: 'Cancelled', icon: '⊘', order: 7, textured: true},
        aborted: {label: 'Aborted', icon: '■', order: 8}
    };

    /** Reading order — legend, tiles, filter chips: best outcome to worst. */
    var STATUS_ORDER = Object.keys(STATUS).sort(function (a, b) {
        return STATUS[a].order - STATUS[b].order;
    });

    /**
     * Paint order of the stacked bars — a colour-safety mechanism, not cosmetics. Green beside red
     * measures ΔE 4.1 under deuteranopia, so Passed and Failed never touch; yellow beside orange
     * fails the normal-vision floor, so Flaky and Risky never touch either. The textured pair sits
     * next to its own family (Cancelled by Skipped, Error by Failed), where the stripe tells them apart.
     */
    var BAR_ORDER = ['passed', 'flaky', 'skipped', 'cancelled', 'aborted', 'risky', 'error', 'failed'];

    var TABS = [
        {id: 'overview', label: 'Overview'},
        {id: 'tests', label: 'Tests'},
        {id: 'channels', label: 'Channels'},
        {id: 'timeline', label: 'Timeline'},
        {id: 'benches', label: 'Benches'},
        {id: 'env', label: 'Environment'}
    ];

    /**
     * How a channel looks is decided here rather than in the document: a report that pinned colours
     * would freeze them into every file ever written, and a channel's identity is its name.
     *
     * Known channels get an icon; anything a plugin or a test invents gets a stable, name-derived colour,
     * the same rule the terminal renderer uses.
     */
    var CHANNEL_PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];

    var CHANNEL_ICONS = {
        'stdout': '›',
        'stderr': '!',
        'retry': '↻',
        'bench-result': '⏱',
        'bench-iterations': '∷',
        'assert-history': '✓'
    };

    var CHANNEL_COLORS = {
        'stderr': '#d03b3b',
        'retry': '#fab219'
    };

    /**
     * Channels whose messages are chunks of one byte stream, not discrete records. Output capture writes
     * a chunk per flush, so a bare `echo` is not a "message": giving each its own row, timestamp and level
     * shreds a single line of output across the log. A streaming channel is reassembled instead —
     * consecutive chunks join into a continuous run, a chunk ending in a newline closes the block the next
     * one opens after, and any other channel wedging in splits the run where it lands. Each chunk stays a
     * hoverable element carrying its own arrival time and level.
     */
    var STREAMING_CHANNELS = {'stdout': true, 'stderr': true};

    function isStreaming(name) { return STREAMING_CHANNELS[name] === true; }

    /* ------------------------------------------------------------------ helpers */

    function el(id) { return document.getElementById(id); }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function crc32(str) {
        var c, crc = 0xFFFFFFFF;
        for (var i = 0; i < str.length; i++) {
            c = (crc ^ str.charCodeAt(i)) & 0xFF;
            for (var k = 0; k < 8; k++) {
                c = c & 1 ? (c >>> 1) ^ 0xEDB88320 : c >>> 1;
            }
            crc = (crc >>> 8) ^ c;
        }
        return (crc ^ 0xFFFFFFFF) >>> 0;
    }

    function dur(seconds) {
        if (seconds === null || seconds === undefined) { return '—'; }
        if (seconds >= 1) { return seconds.toFixed(2) + ' s'; }
        if (seconds >= 0.001) { return (seconds * 1000).toFixed(seconds >= 0.1 ? 0 : 1) + ' ms'; }
        return (seconds * 1000000).toFixed(0) + ' µs';
    }

    function pct(part, total) { return total > 0 ? Math.round(part / total * 1000) / 10 : 0; }

    function statusChip(status, extra) {
        var meta = STATUS[status] || {label: status, icon: '•'};
        return '<span class="s-' + esc(status) + '" style="display:inline-flex;align-items:center;gap:6px">'
            + '<span class="ico">' + meta.icon + '</span>'
            + '<span style="color:var(--ink)' + (extra === 'strong' ? ';font-weight:600' : '') + '">'
            + esc(meta.label) + '</span></span>';
    }

    function verdictChip(status) {
        var meta = STATUS[status] || {label: status, icon: '•'};
        return '<span class="run-verdict s-' + esc(status) + '">'
            + '<span class="ico">' + meta.icon + '</span>' + esc(meta.label.toUpperCase()) + '</span>';
    }

    /* -------------------------------------------------------------------- model */

    var report = window.TESTO_REPORT;
    var model = {tests: [], byId: {}, suites: [], channels: {}, messages: [], benches: []};

    function indexModel() {
        (report.channels || []).forEach(function (c) {
            model.channels[c.name] = decorateChannel(c.name, c.messages || 0);
        });

        report.suites.forEach(function (suite) {
            model.suites.push(suite);
            suite.cases.forEach(function (kase) {
                kase.suite = suite;
                kase.tests.forEach(function (test) {
                    test.case = kase;
                    test.suite = suite;
                    model.tests.push(test);
                    model.byId[test.id] = test;
                    if (test.bench) { model.benches.push(test); }
                    collectMessages(test, test.messages, null);
                    (test.attempts || []).forEach(function (attempt) {
                        collectMessages(test, attempt.messages, attempt.number);
                    });
                    (test.dataSets || []).forEach(function (set) {
                        collectMessages(test, set.messages, null);
                    });
                });
            });
        });

        // Stable order: by time, ties broken by insertion — a run's own order, never the arrival order.
        model.messages.sort(function (a, b) { return a.time - b.time || a.seq - b.seq; });
    }

    function decorateChannel(name, count) {
        return {
            name: name,
            icon: CHANNEL_ICONS[name] || '•',
            color: CHANNEL_COLORS[name] || CHANNEL_PALETTE[crc32(name) % CHANNEL_PALETTE.length],
            declared: Object.prototype.hasOwnProperty.call(CHANNEL_ICONS, name),
            total: count,
            count: 0
        };
    }

    function collectMessages(test, messages, attempt) {
        (messages || []).forEach(function (m) {
            var channel = model.channels[m.channel]
                || (model.channels[m.channel] = decorateChannel(m.channel, 0));
            channel.count++;
            model.messages.push({
                seq: model.messages.length,
                time: m.time,
                channel: m.channel,
                level: m.level,
                content: m.content,
                test: test,
                attempt: attempt
            });
        });
    }

    function assertionsOf(test) { return (test.metrics && test.metrics.assertions) || 0; }

    function groupsOf() {
        var seen = {};
        model.tests.forEach(function (t) { (t.groups || []).forEach(function (g) { seen[g] = true; }); });
        return Object.keys(seen).sort();
    }

    function typesOf() {
        var seen = {};
        model.tests.forEach(function (t) { seen[t.type] = true; });
        return Object.keys(seen).sort();
    }

    /* ------------------------------------------------------------------- router */

    /** Filter and view state lives in the URL, so every view is linkable and shareable. */
    var route = {tab: 'overview', test: null, params: {}};

    function parseHash() {
        var raw = location.hash.replace(/^#\/?/, '');
        var qs = '';
        var qi = raw.indexOf('?');
        if (qi >= 0) { qs = raw.slice(qi + 1); raw = raw.slice(0, qi); }

        var parts = raw.split('/').filter(Boolean).map(decodeURIComponent);
        var params = {};
        qs.split('&').filter(Boolean).forEach(function (pair) {
            var i = pair.indexOf('=');
            var k = decodeURIComponent(i < 0 ? pair : pair.slice(0, i));
            params[k] = i < 0 ? '' : decodeURIComponent(pair.slice(i + 1).replace(/\+/g, ' '));
        });

        if (parts[0] === 'test' && parts[1]) {
            return {tab: 'tests', test: parts.slice(1).join('/'), params: params};
        }
        var tab = parts[0] && TABS.some(function (t) { return t.id === parts[0]; }) ? parts[0] : 'overview';
        return {tab: tab, test: null, params: params};
    }

    function href(tab, params, test) {
        var qs = Object.keys(params || {}).filter(function (k) {
            return params[k] !== '' && params[k] !== null && params[k] !== undefined;
        }).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
        }).join('&');
        var path = test ? 'test/' + encodeURIComponent(test) : tab;
        return '#/' + path + (qs ? '?' + qs : '');
    }

    function go(tab, params, test) { location.hash = href(tab, params, test); }

    function patchParams(patch) {
        var next = {};
        Object.keys(route.params).forEach(function (k) { next[k] = route.params[k]; });
        // A query typed but not yet written back must survive a chip or a select changing around it.
        if (liveQuery !== null) { next.q = liveQuery; }
        Object.keys(patch).forEach(function (k) {
            if (patch[k] === null || patch[k] === '') { delete next[k]; } else { next[k] = patch[k]; }
        });
        go(route.tab, next, route.test);
    }

    /* ------------------------------------------------------------------ filters */

    function activeStatuses() {
        var raw = route.params.status;
        return raw ? raw.split(',').filter(function (s) { return STATUS[s]; }) : [];
    }

    /**
     * The query being typed, which runs ahead of the URL: the search box filters the list in place and
     * only writes the hash back on a debounce. Re-rendering on every keystroke would destroy the input
     * the caret sits in — and because `hashchange` fires asynchronously, restoring focus afterwards
     * would target an element the next render has already thrown away.
     */
    var liveQuery = null;

    function currentQuery() {
        return (liveQuery === null ? (route.params.q || '') : liveQuery).trim().toLowerCase();
    }

    /** Everything the free-text query searches, folded into one lowercase string per test. */
    function haystack(test) {
        return [test.id, test.name, test.description, test.case.name, test.file,
            test.failure ? test.failure.message : ''].join(' ').toLowerCase();
    }

    function matches(test) {
        var statuses = activeStatuses();
        if (statuses.length && statuses.indexOf(test.status) < 0) { return false; }
        if (route.params.suite && test.suite.id !== route.params.suite) { return false; }
        if (route.params.type && test.type !== route.params.type) { return false; }
        if (route.params.group && (test.groups || []).indexOf(route.params.group) < 0) { return false; }

        var q = currentQuery();
        return !q || haystack(test).indexOf(q) >= 0;
    }

    function filtered() { return model.tests.filter(matches); }

    /* -------------------------------------------------------------- shell chrome */

    function renderChrome() {
        var run = report.run;
        var total = totalTests();

        el('verdict').innerHTML = verdictChip(run.status);
        el('generator').textContent = 'v' + report.generator.version;
        el('top-meta').innerHTML = ''
            + '<span class="kv">' + esc(new Date(run.startedAt).toLocaleString()) + '</span>'
            + '<span class="kv">wall <b>' + dur(run.duration) + '</b></span>'
            + '<span class="kv">tests <b>' + total + '</b></span>'
            + '<span class="kv">assertions <b>' + (run.summary.metrics.assertions || 0) + '</b></span>';

        el('tabs').innerHTML = TABS.map(function (t) {
            // The list counts rows; a data-provider test is one row that the summary counts as many.
            var n = t.id === 'tests' ? model.tests.length
                : t.id === 'channels' ? model.messages.length
                    : t.id === 'benches' ? model.benches.length : null;
            return '<a href="' + href(t.id, t.id === 'tests' ? route.params : {}) + '"'
                + (t.id === route.tab ? ' aria-current="page"' : '') + '>' + esc(t.label)
                + (n === null ? '' : '<span class="n">' + n + '</span>') + '</a>';
        }).join('');
    }

    function totalTests() {
        return report.run.summary.total;
    }

    function countsByStatus(tests) {
        var out = {};
        tests.forEach(function (t) { out[t.status] = (out[t.status] || 0) + 1; });
        return out;
    }

    function stackbar(counts, total) {
        return '<div class="stackbar">' + BAR_ORDER.filter(function (s) {
            return counts[s];
        }).map(function (s) {
            return '<span class="bg-' + s + '" style="flex:' + counts[s] + '" title="'
                + esc(STATUS[s].label + ': ' + counts[s] + ' of ' + total) + '"></span>';
        }).join('') + '</div>';
    }

    function minibar(counts) {
        return '<div class="minibar">' + BAR_ORDER.filter(function (s) {
            return counts[s];
        }).map(function (s) {
            return '<span class="bg-' + s + '" style="flex:' + counts[s] + '" title="'
                + esc(STATUS[s].label + ': ' + counts[s]) + '"></span>';
        }).join('') + '</div>';
    }

    /* --------------------------------------------------------------- tab: overview */

    function renderOverview() {
        var run = report.run;
        var counts = run.summary.counts;
        var total = totalTests();

        var html = ''
            + '<div class="hero">'
            + heroCell('Result', verdictChip(run.status), esc(run.status === 'passed' ? 'all green' : 'see the failures below'))
            + heroCell('Tests', String(total), dataSetNote(total))
            + heroCell('Wall clock', dur(run.duration), 'summed test time ' + dur(run.testDuration))
            + heroCell('Assertions', String(run.summary.metrics.assertions || 0),
                (Math.round((run.summary.metrics.assertions || 0) / Math.max(1, total) * 10) / 10) + ' per test')
            + heroCell('Suites', String(report.suites.length),
                'of ' + report.environment.config.suites.length + ' configured')
            + '</div>'

            + '<div class="grid cols-2">'
            + card('Status breakdown', 'every case of the status enum, never reduced to pass/fail',
                stackbar(counts, total) + statusTiles(counts, total))
            + card('Run phases', 'wall-clock, not summed test time', phasesBlock(run))
            + '</div>'

            + '<div class="grid cols-2">'
            + card('Slowest tests', 'top 10 by duration', slowestBlock())
            + card('Suites', null, suitesTable())
            + '</div>';

        var notes = notesBlock();
        if (notes !== '') {
            html += '<div class="grid">' + card('Notes', null, notes) + '</div>';
        }

        return html;
    }

    /**
     * A data set counts as a test in the summary but is one row in the list, so the two numbers
     * differ on purpose — say so instead of letting the reader find the discrepancy.
     */
    function dataSetNote(total) {
        var sets = 0;
        model.tests.forEach(function (t) { sets += (t.dataSets || []).length; });
        return sets
            ? (total - sets) + ' tests + ' + sets + ' data sets'
            : model.tests.length + ' in the list';
    }

    function heroCell(label, value, sub) {
        return '<div class="cell"><div class="label">' + esc(label) + '</div>'
            + '<div class="value">' + value + '</div>'
            + '<div class="sub">' + sub + '</div></div>';
    }

    function card(title, hint, body) {
        return '<section class="card">'
            + (title ? '<header>' + esc(title) + (hint ? '<span class="hint">' + esc(hint) + '</span>' : '') + '</header>' : '')
            + '<div class="body">' + body + '</div></section>';
    }

    /**
     * The status tiles double as the stacked bar's legend — every slot is present even at zero, so
     * the vocabulary is complete, and each one links to the test list filtered by that status.
     */
    function statusTiles(counts, total) {
        return '<div class="tiles">' + STATUS_ORDER.map(function (s) {
            var n = counts[s] || 0;
            return '<a class="tile" href="' + href('tests', {status: s})
                + '" title="filter the test list by ' + esc(STATUS[s].label) + '"'
                + (n ? '' : ' style="opacity:.55"') + '>'
                + '<span class="head">'
                + '<span class="dot bg-' + s + (STATUS[s].textured ? ' textured' : '') + '"></span>'
                + '<span class="ico s-' + s + '">' + STATUS[s].icon + '</span>'
                + esc(STATUS[s].label) + '</span>'
                + '<span class="num">' + n + (n ? '<span class="pct">' + pct(n, total) + '%</span>' : '')
                + '</span></a>';
        }).join('') + '</div>';
    }

    function phasesBlock(run) {
        var sum = run.phases.reduce(function (a, p) { return a + p.duration; }, 0);
        // flex-grow is the phase's share of the total: raw seconds sum to well under 1 and would leave
        // the bar mostly empty, since grows below 1 distribute only that fraction of the free space.
        return '<div class="phases">' + run.phases.map(function (p) {
            return '<span style="flex:' + (sum > 0 ? p.duration / sum : 1)
                + '" title="' + esc(p.name + ' ' + dur(p.duration)) + '"></span>';
        }).join('') + '</div>'
            + '<div class="legend phases-legend">' + run.phases.map(function (p) {
                return '<span class="item"><span class="dot"></span>' + esc(p.name)
                    + ' <b>' + dur(p.duration) + '</b></span>';
            }).join('') + '</div>'
            + testsSplit(run)
            + concurrencyNote(run)
            + '<div style="margin-top:8px;color:var(--muted);font-size:12px">phases total ' + dur(sum)
            + ' · writing the report happens after the last phase and is counted in none of them</div>';
    }

    function phaseDuration(run, name) {
        var p = run.phases.filter(function (x) { return x.name === name; })[0];
        return p ? p.duration : 0;
    }

    /**
     * The `tests` phase is not all test bodies: some of it is the pipeline around them. `execution` is
     * the wall the bodies actually occupied (overlaps counted once), so the rest is framework overhead —
     * the "обвязка" that summed test time cannot show on its own.
     */
    function testsSplit(run) {
        if (run.execution === undefined || run.execution === null) { return ''; }
        var overhead = Math.max(0, phaseDuration(run, 'tests') - run.execution);

        return '<div class="legend phase-stats">'
            + '<span class="item">executing <b>' + dur(run.execution) + '</b></span>'
            + '<span class="item">pipeline overhead <b>' + dur(overhead) + '</b></span>'
            + '</div>';
    }

    /**
     * When tests overlap, summed test time exceeds the wall they ran on — that ratio is the concurrency
     * boost. Stating it turns the two numbers disagreeing (which reads as a bug) into the point.
     */
    function concurrencyNote(run) {
        if (!run.boost || run.boost < 1.005) { return ''; }

        return '<div class="notice info" style="margin-top:12px"><span class="ico">i</span><span>'
            + 'The tests declared <b>' + dur(run.testDuration) + '</b> of work but ran in <b>'
            + dur(run.execution) + '</b> on the wall — a <b>×' + (Math.round(run.boost * 10) / 10)
            + '</b> speed-up from running them concurrently. Not an error.</span></div>';
    }

    function slowestBlock() {
        var top = model.tests.slice().sort(function (a, b) {
            return b.duration - a.duration || a.id.localeCompare(b.id);
        }).slice(0, 10);
        var max = top.length ? top[0].duration : 1;

        return '<div class="barlist">' + top.map(function (t) {
            return '<div class="row">'
                + '<a class="name" href="' + href('tests', {}, t.id) + '" title="' + esc(t.id) + '">'
                + '<span class="ico s-' + t.status + '">' + STATUS[t.status].icon + '</span> '
                + esc(t.case.name.split('\\').pop() + '::' + t.name) + '</a>'
                + '<div class="val">' + dur(t.duration) + '</div>'
                + '<div class="track"><div class="fill bg-' + t.status + '" style="width:'
                + (t.duration / max * 100) + '%"></div></div>'
                + '</div>';
        }).join('') + '</div>';
    }

    function suitesTable() {
        return '<table class="grid-table"><thead><tr>'
            + '<th>Suite</th><th>Status</th><th>Mix</th><th class="num">Tests</th><th class="num">Duration</th>'
            + '</tr></thead><tbody>'
            + report.suites.map(function (suite) {
                var tests = model.tests.filter(function (t) { return t.suite.id === suite.id; });
                var counts = countsByStatus(tests);
                return '<tr>'
                    + '<td><a href="' + href('tests', {suite: suite.id}) + '">' + esc(suite.name) + '</a></td>'
                    + '<td>' + statusChip(suite.status) + '</td>'
                    + '<td>' + minibar(counts) + '</td>'
                    + '<td class="num">' + tests.length + '</td>'
                    + '<td class="num">' + dur(suite.duration) + '</td>'
                    + '</tr>';
            }).join('') + '</tbody></table>';
    }

    /**
     * Empty when the run has nothing to warn about, so the card stays out of the overview.
     */
    function notesBlock() {
        var truncated = model.tests.filter(function (t) { return t.truncated; });
        if (!truncated.length) {
            return '';
        }

        return '<div class="notice"><span class="ico">⚠</span><span>'
            + truncated.length + ' test(s) had their channel output truncated at the configured limit. '
            + truncated.map(function (t) {
                return '<a href="' + href('tests', {}, t.id) + '">' + esc(t.name) + '</a>';
            }).join(', ') + '</span></div>';
    }

    /* ------------------------------------------------------------------ tab: tests */

    function renderTests() {
        return filterBar() + '<div class="panes">'
            + '<section class="card tree" id="tree">' + treeHtml() + '</section>'
            + '<section class="card detail" id="detail">' + detailHtml() + '</section>'
            + '</div>';
    }

    function filterBar() {
        var counts = countsByStatus(model.tests);
        var active = activeStatuses();

        return '<div class="filters">'
            + '<input type="search" id="f-q" placeholder="Search tests, files, failure messages…" value="'
            + esc(liveQuery === null ? (route.params.q || '') : liveQuery) + '">'
            + '<div class="chips">' + STATUS_ORDER.filter(function (s) { return counts[s]; }).map(function (s) {
                return '<button class="chip" data-status="' + s + '" aria-pressed="'
                    + (active.indexOf(s) >= 0) + '">'
                    + '<span class="ico s-' + s + '">' + STATUS[s].icon + '</span>' + esc(STATUS[s].label)
                    + '<span class="n">' + counts[s] + '</span></button>';
            }).join('') + '</div>'
            + select('f-suite', 'All suites', report.suites.map(function (s) { return s.id; }), route.params.suite)
            + select('f-type', 'All types', typesOf(), route.params.type)
            + select('f-group', 'All groups', groupsOf(), route.params.group)
            + '<span class="count reset" id="f-count"></span>'
            + '</div>';
    }

    function select(id, placeholder, options, value) {
        return '<select id="' + id + '"><option value="">' + esc(placeholder) + '</option>'
            + options.map(function (o) {
                return '<option value="' + esc(o) + '"' + (o === value ? ' selected' : '') + '>' + esc(o) + '</option>';
            }).join('') + '</select>';
    }

    /**
     * The whole tree, unfiltered — {@see applyTestFilter} hides what does not match. Rendering once and
     * filtering the DOM afterwards is what lets the search box keep the caret while it narrows the list.
     */
    function treeHtml() {
        return report.suites.map(function (suite) {
            return '<div class="suite"><div class="head">'
                + '<span class="twisty"></span>'
                + '<span class="ico s-' + suite.status + '">' + STATUS[suite.status].icon + '</span>'
                + esc(suite.name)
                + '<span class="tail" data-duration="' + esc(dur(suite.duration)) + '"></span>'
                + '</div><div class="kids">'
                + suite.cases.map(function (kase) {
                    return '<div class="case"><div class="head">'
                        + '<span class="twisty"></span>'
                        + '<span class="ico s-' + kase.status + '">' + STATUS[kase.status].icon + '</span>'
                        + '<span style="overflow:hidden;text-overflow:ellipsis">'
                        + esc(shortCase(kase.name)) + '</span>'
                        + '<span class="tail"></span>'
                        + '</div><div class="kids tests">'
                        + kase.tests.map(testRow).join('')
                        + '</div></div>';
                }).join('')
                + '</div></div>';
        }).join('') + '<div class="empty is-hidden" id="tree-empty">Nothing matches the filter.</div>';
    }

    /**
     * Applies the current filter to the rendered tree: hides the rows that do not match, folds away
     * cases and suites left with nothing, and restates the counts. Returns how many tests survived.
     */
    function applyTestFilter() {
        var q = currentQuery();
        var statuses = activeStatuses();
        var suite = route.params.suite || '';
        var type = route.params.type || '';
        var group = route.params.group || '';
        var visible = 0;
        var facet = {};

        each('#tree .test', function (row) {
            var status = row.getAttribute('data-status');
            // Everything but the status filter, so the chips can say how many each status would add.
            var rest = (!suite || row.getAttribute('data-suite') === suite)
                && (!type || row.getAttribute('data-type') === type)
                && (!group || (' ' + row.getAttribute('data-groups') + ' ').indexOf(' ' + group + ' ') >= 0)
                && (!q || row.getAttribute('data-hay').indexOf(q) >= 0);

            rest && (facet[status] = (facet[status] || 0) + 1);

            var ok = rest && (!statuses.length || statuses.indexOf(status) >= 0);
            row.classList.toggle('is-hidden', !ok);
            ok && visible++;
        });

        each('.filters [data-status]', function (chip) {
            var n = facet[chip.getAttribute('data-status')] || 0;
            var slot = chip.querySelector('.n');
            slot && (slot.textContent = String(n));
            chip.classList.toggle('is-zero', n === 0);
        });

        each('#tree .case', function (node) {
            var n = node.querySelectorAll('.test:not(.is-hidden)').length;
            node.classList.toggle('is-hidden', n === 0);
            setTail(node, n === 0 ? '' : String(n));
        });

        each('#tree .suite', function (node) {
            var n = node.querySelectorAll('.test:not(.is-hidden)').length;
            node.classList.toggle('is-hidden', n === 0);
            var tail = node.querySelector(':scope > .head > .tail');
            tail && (tail.textContent = n === 0 ? '' : n + ' · ' + tail.getAttribute('data-duration'));
        });

        var empty = el('tree-empty');
        empty && empty.classList.toggle('is-hidden', visible > 0);

        var count = el('f-count');
        count && (count.innerHTML = visible + ' of ' + model.tests.length
            + (visible === model.tests.length ? ''
                : ' · <a href="' + href('tests', {}, route.test) + '">reset</a>'));

        return visible;
    }

    function setTail(node, text) {
        var tail = node.querySelector(':scope > .head > .tail');
        tail && (tail.textContent = text);
    }

    function each(selector, fn) {
        Array.prototype.forEach.call(document.querySelectorAll(selector), fn);
    }

    function shortCase(name) {
        if (name.indexOf('\\') < 0) { return name; }
        var parts = name.split('\\');
        return parts[parts.length - 1];
    }

    function testRow(test) {
        var badges = [];
        if (test.type !== 'test') { badges.push('<span class="pill">' + esc(test.type) + '</span>'); }
        if (test.dataSets) { badges.push('<span class="pill">' + test.dataSets.length + ' sets</span>'); }
        if (test.attempts) { badges.push('<span class="pill">' + test.attempts.length + ' attempts</span>'); }
        if (test.bench) { badges.push('<span class="pill">bench</span>'); }

        // The href stays the canonical, filter-free deep link — the shape the IDE gets and the one worth
        // copying. A click is intercepted and carries the filters along instead.
        return '<a class="test" href="' + href('tests', {}, test.id) + '"'
            + ' data-test="' + esc(test.id) + '"'
            + ' data-status="' + esc(test.status) + '"'
            + ' data-suite="' + esc(test.suite.id) + '"'
            + ' data-type="' + esc(test.type) + '"'
            + ' data-groups="' + esc((test.groups || []).join(' ')) + '"'
            + ' data-hay="' + esc(haystack(test)) + '"'
            + (route.test === test.id ? ' aria-current="true"' : '') + '>'
            + '<span class="spacer"></span>'
            + '<span class="ico s-' + test.status + '" title="' + esc(STATUS[test.status].label) + '">'
            + STATUS[test.status].icon + '</span>'
            + '<span class="tname">' + esc(test.name) + '</span>'
            + '<span class="badges">' + badges.join('') + '<span class="dur">' + dur(test.duration) + '</span></span>'
            + '</a>';
    }

    function detailHtml() {
        var test = route.test ? model.byId[route.test] : null;
        if (!test) {
            var visible = filtered();
            return '<div class="empty">Pick a test on the left.<br><br>'
                + (visible.length ? 'Deep link shape: <code>#/test/&lt;id&gt;</code> — e.g. '
                    + '<a href="' + href('tests', {}, visible[0].id) + '">' + esc(visible[0].id) + '</a>' : '')
                + '</div>';
        }

        var out = ''
            + '<div class="headline">'
            + '<div class="crumbs">' + esc(test.suite.name) + ' › ' + esc(test.case.name) + '</div>'
            + '<h3>' + '<span class="ico s-' + test.status + '">' + STATUS[test.status].icon + '</span>'
            + esc(test.name)
            + (test.type !== 'test' ? '<span class="pill">' + esc(test.type) + '</span>' : '')
            + '</h3>'
            + (test.description ? '<div class="desc">' + esc(test.description) + '</div>' : '')
            + '<div class="facts">'
            + '<span>' + statusChip(test.status, 'strong') + '</span>'
            + '<span>duration <b>' + dur(test.duration) + '</b></span>'
            + (test.startedAt === null ? '' : '<span>started at <b>+' + dur(test.startedAt) + '</b></span>')
            + '<span>assertions <b>' + assertionsOf(test) + '</b></span>'
            + (test.groups && test.groups.length ? '<span>groups ' + test.groups.map(function (g) {
                return '<a href="' + href('tests', {group: g}) + '">' + esc(g) + '</a>';
            }).join(', ') + '</span>' : '')
            + '<span>' + esc(test.file) + (test.line === null ? '' : ':' + test.line) + '</span>'
            + '</div>'
            // The exact string --filter takes back, so a reader can rerun what they are looking at.
            + '<div class="crumbs" style="margin-top:8px">--filter=' + esc(test.filter) + '</div>'
            + '</div>';

        if (test.statusReason) {
            out += '<div class="section"><h4>Reason</h4><div class="notice"><span class="ico s-' + test.status + '">'
                + STATUS[test.status].icon + '</span><span>' + esc(test.statusReason) + '</span></div></div>';
        }
        if (test.failure) { out += '<div class="section"><h4>Failure</h4>' + failureHtml(test.failure) + '</div>'; }
        if (test.attempts) { out += attemptsSection(test); }
        if (test.dataSets) { out += dataSetsSection(test); }
        if (test.bench) { out += '<div class="section"><h4>Benchmark</h4>' + benchHtml(test.bench) + '</div>'; }
        out += outputSection(test);

        return out;
    }

    function failureHtml(failure) {
        var out = '<div class="failure">'
            + '<div class="cls">' + esc(failure.class) + '</div>'
            + '<div class="msg">' + esc(failure.message) + '</div>'
            + '<div class="loc">' + esc(failure.file) + ':' + failure.line + '</div>'
            + (failure.sourceLine
                ? '<pre class="code">' + failure.line + ' │ ' + esc(failure.sourceLine) + '</pre>' : '')
            + '</div>';

        if (failure.diff) {
            out += '<div style="margin-top:10px"><div class="diff">'
                + failure.diff.lines.map(function (l) {
                    return '<div class="l ' + esc(l.op) + '">' + esc(l.text) + '</div>';
                }).join('') + '</div></div>';
        }

        if (failure.causedBy && failure.causedBy.length) {
            out += '<details class="fold" style="margin-top:10px"><summary>Caused by ('
                + failure.causedBy.length + ')</summary>'
                + failure.causedBy.map(function (c) {
                    return '<div class="failure" style="margin-top:8px">'
                        + '<div class="cls">' + esc(c.class) + '</div>'
                        + '<div class="msg">' + esc(c.message) + '</div>'
                        + '<div class="loc">' + esc(c.file) + ':' + c.line + '</div></div>';
                }).join('') + '</details>';
        }

        if (failure.trace && failure.trace.length) {
            out += '<details class="fold" style="margin-top:10px" open><summary>Stack trace ('
                + failure.trace.length + ' frames)</summary><div class="trace" style="margin-top:6px">'
                + failure.trace.map(function (f, i) {
                    return '<div class="frame"><span class="i">#' + i + '</span>'
                        + '<span class="where">' + esc(f.file) + ':' + f.line
                        + ' <span class="call">' + esc(f.call) + '</span></span></div>';
                }).join('') + '</div></details>';
        }

        return out;
    }

    function attemptsSection(test) {
        var policy = test.retryPolicy;
        return '<div class="section"><h4>Attempts <span style="font-weight:400;text-transform:none;letter-spacing:0">'
            + (policy
                ? 'retry policy: max ' + policy.maxAttempts
                + (policy.markFlaky ? ', marks flaky' : ', keeps the status')
                : '')
            + '</span></h4><div class="attempts">'
            + test.attempts.map(function (a) {
                var kept = a.kept;
                return '<div class="attempt ' + (kept ? 'kept' : 'discarded') + '">'
                    + '<div class="head">'
                    + '<span class="ico s-' + a.status + '">' + STATUS[a.status].icon + '</span>'
                    + '<span class="n">Attempt ' + a.number + '</span>'
                    + '<span class="pill">' + (kept ? 'kept' : 'discarded') + '</span>'
                    + '<span class="dur">' + dur(a.duration) + '</span>'
                    + '</div>'
                    + (a.failure ? '<div style="margin-top:8px">' + failureHtml(a.failure) + '</div>' : '')
                    + (a.messages && a.messages.length
                        ? '<details class="fold" style="margin-top:8px"><summary>Output of this attempt</summary>'
                        + logHtml(a.messages.map(function (m) {
                            return {time: m.time, channel: m.channel, level: m.level, content: m.content};
                        }), false) + '</details>' : '')
                    + '</div>';
            }).join('') + '</div></div>';
    }

    function dataSetsSection(test) {
        var counts = countsByStatus(test.dataSets);
        return '<div class="section"><h4>Data sets <span style="font-weight:400">'
            + test.dataSets.length + '</span></h4>'
            + minibar(counts)
            + '<table class="grid-table" style="margin-top:10px"><thead><tr>'
            + '<th>#</th><th>Key</th><th>Arguments</th><th>Status</th><th class="num">Duration</th>'
            + '</tr></thead><tbody>'
            + test.dataSets.map(function (set) {
                return '<tr>'
                    + '<td class="num">' + (set.providerIndex !== null && set.providerIndex !== undefined
                        ? set.providerIndex + ':' : '') + set.index + '</td>'
                    + '<td>' + esc(set.key) + '</td>'
                    + '<td>' + (set.arguments || []).map(function (a) {
                        return '<span class="tag">$' + esc(a.name) + ' = ' + esc(a.value) + '</span>';
                    }).join('') + '</td>'
                    + '<td>' + statusChip(set.status) + '</td>'
                    + '<td class="num">' + dur(set.duration) + '</td>'
                    + '</tr>'
                    + (set.failure ? '<tr><td></td><td colspan="4" style="padding-bottom:12px">'
                        + failureHtml(set.failure) + '</td></tr>' : '');
            }).join('') + '</tbody></table></div>';
    }

    function outputSection(test) {
        var lines = [];
        (test.messages || []).forEach(function (m) { lines.push(m); });
        (test.dataSets || []).forEach(function (s) {
            (s.messages || []).forEach(function (m) { lines.push(m); });
        });

        if (!lines.length) {
            return '<div class="section"><h4>Channel output</h4>'
                + '<div style="color:var(--muted);font-size:12.5px">No output recorded.</div></div>';
        }

        var counts = {};
        lines.forEach(function (m) { counts[m.channel] = (counts[m.channel] || 0) + 1; });
        var names = Object.keys(counts).sort();

        // Channels are buttons over one list, not separate lists: pressing them narrows the merged view,
        // hovering one previews what it would keep. Nothing pressed means everything is shown.
        var out = '<div class="section" id="test-output"><h4>Channel output'
            + '<span style="margin-left:auto;font-weight:400;text-transform:none;letter-spacing:0">'
            + names.length + ' channel(s), ' + lines.length + ' message(s)</span></h4>'
            + '<div class="chips" style="margin-bottom:10px">' + names.map(function (name) {
                var ch = channelOf(name);
                return '<button class="chip" data-ch-filter="' + esc(name) + '" aria-pressed="false">'
                    + '<span class="swatch" style="width:3px;height:13px;border-radius:2px;background:'
                    + esc(ch.color) + '"></span>'
                    + '<span style="color:' + esc(ch.color) + '">' + ch.icon + '</span>' + esc(name)
                    + '<span class="n">' + counts[name] + '</span></button>';
            }).join('') + '</div>'
            + logHtml(lines, false);

        if (test.truncated && test.truncated.messages) {
            var t = test.truncated.messages;
            out += '<div class="notice" style="margin-top:10px"><span class="ico">⚠</span><span>'
                + 'Output truncated: showing ' + t.shown + ' of ' + t.total + ' messages ('
                + Math.round(t.bytes / 1024) + ' KiB captured, limit ' + Math.round(t.limit / 1024)
                + ' KiB).</span></div>';
        }

        return out + '</div>';
    }

    /** A channel from the document, or one invented on the spot for output nothing declared. */
    function channelOf(name) {
        return model.channels[name] || decorateChannel(name, 0);
    }

    function logHtml(lines, withTest) {
        // Group the ordered run: a maximal run of one streaming channel becomes a single reassembled row,
        // everything else stays one row per message. A message of any other channel ends the run in
        // progress, so the next chunk of the stream opens a fresh row where the interruption landed.
        var groups = [];
        var stream = null;
        lines.forEach(function (m) {
            if (isStreaming(m.channel) && stream && stream.channel === m.channel) {
                stream.chunks.push(m);
                return;
            }
            if (isStreaming(m.channel)) {
                stream = {channel: m.channel, chunks: [m]};
                groups.push({stream: stream});
                return;
            }
            stream = null;
            groups.push({line: m});
        });

        return '<div class="log">' + groups.map(function (g) {
            return g.stream ? streamHtml(g.stream, withTest) : lineHtml(g.line, withTest);
        }).join('') + '</div>';
    }

    /** The source-test suffix a merged view appends: which test, and which retry, wrote the message. */
    function fromHtml(m) {
        return '<span class="from">  ← <a href="' + href('tests', {}, m.test.id) + '">'
            + esc(m.test.name) + (m.attempt ? ' #' + m.attempt : '') + '</a></span>';
    }

    /** One discrete message: a row of time · channel · level · content. */
    function lineHtml(m, withTest) {
        var ch = channelOf(m.channel);
        return '<div class="line" data-ch="' + esc(m.channel) + '" data-copy="' + esc(m.content) + '">'
            + '<span class="t">+' + m.time.toFixed(3) + '</span>'
            + '<span class="ch"><span class="swatch" style="background:' + esc(ch.color) + '"></span>'
            + '<span style="color:' + esc(ch.color) + '">' + ch.icon + '</span>'
            + '<span>' + esc(m.channel) + '</span>'
            + '<span class="lv lv-' + esc(m.level) + '">' + esc(m.level) + '</span></span>'
            + '<span class="content">' + esc(m.content.replace(/\n$/, ''))
            + (withTest && m.test ? fromHtml(m) : '')
            + '</span>' + copyBtn() + '</div>';
    }

    /**
     * A streaming channel's run reassembled into one row. The left columns carry the run's start and its
     * channel; the content is the stream rebuilt — chunks concatenated in place, broken into blocks at the
     * newlines that ended a chunk, each chunk its own hoverable span. No level badge: a run has no single
     * level, so each chunk keeps its own in its hover hint. No whitespace may sit between chunk spans in
     * the markup, or `pre-wrap` would render it as output that was never there.
     */
    function streamHtml(stream, withTest) {
        var ch = channelOf(stream.channel);
        var first = stream.chunks[0];
        // The message copies whole: the raw stream this row rebuilt, every chunk in order, newlines kept.
        var copy = stream.chunks.map(function (m) { return m.content; }).join('');
        // A lone chunk is the whole message — nothing to tell apart within it, so it gets no per-chunk tip.
        var multi = stream.chunks.length > 1;

        var blocks = [[]];
        stream.chunks.forEach(function (m) {
            blocks[blocks.length - 1].push(m);
            /\n$/.test(m.content) && blocks.push([]);
        });
        blocks[blocks.length - 1].length || blocks.pop();

        var body = blocks.map(function (chunks) {
            return '<span class="stream-block">' + chunks.map(function (m) {
                return chunkHtml(m, withTest, multi);
            }).join('') + '</span>';
        }).join('');

        // A merged view still names the source when the whole run came from one test; a run that spans
        // several leaves the attribution to each chunk's hover hint rather than picking one to show.
        var sameTest = withTest && first.test && stream.chunks.every(function (m) {
            return m.test === first.test && m.attempt === first.attempt;
        });

        return '<div class="line stream" data-ch="' + esc(stream.channel) + '" data-copy="' + esc(copy) + '">'
            + '<span class="t">+' + first.time.toFixed(3) + '</span>'
            + '<span class="ch"><span class="swatch" style="background:' + esc(ch.color) + '"></span>'
            + '<span style="color:' + esc(ch.color) + '">' + ch.icon + '</span>'
            + '<span>' + esc(stream.channel) + '</span></span>'
            + '<span class="content stream-body">' + body + (sameTest ? fromHtml(first) : '')
            + '</span>' + copyBtn() + '</div>';
    }

    /**
     * One chunk of a stream: an inline span holding its raw content, with its channel, arrival, level and
     * source composed into the shared hover tip (see {@see bindTips}). A native `title` is too slow to
     * surface and cannot be styled, so the scripted tip carries the detail a chunk is too small to show.
     *
     * A sole chunk (`multi` false) keeps its level colour but drops the tip and the hover: it is the whole
     * message, and the row's copy button already offers what a hover would.
     */
    function chunkHtml(m, withTest, multi) {
        var content = esc(m.content.replace(/\n$/, ''));
        if (!multi) {
            return '<span class="chunk lv-' + esc(m.level) + '">' + content + '</span>';
        }

        var from = withTest && m.test ? ' · ← ' + m.test.name + (m.attempt ? ' #' + m.attempt : '') : '';
        var tip = m.channel + ' · +' + m.time.toFixed(3) + 's · ' + m.level + from;
        return '<span class="chunk lv-' + esc(m.level) + '" data-tip="' + esc(tip) + '">' + content + '</span>';
    }

    /** The hover-revealed copy button every log row carries; the row holds the exact text in `data-copy`. */
    function copyBtn() {
        return '<button class="copy-btn" type="button" data-tip="Copy" aria-label="Copy message">⧉</button>';
    }

    /* --------------------------------------------------------------- tab: channels */

    function renderChannels() {
        var selected = route.params.channel || '';
        var level = route.params.level || '';
        var q = (route.params.q || '').toLowerCase();
        var levels = report.levels;

        // Levels arrive most severe first, so "warning and above" keeps everything at or before it.
        var lines = model.messages.filter(function (m) {
            if (selected && m.channel !== selected) { return false; }
            if (level && levels.indexOf(m.level) > levels.indexOf(level)) { return false; }
            if (q && m.content.toLowerCase().indexOf(q) < 0) { return false; }
            return true;
        });

        var names = Object.keys(model.channels).sort();

        return '<div class="filters">'
            + '<input type="search" id="c-q" placeholder="Search output…" value="' + esc(route.params.q || '') + '">'
            + '<div class="chips">' + levels.map(function (l) {
                return '<button class="chip" data-level="' + l + '" aria-pressed="' + (level === l) + '">'
                    + esc(l) + ' +</button>';
            }).join('') + '</div>'
            + '<span class="count reset">' + lines.length + ' of ' + model.messages.length + ' messages'
            + (selected || level || q ? ' · <a href="' + href('channels', {}) + '">reset</a>' : '') + '</span>'
            + '</div>'

            + '<div class="panes" style="grid-template-columns:minmax(220px,260px) minmax(0,1fr)">'
            + '<section class="card"><header>Channels</header><div class="channel-picker">'
            + '<button data-channel="" aria-pressed="' + (selected === '') + '">'
            + '<span class="ico">≡</span> merged view <span class="n">' + model.messages.length + '</span></button>'
            + names.map(function (name) {
                var ch = model.channels[name];
                return '<button data-channel="' + esc(name) + '" aria-pressed="' + (selected === name) + '">'
                    + '<span class="swatch" style="width:3px;height:14px;border-radius:2px;background:'
                    + esc(ch.color) + '"></span>'
                    + '<span style="color:' + esc(ch.color) + '">' + ch.icon + '</span> ' + esc(name)
                    + '<span class="n">' + ch.count + '</span></button>';
            }).join('')
            + '</div></section>'

            + '<section class="card"><header>' + (selected ? esc(selected) : 'Merged output')
            + '<span class="hint">time is relative to the run start</span></header>'
            + '<div class="body">' + (lines.length ? logHtml(lines, true)
                : '<div class="empty">No messages match the filter.</div>') + '</div></section>'
            + '</div>';
    }

    /* --------------------------------------------------------------- tab: timeline */

    /** Lane magnification. 1× fits the run in the window; the rest scroll horizontally. */
    var TL_ZOOMS = [1, 2, 4, 8, 16];

    function renderTimeline() {
        var run = report.run;
        // The axis has to cover the last test that finished, which under concurrency can outlast the
        // wall clock the loop measured; a shorter span would draw bars past the right edge.
        var span = Math.max(run.duration, model.tests.reduce(function (a, t) {
            return t.startedAt === null ? a : Math.max(a, t.startedAt + t.duration);
        }, 0)) || 1;

        // A test that never announced a start cannot be placed, and guessing a position would be a lie.
        var rows = model.tests.filter(function (t) { return t.startedAt !== null; })
            .sort(function (a, b) { return a.startedAt - b.startedAt || a.id.localeCompare(b.id); });
        var unplaced = model.tests.length - rows.length;

        var tools = '<div class="tl-tools"><span>Zoom</span>' + TL_ZOOMS.map(function (zoom) {
            return '<button class="chip" data-zoom="' + zoom + '" aria-pressed="'
                + (zoom === 1) + '">' + zoom + '×</button>';
        }).join('') + '</div>';

        return '<div class="grid">' + card('Timeline',
            'bars are positioned by start time; overlaps are concurrency',
            '<div class="timeline" data-span="' + span + '">'
            + tools
            // The axis is filled by bindTimeline(): its tick step follows the lane's pixel width,
            // which only the laid-out element knows.
            + '<div class="tl-scroll"><div class="tl-canvas">'
            + '<div class="tl-head"><div class="tl-axis"></div></div>'
            + rows.map(function (test) {
                var left = test.startedAt / span * 100;
                var width = Math.max(test.duration / span * 100, 0.4);
                return '<div class="tl-row">'
                    + '<a class="label" href="' + href('tests', {}, test.id) + '" title="' + esc(test.id) + '">'
                    + '<span class="ico s-' + test.status + '">' + STATUS[test.status].icon + '</span> '
                    + esc(test.name) + '</a>'
                    + '<div class="tl-lane"><span class="bar bg-' + test.status + '" style="left:' + left
                    + '%;width:' + width + '%" title="' + esc(test.name + ' — ' + STATUS[test.status].label
                        + ', ' + dur(test.duration) + ' at +' + dur(test.startedAt)) + '"></span></div>'
                    + '</div>';
            }).join('')
            + '</div></div></div>'
            + (unplaced === 0 ? '' : '<div class="notice" style="margin-top:14px"><span class="ico">⚠</span>'
                + '<span>' + unplaced + ' test(s) announced no start and cannot be placed on the timeline.'
                + '</span></div>')
            + '<div class="notice info" style="margin-top:14px"><span class="ico">i</span><span>'
            + 'Wall clock <b>' + dur(run.duration) + '</b>, summed test time <b>' + dur(run.testDuration)
            + '</b>. The excess is overlap, not double counting.</span></div>') + '</div>';
    }

    /**
     * Ticks for a lane `laneWidth` pixels wide covering `span` seconds. The step is the smallest
     * round unit that keeps labels ~70px apart, so zooming in subdivides the axis instead of
     * stretching five-second gaps, and zooming out never overlaps two labels.
     */
    function tlTicks(span, laneWidth) {
        var units = [0.001, 0.002, 0.005, 0.01, 0.02, 0.05, 0.1, 0.25, 0.5, 1, 2, 5, 10, 30, 60, 300, 600];
        var wanted = span * 70 / Math.max(laneWidth, 1);
        var step = units[units.length - 1];
        for (var i = 0; i < units.length; i++) {
            if (units[i] >= wanted) { step = units[i]; break; }
        }

        var digits = step >= 1 ? 0 : step >= 0.1 ? 1 : step >= 0.01 ? 2 : 3;
        var out = [];
        for (var t = 0; t <= span; t += step) {
            out.push('<span class="tick" style="left:' + (t / span * 100) + '%">'
                + t.toFixed(digits) + 's</span>');
        }

        return out.join('');
    }

    /* ---------------------------------------------------------------- tab: benches */

    function renderBenches() {
        if (!model.benches.length) {
            return '<div class="grid">' + card('Benches', null, '<div class="empty">No benches in this run.</div>') + '</div>';
        }
        return '<div class="grid">' + model.benches.map(function (test) {
            var calls = test.bench.cases.length ? test.bench.cases[0].calls : 0;
            return card(test.case.name.split('\\').pop() + '::' + test.name,
                test.bench.iterations + ' iterations × ' + calls + ' calls',
                (test.description ? '<div style="color:var(--ink-2);margin-bottom:12px">'
                    + esc(test.description) + '</div>' : '')
                + benchHtml(test.bench)
                + '<div style="margin-top:12px"><a href="' + href('tests', {}, test.id) + '">open the test →</a></div>');
        }).join('') + '</div>';
    }

    /**
     * Times are microseconds, the unit the bench plugin measures in. `meanDiff` is the percentage against
     * the fastest case, which is the comparison a reader makes; the bar shows the same thing as a length.
     */
    function benchHtml(bench) {
        var slowest = bench.cases.reduce(function (a, c) { return Math.max(a, c.mean); }, 1);
        var rejected = bench.cases.some(function (c) { return c.rejected > 0; });

        var out = '<table class="grid-table"><thead><tr>'
            + '<th>Case</th><th>mean, relative</th><th class="num">mean</th>'
            + '<th class="num">median</th><th class="num">rstdev</th><th class="num">vs fastest</th>'
            + (rejected ? '<th class="num">rejected</th><th class="num">mean*</th>' : '')
            + '</tr></thead><tbody>'
            + bench.cases.map(function (c) {
                return '<tr>'
                    + '<td>' + esc(c.name) + (c.place === 1 ? ' <span class="pill">fastest</span>' : '') + '</td>'
                    + '<td style="width:150px"><div class="track" style="height:8px;background:var(--grid);'
                    + 'border-radius:4px;overflow:hidden"><div style="height:8px;border-radius:0 4px 4px 0;'
                    + 'background:var(--accent);width:' + (c.mean / slowest * 100) + '%"></div></div></td>'
                    + '<td class="num' + (c.place === 1 ? ' best' : '') + '">' + micros(c.mean) + '</td>'
                    + '<td class="num">' + micros(c.median) + '</td>'
                    + '<td class="num">±' + c.rstdev.toFixed(1) + '%</td>'
                    + '<td class="num">' + (c.place === 1 ? '—' : '+' + c.meanDiff.toFixed(1) + '%') + '</td>'
                    + (rejected
                        ? '<td class="num">' + c.rejected + '</td>'
                        + '<td class="num">' + micros(c.filteredMean) + '</td>'
                        : '')
                    + '</tr>';
            }).join('') + '</tbody></table>';

        if (bench.diagnostics && bench.diagnostics.length) {
            out += '<div style="margin-top:12px;display:grid;gap:8px">'
                + bench.diagnostics.map(function (d) {
                    var icon = d.severity === 'danger' ? '✕' : d.severity === 'warning' ? '⚠' : 'i';
                    return '<div class="notice' + (d.severity === 'notice' ? ' info' : '') + '">'
                        + '<span class="ico sev-' + esc(d.severity) + '">' + icon + '</span>'
                        + '<span><span class="sev sev-' + esc(d.severity) + '" style="font-weight:600">'
                        + esc(d.severity) + '</span> · <code>' + esc(d.kind) + '</code> · '
                        + esc(d.case) + ' — ' + esc(d.reason)
                        + (d.advice ? ' <span style="color:var(--muted)">' + esc(d.advice) + '</span>' : '')
                        + '</span></div>';
                }).join('') + '</div>';
        }

        return out;
    }

    /** Microseconds as the bench plugin reports them, scaled to whatever reads shortest. */
    function micros(value) {
        if (value >= 1000000) { return (value / 1000000).toFixed(2) + ' s'; }
        if (value >= 1000) { return (value / 1000).toFixed(2) + ' ms'; }
        return value.toFixed(2) + ' µs';
    }

    /* -------------------------------------------------------------------- tab: env */

    function renderEnv() {
        var e = report.environment;
        var c = e.config;

        return '<div class="grid cols-2">'
            + card('Runtime', null, '<dl class="kv-list">'
                + kv('PHP', esc(e.php + ' (' + e.sapi + ')'))
                + kv('Testo', esc(e.testo))
                + kv('OS', esc(e.os))
                + (e.cpu ? kv('CPU', esc(e.cpu)) : '')
                + kv('Working directory', esc(e.cwd))
                + kv('Extensions', Object.keys(e.extensions).map(function (name) {
                    return '<span class="tag' + (e.extensions[name] ? '' : ' neg') + '">' + esc(name) + ' '
                        + esc(e.extensions[name] || 'absent') + '</span>';
                }).join(''))
                + '</dl>')
            + card('Effective run configuration', 'what this run was asked to do', '<dl class="kv-list">'
                + kv('Config file', esc(c.file || 'none'))
                + kv('Suites', c.suites.length
                    ? c.suites.map(function (s) { return '<span class="tag">' + esc(s) + '</span>'; }).join('')
                    : '<span style="color:var(--muted)">none configured</span>')
                + kv('Options', optionTags(c.options))
                + kv('Arguments', optionTags(c.arguments))
                + kv('Schema version', String(report.schemaVersion))
                + '</dl>'
                + '<div class="notice info" style="margin-top:12px"><span class="ico">i</span><span>'
                + 'Only the options this run was actually given are listed; the environment is never '
                + 'recorded, so a shared report carries no tokens.</span></div>')
            + '</div>';
    }

    /**
     * CLI values as tags. A negated group or type (`!slow`) is a decision to exclude and reads as one.
     */
    function optionTags(values) {
        var keys = Object.keys(values || {});
        if (!keys.length) { return '<span style="color:var(--muted)">none</span>'; }

        return keys.map(function (key) {
            var value = values[key];
            var items = Array.isArray(value) ? value : [value];

            return items.map(function (item) {
                var text = item === true ? key : key + '=' + item;
                var negated = typeof item === 'string' && item.charAt(0) === '!';
                return '<span class="tag' + (negated ? ' neg' : '') + '">' + esc(text) + '</span>';
            }).join('');
        }).join('');
    }

    function kv(k, v) { return '<dt>' + esc(k) + '</dt><dd>' + v + '</dd>'; }

    /* ------------------------------------------------------------------ rendering */

    function render() {
        route = parseHash();
        // A real navigation supersedes whatever was being typed; replaceState never gets here.
        liveQuery = null;
        // The hovered element is about to be replaced; drop any tip anchored to it before it vanishes.
        hideTip();
        renderChrome();

        var body = el('view');
        switch (route.tab) {
            case 'tests': body.innerHTML = renderTests(); applyTestFilter(); break;
            case 'channels': body.innerHTML = renderChannels(); break;
            case 'timeline': body.innerHTML = renderTimeline(); break;
            case 'benches': body.innerHTML = renderBenches(); break;
            case 'env': body.innerHTML = renderEnv(); break;
            default: body.innerHTML = renderOverview();
        }

        bindView();
        document.title = 'Testo report — ' + report.run.status + ' · ' + totalTests() + ' tests';
    }

    function bindView() {
        bindTestSearch();
        bindChannelSearch();
        bindTree();
        bindOutputFilter();
        bindTimeline();

        bindSelect('f-suite', 'suite');
        bindSelect('f-type', 'type');
        bindSelect('f-group', 'group');

        // Scoped to the filter bar and the channel picker on purpose. A test row carries its own
        // data-status and data-type, so an unscoped selector makes every click on a test double as a
        // click on the chip of that test's status.
        each('.filters [data-status]', function (btn) {
            btn.addEventListener('click', function () {
                var active = activeStatuses();
                var s = btn.getAttribute('data-status');
                var i = active.indexOf(s);
                if (i >= 0) { active.splice(i, 1); } else { active.push(s); }
                patchParams({status: active.join(',')});
            });
        });

        each('.channel-picker [data-channel]', function (btn) {
            btn.addEventListener('click', function () {
                patchParams({channel: btn.getAttribute('data-channel')});
            });
        });

        each('.filters [data-level]', function (btn) {
            btn.addEventListener('click', function () {
                var l = btn.getAttribute('data-level');
                patchParams({level: route.params.level === l ? '' : l});
            });
        });
    }

    /**
     * The test search filters the rendered list in place and writes the query back into the hash on a
     * debounce with `replaceState`, which does not fire `hashchange` — so nothing re-renders under the
     * caret and the URL still carries the filter state.
     */
    function bindTestSearch() {
        var input = el('f-q');
        if (!input) { return; }

        var sync = debounce(function () {
            var params = {};
            Object.keys(route.params).forEach(function (k) { params[k] = route.params[k]; });
            params.q = liveQuery === '' ? null : liveQuery;
            route.params.q = params.q === null ? undefined : params.q;
            try {
                history.replaceState(null, '', href(route.tab, params, route.test));
            } catch (e) {
                // A browser that refuses the history API over file:// just keeps the older hash.
            }
        }, 250);

        input.addEventListener('input', function () {
            liveQuery = input.value;
            applyTestFilter();
            sync();
        });
    }

    /** The channel log is a plain re-render — there is no tree to keep, so navigation is enough. */
    function bindChannelSearch() {
        var input = el('c-q');
        input && input.addEventListener('input', debounce(function () {
            patchParams({q: input.value});
        }, 250));
    }

    function bindTree() {
        each('.tree .head', function (head) {
            head.addEventListener('click', function () {
                head.parentNode.classList.toggle('collapsed');
            });
        });

        // Selecting a test keeps the filters that led to it, while its href stays the canonical deep link.
        each('.tree .test', function (row) {
            row.addEventListener('click', function (event) {
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) { return; }
                event.preventDefault();
                go(route.tab, route.params, row.getAttribute('data-test'));
            });
        });
    }

    function bindOutputFilter() {
        var root = el('test-output');
        if (!root) { return; }

        var chips = root.querySelectorAll('[data-ch-filter]');
        var lines = root.querySelectorAll('.log .line');

        function apply() {
            var on = [];
            Array.prototype.forEach.call(chips, function (chip) {
                if (chip.getAttribute('aria-pressed') === 'true') { on.push(chip.getAttribute('data-ch-filter')); }
            });
            Array.prototype.forEach.call(lines, function (line) {
                var keep = !on.length || on.indexOf(line.getAttribute('data-ch')) >= 0;
                line.classList.toggle('is-hidden', !keep);
            });
        }

        Array.prototype.forEach.call(chips, function (chip) {
            var name = chip.getAttribute('data-ch-filter');

            chip.addEventListener('click', function () {
                chip.setAttribute('aria-pressed', chip.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
                apply();
            });

            chip.addEventListener('mouseenter', function () {
                Array.prototype.forEach.call(lines, function (line) {
                    var match = line.getAttribute('data-ch') === name;
                    line.classList.toggle('hl', match);
                    line.classList.toggle('dim', !match);
                });
            });

            chip.addEventListener('mouseleave', function () {
                Array.prototype.forEach.call(lines, function (line) {
                    line.classList.remove('hl');
                    line.classList.remove('dim');
                });
            });
        });
    }

    /**
     * Zoom writes a CSS variable instead of re-rendering, so the horizontal scroll position and the
     * spot the reader was looking at both survive. Only the axis is redrawn, since its tick step is a
     * function of the lane width the zoom just changed.
     */
    function bindTimeline() {
        var root = document.querySelector('.timeline');
        if (!root) { return; }

        each('.tl-tools [data-zoom]', function (btn) {
            btn.addEventListener('click', function () {
                root.style.setProperty('--tl-zoom', btn.getAttribute('data-zoom'));
                each('.tl-tools [data-zoom]', function (other) {
                    other.setAttribute('aria-pressed', String(other === btn));
                });
                drawTimelineAxis();
            });
        });

        drawTimelineAxis();
    }

    /** No-op off the timeline tab, so the window resize listener can call it unconditionally. */
    function drawTimelineAxis() {
        var root = document.querySelector('.timeline');
        var axis = root && root.querySelector('.tl-axis');
        if (!axis) { return; }

        axis.innerHTML = tlTicks(parseFloat(root.getAttribute('data-span')), axis.clientWidth);
    }

    function bindSelect(id, param) {
        var s = el(id);
        if (s) { s.addEventListener('change', function () { patchParams(makePatch(param, s.value)); }); }
    }

    function makePatch(k, v) { var p = {}; p[k] = v; return p; }

    function debounce(fn, ms) {
        var timer = null;
        return function () {
            clearTimeout(timer);
            timer = setTimeout(fn, ms);
        };
    }

    /* ---------------------------------------------------------------------- theme */

    function initTheme() {
        var stored = null;
        try { stored = localStorage.getItem('testo-report-theme'); } catch (e) { /* file:// may deny it */ }
        if (stored) { document.documentElement.setAttribute('data-theme', stored); }

        el('theme').addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme');
            var dark = current
                ? current === 'dark'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;
            var next = dark ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('testo-report-theme', next); } catch (e) { /* ignore */ }
        });
    }

    /* ----------------------------------------------------------------------- tip */

    // One floating tooltip shared by the whole report: any element carrying a `data-tip` attribute shows
    // it on hover, faster and better-styled than a native `title`. The tip is text, never markup — a
    // producer composes what it wants to say (see `chunkHtml`) and the mechanism stays a plain lookup.
    var tipEl = null;
    var tipHost = null;

    function ensureTip() {
        if (tipEl) { return tipEl; }
        tipEl = document.createElement('div');
        tipEl.className = 'tip';
        tipEl.setAttribute('role', 'tooltip');
        document.body.appendChild(tipEl);
        return tipEl;
    }

    function showTip(text, x, y) {
        var tip = ensureTip();
        tip.textContent = text;
        tip.classList.add('on');
        moveTip(x, y);
    }

    function hideTip() {
        tipHost = null;
        tipEl && tipEl.classList.remove('on');
    }

    /** Follows the cursor, flipping to the other side of it near a viewport edge so it never clips. */
    function moveTip(x, y) {
        if (!tipEl) { return; }
        var pad = 10;
        var w = tipEl.offsetWidth;
        var h = tipEl.offsetHeight;
        var left = x + 14;
        var top = y + 16;
        left + w + pad > window.innerWidth && (left = x - w - 14);
        top + h + pad > window.innerHeight && (top = y - h - 16);
        tipEl.style.left = Math.max(pad, left) + 'px';
        tipEl.style.top = Math.max(pad, top) + 'px';
    }

    /**
     * Delegated once, for good: views re-render on every navigation, so binding per element (or per
     * render) would pile up listeners on pages the user reopens all day. `mouseout` consults where the
     * pointer went so moving between a host's own children never flickers the tip.
     */
    function bindTips() {
        document.addEventListener('mouseover', function (e) {
            var host = e.target.closest && e.target.closest('[data-tip]');
            if (host && host !== tipHost) {
                tipHost = host;
                showTip(host.getAttribute('data-tip'), e.clientX, e.clientY);
            }
        });
        document.addEventListener('mousemove', function (e) {
            tipHost && moveTip(e.clientX, e.clientY);
        });
        document.addEventListener('mouseout', function (e) {
            var to = e.relatedTarget;
            tipHost && (!to || !tipHost.contains(to)) && hideTip();
        });
    }

    /* ---------------------------------------------------------------------- copy */

    /**
     * Copies text, favouring the async Clipboard API and falling back to a hidden textarea — a report
     * opened over `file://` is not a secure context, so `navigator.clipboard` is often absent there.
     */
    function copyText(text, done) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(
                function () { done(true); },
                function () { done(fallbackCopy(text)); },
            );
            return;
        }
        done(fallbackCopy(text));
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.top = '-1000px';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();

        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(ta);

        return ok;
    }

    /** Brief acknowledgement on the button itself — a tick that took, a cross that did not. */
    function flashCopied(btn, ok) {
        if (btn.classList.contains('copied') || btn.classList.contains('failed')) { return; }
        var glyph = btn.textContent;
        btn.textContent = ok ? '✓' : '✗';
        btn.classList.add(ok ? 'copied' : 'failed');
        setTimeout(function () {
            btn.textContent = glyph;
            btn.classList.remove('copied', 'failed');
        }, 1100);
    }

    /** Delegated once: every log row's copy button hands its row's `data-copy` to the clipboard. */
    function bindCopy() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('.copy-btn');
            if (!btn) { return; }
            e.preventDefault();

            var line = btn.closest('.line');
            copyText(line ? (line.getAttribute('data-copy') || '') : '', function (ok) {
                flashCopied(btn, ok);
            });
        });
    }

    /* ----------------------------------------------------------------------- boot */

    function boot() {
        if (!report || Math.floor(report.schemaVersion) !== SUPPORTED_SCHEMA_MAJOR) {
            document.body.innerHTML = '<div class="schema-error">'
                + '<h2 style="margin:0 0 8px">Unsupported report schema</h2>'
                + '<p style="margin:0">This renderer supports schema major <b>' + SUPPORTED_SCHEMA_MAJOR
                + '</b>, the document declares <b>' + esc(report ? report.schemaVersion : 'nothing')
                + '</b>. Refusing to render a half-broken page.</p></div>';
            return;
        }

        indexModel();
        initTheme();
        bindTips();
        bindCopy();
        window.addEventListener('hashchange', render);
        // The tick step is measured in pixels, so a resized window needs a fresh axis.
        window.addEventListener('resize', debounce(drawTimelineAxis, 150));
        render();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
