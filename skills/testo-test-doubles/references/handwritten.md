# Hand-written fakes, stubs and spies

Reference for the library-free route of `testo-test-doubles`. A hand-written double is an ordinary
`final class` implementing the collaborator's interface, checked in under `tests/`. It costs a file, and
buys a readable test, a reusable fixture, and zero dependencies. It is the right default for ports you own.

## When to write one

- The collaborator is an **interface in your codebase** (repository, clock, mailer, gateway port).
- The double **holds state** the test reads back (saved entities, sent messages).
- **Three or more tests** would configure the same stub — one fake replaces repeated `allows()` chains.
- **No mocking library** is installed and the test needs one or two simple doubles.

Reach for a library instead when the target is a third-party interface with many methods you would have
to implement, or when call order is the contract (a hand-rolled ordered mock is more code than it is worth).

## Kinds and naming

Name states the kind, prefix first, then the contract it implements:

| Kind | Prefix | Shape |
|---|---|---|
| Fake | `Fake`, `InMemory` | Working implementation with a simplified backend: `InMemoryUserRepository`, `FakeClock` |
| Stub | `Stub`, `Fixed` | Returns what the constructor was given: `FixedRateProvider`, `StubTokenGenerator` |
| Spy | `Spy`, `Recording` | Records every call into public typed lists: `SpyMailer`, `RecordingDispatcher` |
| Dummy | `Null` | Every method a no-op returning the type's neutral value: `NullLogger` |
| Throwing | `Throwing` | Every method throws; for "must not be reached" branches: `ThrowingGateway` |

A fake that also records (an in-memory repository exposing `saved` calls) is still a `Fake`/`InMemory`
— the recording is a convenience, the behaviour is the point.

## Where it lives

- Directory: **`tests/<Suite>/Stub/`** next to the tests that use it (`tests/Unit/Stub/`, or per module
  `tests/Billing/Stub/`). Namespace mirrors the path: `Tests\Unit\Stub\SpyMailer`. Testo's own suites use
  exactly this layout (`tests/Application/Stub/SpyDispatcher.php`, `plugin/codecov/tests/Stub/SpyDriver.php`).
- Make sure `autoload-dev` maps the `Tests\` prefix onto `tests/`; a fake that is not autoloadable fails
  with a class-not-found inside the test.
- Discovery is attribute-based, so a class under a suite's `location` without `#[Test]` is never run as a
  test. Keep `#[Test]`, `#[Group]` and other test attributes off the fake.
- One fake per file, one collaborator per fake. A fake for a second interface is a second class, even
  when the two are always used together.

## Shape

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Stub;

use App\Mail\Mailer;
use App\Mail\Message;

/**
 * Records every message handed to it so tests can assert on what was sent.
 */
final class SpyMailer implements Mailer
{
    /** @var list<Message> */
    public array $sent = [];

    #[\Override]
    public function send(Message $message): void
    {
        $this->sent[] = $message;
    }

    /**
     * @return list<string>
     */
    public function recipients(): array
    {
        return \array_map(static fn(Message $m): string => $m->to, $this->sent);
    }
}
```

```php
final class InMemoryUserRepository implements UserRepository
{
    /** @var array<int, User> */
    private array $users = [];

    public function __construct(User ...$seed)
    {
        foreach ($seed as $user) {
            $this->users[$user->id] = $user;
        }
    }

    #[\Override]
    public function find(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    #[\Override]
    public function save(User $user): void
    {
        $this->users[$user->id] = $user;
    }

    #[\Override]
    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }
}
```

Rules the two examples follow:

- `final class`, **implements the interface** — never `extends` the production class. Extending drags real
  behaviour into the test and breaks the moment the parent gains a constructor dependency.
- `#[\Override]` on every interface method, so a renamed interface method fails at load time rather than
  silently leaving a dead method on the fake.
- **Canned data enters through the constructor**, with defaults so `new FakeX()` works bare.
- **Recorded calls are public, typed lists** (`/** @var list<Message> */ public array $sent`). A getter per
  list adds nothing; a *derived* query (`recipients()`) that saves the test a `array_map` earns its place.
- **No logic the interface does not demand.** A fake repository stores and finds; it does not validate,
  paginate or emit events unless the port's contract says so.
- **Methods the tests never exercise throw**, they do not return `null` silently:

  ```php
  #[\Override]
  public function stream(): iterable
  {
      throw new \LogicException(self::class . '::stream() is not expected in tests');
  }
  ```

  A silent `null` becomes a passing test that exercised nothing.

## Using it in a test

```php
#[Test]
#[Covers(Signup::class)]
final class SignupTest
{
    public function sendsWelcomeMailToNewUser(): void
    {
        $mailer = new SpyMailer();
        $users = new InMemoryUserRepository();
        $signup = new Signup($users, $mailer);

        $signup->register('alice@example.com');

        Assert::same($mailer->recipients(), ['alice@example.com']);
        Assert::instanceOf($users->findByEmail('alice@example.com'), User::class);
    }
}
```

- `#[Covers]` names the **SUT**, never the fake.
- A hand-written spy records nothing into Testo's assertion history. Every check on it goes through
  `Assert::*`, or the test is `Status::Risky` for having asserted nothing.
- Build fakes per test (inline or in `#[BeforeTest]`), not in `#[BeforeClass]`: shared mutable state
  across tests is how order-dependent failures start.

## What to cover — and when the fake needs its own test

The fake must honour the **contract of the interface**, not the behaviour of the production adapter. A
fake clock returns the configured instant; it does not tick. A fake repository returns what was saved; it
does not enforce database constraints.

Write a test **for the fake itself** only when it carries behaviour a test depends on: an in-memory
repository with filtering (`findByEmail`), a fake queue with ordering, a fake clock that advances. Place it
beside the fake's users (`tests/Unit/Stub/InMemoryUserRepositoryTest.php`) and keep it to the contract
methods. A pure recording spy or a fixed-value stub needs no test — its correctness is visible on the page.

When several adapters implement the same port (real Doctrine repository, in-memory fake), a shared
**contract test** run against both is the strongest arrangement: one test class, one `#[DataProvider]`
yielding each implementation. See `testo-data-driven`.
