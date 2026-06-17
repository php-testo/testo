<p align="center">
    <a href="https://github.com/php-testo/testo"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">Test filtering plugin</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

</div>

<br />

> [!IMPORTANT]
> ## 🪞 This is a read-only mirror.
>
> Active development of the Testo project lives in [**php-testo/testo**](https://github.com/php-testo/testo) under `plugin/filter/`. This repository is **automatically synchronized** from there on every release.
>
> File issues and pull requests in the [main monorepo](https://github.com/php-testo/testo/issues), not here.

## About

Selects which tests run on a given invocation by name patterns, paths, suites, types, dataset pointers, and groups. Powers Testo's `--filter`, `--path`, `--suite`, `--type`, and `--group` CLI flags.

## Groups

Label tests with the `#[Group]` attribute and select them with `--group`:

```php
use Testo\Filter\Group;
use Testo\Test;

#[Test]
#[Group('integration')]      // every test of this class inherits "integration"
final class OrderTest
{
    #[Group('slow')]         // groups: integration, slow
    public function importsLargeDataset(): void { /* ... */ }

    public function createsOrder(): void { /* ... */ } // groups: integration
}
```

The attribute targets classes, methods, and functions and accepts several names at once
(`#[Group('db', 'slow')]`). A test's group set is the union of all groups reachable from it:
its own method (and any overridden parent method), the test class, its parent classes, and traits.

```bash
# Run only tests in the "db" or "integration" group (OR logic)
./vendor/bin/testo run --group=db --group=integration

# Exclude a group with the "!" prefix (run everything except "slow")
./vendor/bin/testo run --group=!slow

# Combine with other filters (AND between filter types)
./vendor/bin/testo run --group=db --filter=OrderTest
```

Exclusion (`!`) takes precedence over inclusion. Group filters combine with name/path/suite/type
filters using AND logic.

## Install

```bash
composer require --dev testo/filter
```

[![PHP](https://img.shields.io/packagist/php-v/testo/filter.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/filter)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/filter.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/filter)
[![License](https://img.shields.io/packagist/l/testo/filter.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/filter.svg?style=flat-square)](https://packagist.org/packages/testo/filter/stats)
