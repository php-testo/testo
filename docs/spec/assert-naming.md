# Assertion Chain Method Naming

Chain methods on the typed heads (`Assert::string()->…`, `Assert::callable()->…`) read as a sentence about the subject: `Assert::string($s)->contains('foo')`. Pick the name by what the method checks:

| Checks | Form | Examples |
|---|---|---|
| A predicate or a relation to the argument | bare word | `same`, `contains`, `greaterThan` |
| A declared or owned part of the subject | `has*` | `hasKeys`, `hasProperty`, `hasReturnType` |
| A kind, where the bare noun would read like a getter, or a property named by an adjective that is a PHP keyword | `is*` | `isList`, `isStatic` |
| The kind of argument, where a bare verb would be ambiguous | verb + argument noun | `matchesRegex` |
| The negation of any of the above | `not` prefix | `notSame`, `notContains`, `notStatic` |

A `not` method mirrors its positive one: same parameters, same argument meaning, and the positive name right after `not`, without an `is` (`notStartsWith`, `notStatic`). `ArrayType::doesNotHaveKeys()` is the one existing exception; new methods follow the `not` form.
