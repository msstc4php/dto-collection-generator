# Architecture

Hexagonal, as dto-generator; `deptrac.yaml` enforces it.

| Layer | Directory | What |
|---|---|---|
| Domain | `src/Domain/{Diagnostic,Target,Spec,Model,Shared,Exception}`, `Validation/ClassFacts` | value objects, method catalogue, `TypeShape` |
| DomainService | `src/Domain/Validation/SpecValidator` | spec §7 checks |
| Application | `src/Application/{Config,Generate,Port}` | config parsing, the `Generate` use case, ports |
| Infrastructure | `src/Infrastructure/*` | YAML/JSON loader, attribute scanner, reflection, emitter, writer, composer.json |
| Presentation | `src/Presentation/{Cli,Composer}` | `generate` command, plugin settings |
| EntryPoint | `DtoCollectionGenerator`, `ComposerPlugin` | composition root |

Every layer may use `MSSTC4PHP\DtoCollection\*` (attribute, `Variant`, `ElementType`, `Equatable`).

## One run (`Generate::__invoke`)

1. `DocumentLoader` → `ConfigFactory::create()` (errors → `ConfigFailed`, exit 3).
2. `TargetResolver` (`auto` → `ProjectPhpConstraint`, lower bound of `require.php`).
3. Specs: `SpecSource` (`AttributeSpecSource`: php-parser finds candidate classes, reflection
   instantiates the attributes) first, then the config's `collections`.
4. `SpecValidator` with `ClassInspector` and `FileWriter::isGenerated()`; any error stops the run
   before emitting (`GenerationFailed`, exit 2).
5. `ProjectLayout` → file path; `CodeEmitter` (`StringCodeEmitter`) → contents.
6. `FileWriter::plan()` against disk and manifest; conflicts → exit 2; `Check` → exit 1 when not
   up to date; `Write` → `apply()`.

Errors are collected in `Diagnostics`, never thrown, so one run reports everything. The scanner
turns any `Throwable` of loading a class or creating an attribute into a diagnostic at the
attribute's line.
