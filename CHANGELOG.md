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
- A manifest of generated files: stale ones are deleted, hand-edited and hand-written ones are
  never touched.
- `vendor/bin/dto-collection-generator generate` with `--check`, `--dry-run` and
  `--format=json`; the PHP API `DtoCollectionGenerator::generator()`; a Composer plugin on
  `post-autoload-dump`.
