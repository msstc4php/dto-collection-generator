# dto-collection-generator

Writes natively typed collection classes over
[msstc4php/dto-collection](../dto-collection) from `#[TypedCollection]` attributes on your DTOs
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
  [writing-a-typed-collection.md](../dto-collection/docs/writing-a-typed-collection.md).

## License

MIT
