# CLAUDE.md

Guidance for Claude Code working in this repository. Deep references live
under `.claude/docs/`; this file stays lean.

## What this is

`msstc4php/dto-collection-generator`: writes natively typed collection classes
over `msstc4php/dto-collection` from `#[TypedCollection]` attributes and
`dto-collection-generator.yaml`, for target PHP 8.1–8.5. Hexagonal layout
(`Domain`, `Application`, `Infrastructure`, `Presentation`), enforced by
`deptrac.yaml`. Design: `docs/internal/specs/2026-10-26-dto-collection-generator-design.md`;
plan: `docs/internal/plans/2026-10-26-dto-collection-generator-1.0.md`. The shape of
every emitted class is `../dto-collection/docs/writing-a-typed-collection.md`.

The generator itself runs on **PHP 8.1**: no PHP 8.2+ syntax or functions in
`src/` (PHPStan `phpVersion: 80100`, `make lint-81`).

## Common commands

- `make install`, `make fix`, `make check`, `make test`, `make test-81`.
- `make verify` — the full gate; `make infection` — mutation testing (keep 100 %).
- `make test-targets` — the golden output on PHP 8.1 … 8.5 in Docker.
- `UPDATE_SNAPSHOTS=1` regenerates golden files.

## Conventions

- Code comments in English, only the non-obvious "why".
- Full names, no abbreviations.
- Every change: tests first, `make fix`, `make verify`, `make infection`.
- User-facing behaviour changes go into `CHANGELOG.md` and `docs/`.
