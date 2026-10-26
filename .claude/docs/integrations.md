# Integrations

- **dto-collection** (`^1.1`, for `ElementType::andInstanceOf()`): installed from the path repository `../dto-collection` with
  `versions: {msstc4php/dto-collection: 1.1.0}` and `symlink: false` (a mirror copy, including
  its own vendor/ — harmless). Its `php` constraint is `>=8.1 <8.5`.
- **php-parser 5**: only for finding candidate classes (`createForNewestSupportedVersion()`,
  `NameResolver`). Files without the text `TypedCollection` are not parsed.
- **Reflection / autoloading**: the generator runs in the project's process and loads the found
  DTO classes and the element classes. Loading may throw (missing parent, parse error); every
  such `Throwable` becomes a diagnostic.
- **Composer plugin**: `post-autoload-dump` runs `PHP_BINARY <bin-dir>/dto-collection-generator
  generate --config=…` in a separate process, as dto-generator does (Composer's own classes are
  loaded in Composer's process). `extra.dto-collection-generator.config` is required.
- **Doctrine ODM**: only to check the ODM variant (document attributes via `AbstractDocument` +
  `IS_INSTANCEOF`) and, in `make test-targets`, to generate the persistent subclass of the golden
  `PetCollection` (`DefaultPersistentCollectionGenerator`).
