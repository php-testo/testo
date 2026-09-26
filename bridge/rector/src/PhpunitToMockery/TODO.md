# PHPUnit mocks -> Mockery: outstanding / partial conversions

The `phpunit-to-mockery` set moves PHPUnit doubles onto Mockery, verified after every test by
`testo/bridge-mockery` (register `MockeryPlugin`, or nothing calls `Mockery::close()`). The class docblock
of `CreateMockToMockeryRector` carries the full map.

## Implemented

- **CreateMockToMockeryRector** (registered). Creation, read by the shared `Internal\PhpunitMockFactory`:
  `createMock`/`createStub`/intersections/the constructor-disabling builder →
  `\Mockery::mock(...)->shouldIgnoreMissing()` — a PHPUnit double answers an unconfigured call with a
  type-appropriate default, so does an ignore-missing Mockery mock, and a plain `Mockery::mock()` throws;
  `disableAutoReturnValueGeneration()` keeps the plain, throwing mock. `createConfiguredMock(X, $map)` →
  `\Mockery::mock(X, $map)->shouldIgnoreMissing()` (quick definitions). A partial — `createPartialMock(X,
  ['a'])`, `onlyMethods(['a'])` — becomes the traditional partial `\Mockery::mock('App\X[a]')`, with the
  constructor arguments as the second argument when the builder runs the constructor (`[]` when it runs
  it without arguments); an empty method list is `->makePartial()`. The chain: `expects($matcher)->method('m')`
  / `method('m')` → `shouldReceive('m')` plus the count; `withAnyParameters()` drops; the returns map onto
  `andReturn`/`andThrow`/`andReturnUsing`/`andReturnArg`/`andReturnSelf`, `willReturnMap` →
  `andReturnUsing(<lookup>)`, legacy `will(...)` included. `with()` constraints: a plain value and `equalTo`
  stay plain (both loose), `anything`→`any`, `identicalTo`→`isSame`, `isInstanceOf`→`type`, the type
  checks Mockery's `is_*()` agrees on → `type` (`isType('integer')`, `isInt()`, …), `callback`→`on`,
  `matchesRegularExpression`→`pattern`, `arrayHasKey`→`hasKey`, `contains`/`containsEqual`→`hasValue`,
  `isNull`/`isTrue`/`isFalse`→`isSame(literal)`. Everything else becomes `Mockery::on()` over the
  expression `Internal\PhpunitConstraint` builds (see `../PhpunitToDouble/TODO.md` for the list and its
  guards), including `logicalAnd`/`logicalXor` and a `resource` type check (Mockery's `is_resource()`
  rejects a closed resource, PHPUnit's does not). `with()` itself ends in `\Mockery::andAnyOtherArgs()`
  (`...[\Mockery::andAnyOtherArgs()]` after a spread): PHPUnit ignores the call arguments past the listed
  ones, Mockery's `with()` requires the exact count. An empty `with()` constrains nothing in PHPUnit and
  drops out like `withAnyParameters()` (in Mockery it would demand a call with no arguments).

## Residuals by design

- An omitted optional argument: PHPUnit fills in the parameter's default, `andAnyOtherArgs()` pads the
  call with `null`. The two agree when the default is `null`; otherwise `with()` listing the default
  matches only in PHPUnit, and `with(null)` matches only in Mockery.

## Left for manual migration

- A variable invocation matcher, `prophesize()`, `getMockForAbstractClass()`, `addMethods()`,
  `setMockClassName()`, the bare constructor-calling `getMockBuilder(X)->getMock()`, a partial with a
  computed class or method name (Mockery's partial target is a literal string), `withConsecutive()`,
  `willReturnReference()`.
- The constraints with no fixed form: see `../PhpunitToDouble/TODO.md`.
