# Domain model

- **CollectionSpec**: `ClassName $class`, `Variant $variant`, `ElementType $elementType` (nullable
  already applied), `?ClassName $markedClass` (null for config specs), `Location $origin`.
  Built only through `CollectionSpec::fromAttribute()`, also for config entries (the factory
  builds a `TypedCollection`), so the attribute's constructor is the one source of its rules
  (empty name, nullable ODM).
- **ClassName**: FQCN syntax and PHP's reserved words for the short name (namespace segments may
  be reserved words since PHP 8.0). `key()` is lower case: PHP compares class names case-insensitively.
- **ElementType** comes from dto-collection; its factories already reject missing classes and
  traits (`class_exists() || interface_exists()`), which is why the validator has no such rule.
- **TypeShape**: reads `ElementType::describe()` back into branches (`'int'` or a list of classes;
  two or more = intersection). Renders native `T`, native `?T` (`?X`, `A|B|null`, `(A&B)|null`),
  PHPDoc `T` and `T|null`. Class names go through the emitter's `ImportTable` callback.
- **MethodCatalogue**: per variant, the typed methods in the order of dto-collection's reference
  classes. `declaredByInterface` decides `#[\Override]`; a drift test compares it with
  `method_exists()` on `ReadableList`/`MutableList`/`TypedList`.
- **TargetProfile / Capability**: `DnfTypes` (8.2), `OverrideAttribute` (8.3).
- **Diagnostics**: deduplicated by (severity, message, path, line, pointer); the first of equal
  ones is kept.
