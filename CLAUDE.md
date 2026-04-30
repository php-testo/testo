## Important

- There is a guideline about writing PHP code: docs/guidelines/how-to-write-php-code-best-practices.mdhow-to-write-php-code-best-practices.md
- To write tests using Testo read https://php-testo.github.io/llms.txt
- About Self-Tests read tests/README.md

## Project Overview

**Testo** is an extensible PHP testing framework designed for projects requiring substantial customization of testing workflows.

### Philosophy
- Name derived from East and South Slavic languages "testo" (dough) - symbolizing malleability and customization
- Core principle: developers deserve complete authority over their testing environments
- Built on minimal core with middleware system for unprecedented extensibility

### Target Audience
Projects requiring significant testing workflow customization:
- SDK development
- Framework tools and libraries
- Complex integrations
- Scenarios where PHPUnit/standard frameworks lack flexibility

### Key Differentiators
1. **Familiar PHP syntax** - No new DSL to learn, standard PHP code
2. **Extensibility first** - Middleware architecture enables deep customization
3. **Minimal core** - Lightweight foundation that remains powerful through extensions

### Core Features
- Attribute-based test configuration (#[Test], #[RetryPolicy], #[ExpectException])
- No base class requirement for test classes
- Built-in dependency injection support
- Memory leak detection capabilities
- Retry policies for flaky tests
- Flexible assertion library
- Symfony Console-based CLI

### Technical Stack
- PHP 8.1+ (leverages modern language features)
- Symfony components (Console, Finder, Process)
- ReactPHP for async operations
- PSR standards compliance (Container, SimpleCache)

## Guidelines Index

### 📖 Documentation Guidelines
**Path:** `docs/guidelines/how-to-translate-readme-docs.md`  
**Value:** Standardizes documentation translation process and multilingual content management  
**Key Areas:**
- Translation workflow using LLMs for documentation
- Multilanguage README pattern using ISO 639-1 codes (`README-{lang_code}.md`)
- Quality standards: preserve technical content, ensure natural language flow
- Review process for translated content
- MAPS framework for complex translations

### 🖥️ Console Command Development
**Path:** `docs/guidelines/how-to-write-console-command.md`  
**Value:** Ensures consistent CLI interface design and proper Symfony console integration  
**Key Areas:**
- Command structure: extend `Base` class, use `#[AsCommand]` attribute
- Required methods: `configure()` and `execute()`
- Type system: always use `Path` value object instead of strings for file paths
- Interactive patterns: use `$input->isInteractive()` for detection
- Error handling: proper return codes (`Command::SUCCESS`, `Command::FAILURE`, `Command::INVALID`)
- Best practices: method extraction, confirmation dialogs, file operation patterns
- Available services through DI container (Logger, StyleInterface, etc.)

### 📝 PHP Code Standards
**Path:** `docs/guidelines/how-to-write-php-code-best-practices.md`  
**Value:** Maintains modern PHP code quality and leverages latest language features for better performance and maintainability  
**Key Areas:**
- Modern PHP 8.1+ features: constructor promotion, union types, match expressions, throw expressions
- Code structure: PER-2 standards, single responsibility, final classes by default
- Enumerations: use enums for fixed value sets, CamelCase naming, backed enums for primitives
- Immutability: readonly properties, `with` prefix for immutable updates
- Type system: precise PHPDoc annotations, generics, non-empty-string types
- Comparison patterns: strict equality (`===`), null coalescing (`??`), avoid `empty()`
- Dependency injection and IoC container patterns

### 🧪 Testing Guidelines
**Path:** `docs/guidelines/how-to-write-tests.md`  
**Value:** Ensures comprehensive test coverage with modern PHPUnit practices and proper test isolation  
**Key Areas:**
- Test structure: mirror source structure, `final` test classes, Arrange-Act-Assert pattern
- Module testing: independent test areas with dedicated `Stub` directories
- Naming: `{ClassUnderTest}Test`, descriptive method names
- Modern PHPUnit: PHP 8.1+ attributes (`#[CoversClass]`, `#[DataProvider]`), data providers with generators
- Isolation: mock dependencies, use test doubles, reset state between tests
- **Critical restrictions**: DO NOT mock enums or final classes - use real instances
- Error testing: expectException before Act phase
- Test traits for shared functionality
