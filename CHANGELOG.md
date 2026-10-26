# Changelog

All notable changes to this project are documented here. Dates are UTC.

## 1.0.0 - unreleased

First release.

### Added

- Generation of immutable, mutable and Doctrine MongoDB ODM collection classes over
  `msstc4php/dto-collection` 1.x, from `#[TypedCollection]` attributes and from
  `dto-collection-generator.yaml`.
- Target PHP 8.1–8.5: `#[\Override]` from 8.3, intersection element types from 8.2.
- Checks of every spec before anything is written; all problems of a run are reported at once.
- A manifest of generated files, with paths relative to the config: stale ones are deleted,
  hand-edited and hand-written ones are never touched.
- `vendor/bin/dto-collection-generator generate` with `--check`, `--dry-run` and
  `--format=json`; the PHP API `DtoCollectionGenerator::generator()`; a Composer plugin on
  `post-autoload-dump`.
- An ODM collection takes a single class or interface as its element type (Doctrine ODM's
  persistent-collection generator cannot print a union return type), and every collection needs a
  namespace.
- `--check` and `--dry-run` list the manifest when it would change; commit the manifest.
- A stale file that no longer starts with the generated header is left alone with a warning
  (`abandoned` in the JSON report).
