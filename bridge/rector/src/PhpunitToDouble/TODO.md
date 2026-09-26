# PHPUnit mocks -> Double: outstanding / partial conversions

The `phpunit-to-double` set moves PHPUnit doubles onto Double (`testo/bridge-double`). The class
docblock of `CreateMockToDoubleRector` carries the full map; this file records what is partial and what
stays manual.

## Stubbed (not registered)

- **UnconvertibleMockToDoubleRector** — the forms with no faithful Double target: `prophesize()` (a
  different creation/expectation model); `getMockForAbstractClass()`/`getMockForTrait()` (the abstract
  methods would need an `allows()` each, which depends on the class); `addMethods()`, `setMockClassName()`
  and the bare constructor-calling `getMockBuilder(X)->getMock()`; a partial double with
  `disableAutoReturnValueGeneration()` (Double has no per-method strictness); a configured or partial
  double that is not assigned to a variable or property; a variable invocation matcher;
  `withConsecutive()`; `willReturnReference()`; and the `with()` constraints with no fixed form —
  `stringContains()` with a computed case flag or line-ending normalisation, `isType()`/`containsOnly()`
  with a computed or unknown type, `matches()` format descriptions. Replace manually with the matching
  Double form, a third-party mocking library, or a hand-written fake.

## Implemented

- **CreateMockToDoubleRector** (registered). Creation is read by the shared `Internal\PhpunitMockFactory`:
  `createMock`/`createStub`, the intersection factories and the constructor-disabling builder →
  `Double::for(...)`; `disableAutoReturnValueGeneration()` → `->strict()`; `disableOriginalClone()` and
  `disableArgumentCloning()` drop (Double clones neither). `createConfiguredMock(X, ['m' => $v])` becomes
  `Double::for(X)` plus an `allows('m')->returns($v)` statement per entry; `createPartialMock(X, ['a'])` and
  `onlyMethods(['a'])` become `Double::for(X)->passthru()` plus `allows('a')` per doubled method (an
  `allows()` without `returns()` answers with the safe default, whatever the mode), and a builder that runs
  the constructor copies its state through `passthru(new X(...$constructorArgs))`. The configuration chain
  maps as before, plus `willReturnMap`/`returnValueMap` → `resolves(<lookup>)` (`Internal\ReturnValueMap`,
  `ReturnValueMap`'s own first-identical-row lookup). `with()` constraints keep their dedicated
  `Argument::*` matchers and fall back to `Argument::satisfies()` over the PHP expression
  `Internal\PhpunitConstraint` builds for every other constraint: `logicalAnd`/`logicalXor`, delta,
  case-insensitive and canonicalizing equality, `countOf`, `isList`, `isJson`, `isNan`/`isFinite`/
  `isInfinite`, the file constraints, `containsIdentical`/`containsOnly*`, `objectEquals`, and PHPUnit
  12's `isInt()`…`isString()` factories. Those expressions reproduce PHPUnit's own guards (a string
  constraint rejects a non-string, `ArrayHasKey` accepts an `ArrayAccess`, `IsEmpty` counts a
  `Countable`, `isType('resource')` accepts a closed resource); `PhpunitConstraintTest` pins the verdicts
  PHPUnit gives on the edge values. `isType()` names Double's `type()` spells differently are normalised
  (`integer` → `int`, `boolean` → `bool`, `double` → `float`); the rest become predicates. The predicate
  closures name their parameter `$value`, or `$value2`, … when the statement or its scope already uses
  `$value`. PHPUnit's `with()` checks only the listed arguments and ignores extra ones, while Double
  compares the arity exactly and an unmatched `allows()` answers null without complaint, so the
  rebuilt `with()` ends in `Argument::remaining()` (appended as `...[Argument::remaining()]` after a
  spread). An empty `with()` constrains nothing in PHPUnit and drops out like `withAnyParameters()`.

## Residuals by design

- Comparison: Double matches a plain value with `===` for scalars, PHPUnit's `equalTo` with `==`.
- `willReturnMap` sees the arguments as passed; PHPUnit also fills in the defaults of omitted optional
  parameters, so a map row listing a default only matches in PHPUnit.
- `with()` sees the arguments as passed too: PHPUnit fills in the defaults of omitted optional
  parameters, so `with('abc', null)` matches a `getValue('abc')` call on `getValue($field, $default = null)`
  in PHPUnit and not in Double.
- `equalToWithDelta`/`equalToCanonicalizing` reproduce PHPUnit on numbers and on flat arrays; the
  recursive comparison of nested arrays is not reproduced.
- A double is not tracked across statements: when one statement on a double stays PHPUnit while its
  creation converts, that statement needs a hand fix.
