# dto-collection-generator

![Build Status](https://github.com/msstc4php/dto-collection-generator/actions/workflows/ci.yml/badge.svg?branch=main)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-787CB5?logo=php&logoColor=white)](https://php.net)
[![Generated code](https://img.shields.io/badge/generates-PHP%208.1%20%E2%80%93%208.5-787CB5)](docs/configuration.md)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-2a5ea7)](https://phpstan.org)
[![Last commit](https://img.shields.io/github/last-commit/msstc4php/dto-collection-generator/main)](https://github.com/msstc4php/dto-collection-generator/commits/main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Writes natively typed collection classes over
[msstc4php/dto-collection](https://github.com/msstc4php/dto-collection) from `#[TypedCollection]` attributes on your DTOs
and from a config file. One attribute gives you a class like this, with the element type in
every signature:

```php
final class OrderLineList implements ImmutableList, TypedList
{
    use ImmutableListBehaviour;

    public static function of(OrderLine ...$elements): static { /* … */ }
    public function firstOrFail(): OrderLine { /* … */ }
    public function with(OrderLine ...$elements): self { /* … */ }
    // … about 30 one-line methods
}
```

- Variants: **immutable**, **mutable** and **Doctrine MongoDB ODM** (`collectionClass`).
- Element types: classes, interfaces, `int`, `float`, `string`, `bool`, `?T`, unions and
  intersections.
- Code for the target PHP version (8.1–8.5): `#[\Override]` from 8.3, intersection element
  types from 8.2.
- Repeatable output, a `--check` mode for CI, and no hand-written file is ever overwritten.

## Installation

```bash
composer require --dev msstc4php/dto-collection-generator
composer require msstc4php/dto-collection
```

The generator runs on PHP 8.1+, inside your project: it loads your DTO classes through the
project's autoloader.

## Quick start

Mark the DTO:

```php
use MSSTC4PHP\DtoCollection\Attribute\TypedCollection;
use MSSTC4PHP\DtoCollection\Attribute\Variant;

#[TypedCollection(class: 'App\Collection\OrderLineList', variant: Variant::Immutable)]
#[TypedCollection(class: 'App\Collection\MutableOrderLineList', variant: Variant::Mutable)]
final class OrderLine
{
    // …
}
```

Add `dto-collection-generator.yaml` next to `composer.json`:

```yaml
version: 1
sources:
  - src/Dto
```

Run it:

```bash
vendor/bin/dto-collection-generator generate
```

The classes land where PSR-4 autoloading looks for them, here `src/Collection/` if
`App\` maps to `src/`.

## In CI

```bash
vendor/bin/dto-collection-generator generate --check
```

exits with 1 when a generated file is missing, outdated or edited by hand.

## Regenerating on `composer dump-autoload`

```json
{
    "extra": {
        "dto-collection-generator": {"config": "dto-collection-generator.yaml"}
    }
}
```

The plugin runs the generator after every autoload dump and only warns on failure; add
`"failOnError": true` to make the Composer command fail instead.

## Documentation

- [docs/configuration.md](docs/configuration.md): every config key, the checks and the exit codes.
- The shape of the generated classes is dto-collection's
  [writing-a-typed-collection.md](https://github.com/msstc4php/dto-collection/blob/1.x/docs/writing-a-typed-collection.md).

## Development

```bash
make install       # package and tool dependencies
make verify        # PHPStan, CS-Fixer, Rector, deptrac, PHP 8.1 lint, tests on local PHP and 8.1
make test-targets  # the golden output on PHP 8.1 … 8.5 in Docker
make infection     # mutation testing
```

## Security

See [SECURITY.md](SECURITY.md).

## License

MIT, see [LICENSE](LICENSE).
