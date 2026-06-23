<p align="center">
    <a href="https://github.com/php-testo/testo"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">Spec-driven plugin</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

</div>

<br />

> [!IMPORTANT]
> ## 🪞 This is a read-only mirror.
>
> Active development of the Testo project lives in [**php-testo/testo**](https://github.com/php-testo/testo) under `plugin/spec/`. This repository is **automatically synchronized** from there on every release.
>
> File issues and pull requests in the [main monorepo](https://github.com/php-testo/testo/issues), not here.

## About

Blends **BDD**, **Spec-Driven** and **TDD** workflows: write the behaviour you expect as a `#[Spec(...)]`
fragment — a user story or a slice of the product specification — right next to the test that proves it.

At runtime every fragment is published to the `spec.md` messenger channel, so it travels with the test
output. When you want living documentation, flip on generation (the `--spec` flag or the plugin's
`collect` option) and Testo renders the collected fragments into Markdown files, one per Test Case.

## Install

```bash
composer require --dev testo/spec
```

[![PHP](https://img.shields.io/packagist/php-v/testo/spec.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/spec)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/spec.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/spec)
[![License](https://img.shields.io/packagist/l/testo/spec.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/spec.svg?style=flat-square)](https://packagist.org/packages/testo/spec/stats)

## Usage

Attach a spec fragment to a test (method, function, or a whole class). `#[Spec]` carries the
*content* (the story), `#[SpecHeader]` carries the *heading and number*:

```php
use Testo\Spec;
use Testo\Spec\SpecHeader;
use Testo\Test;

#[Test]
#[SpecHeader(title: 'Checkout', number: '5')]   // class = numbered section
final class CheckoutTest
{
    #[Spec(
        story: <<<'MD'
            **As a** customer
            **I want** my cart total to include tax
            **so that** the price I pay matches the price I see.
            MD,
        tags: ['checkout', 'JIRA-128'],
    )]
    public function totalIncludesTax(): void  // -> item 5.1
    {
        // ...assertions that prove the story...
    }

    #[Test]
    #[Spec(story: 'A valid coupon lowers the total.')]
    #[SpecHeader(title: 'Coupon applies')]    // override the item title, still auto-numbered -> 5.2
    public function couponApplies(): void
    {
        // ...
    }
}
```

### Numbering & ordering

- `#[SpecHeader]` on a **class** opens a section: `number` is the section number (maps onto your
  external spec document), `title` is the heading. Either may be omitted — a section with no number
  falls to the end, a section with no title falls back to the class name.
- `#[SpecHeader]` on a **method** overrides that item's title and/or pins its number.
- Items are auto-numbered `{section}.{n}` in source order; a pinned method number is kept as-is.
- **Collisions** (e.g. two cases sharing a section number) are disambiguated with a ` (1)`, ` (2)` …
  suffix in document order — numbers are never silently dropped.

The example above renders to:

```markdown
# 5. Checkout

## 5.1 totalIncludesTax

**As a** customer
**I want** my cart total to include tax
**so that** the price I pay matches the price I see.

_Tags: `checkout` `JIRA-128`_

## 5.2 Coupon applies

A valid coupon lowers the total.
```

Sections without a number are gathered into a trailing **Uncategorized** block — items with a header
render as a bullet list, items without one as plain paragraphs.

### Execution order follows the numbers

By default the plugin also **reorders test execution** to match the document: Test Cases run in
section-number order, and tests within a case run in item-number order (unnumbered ones keep source
order and run last). Turn it off with `new SpecPlugin(reorder: false)` if your tests must keep their
discovered order.

### Generate documentation

Register the plugin in `testo.php`:

```php
use Testo\Spec\SpecPlugin;

// In ApplicationConfig::$plugins or a SuiteConfig::$plugins:
new SpecPlugin(outputDir: __DIR__ . '/docs/specs'),
```

Reordering is on as soon as the plugin is registered. File generation is separate — enable it with
`collect: true` on the plugin, or from the CLI:

```bash
# Generate into the plugin's configured directory
vendor/bin/testo --spec

# Generate into a custom directory
vendor/bin/testo --spec-dir=docs/specs
```

The whole run is rendered into a single ordered `spec.md` in the target directory. Even without the
plugin the fragments are still emitted to the `spec.md` channel — generation and reordering are the
optional halves.
