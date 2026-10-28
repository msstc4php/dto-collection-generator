# Conventions

- Tooling is dto-collection's (PHP 8.1 floor): PHPStan max `phpVersion: 80100`, Rector PHP_81,
  PHP-CS-Fixer `@Symfony` + `@PHP81Migration`, deptrac, Infection (4 threads, MSI 100 %).
- Golden files: `tests/Fixtures/Emitter/<php>/<Class>.php.golden`, rewritten with
  `UPDATE_SNAPSHOTS=1`; `GoldenEmitterTest::testEveryGoldenFileHasACase` keeps the set exact.
  After a change to the emitter, diff `8.1/{ItemList,MutableItemList,IntegerList,PetCollection}`
  against `../dto-collection/tests/Fixture/` — only header, namespace, element import and the
  reference classes' explanatory doc sentences may differ.
- `make test-targets` (Docker): each profile on its PHP and every newer one up to 8.4 (lint,
  `tests/Targets/smoke.php`, `tests/Targets/odm-subclass.php`), 8.5 lint, PHPStan max per profile.
- Deliberately broken fixtures (`tests/Fixtures/Model/Broken.php`, `tests/Fixtures/Projects/Scan/`)
  are excluded from PHPStan, Rector, CS-Fixer and the 8.1 lint where needed.
- Public API: `DtoCollectionGenerator`, `Application\Generate\*`, `Domain\Diagnostic\*`;
  everything else is `@internal` (`PublicApiTest`).
- **An intersection is emitted as `ElementType::ofClass(A::class)->andInstanceOf(B::class)`**,
  not `ElementType::intersection(A::class, B::class)`: the latter is PHPDoc-typed
  `ElementType<object>` and would need a `@phpstan-ignore` in every generated class. Hence
  dto-collection `^1.1`.

## Repository files follow dto-generator

`SECURITY.md` (with a "what it trusts" section), README badges plus Security/License
sections, a Keep a Changelog `CHANGELOG.md` with `[x.y.z]` headings and compare links, and
the CI jobs with the Composer cache steps are kept as in `../dto-generator`. Deliberate
differences: PHP 8.1 floor, `--prefer-lowest` only on 8.1, ParallelConfig(4), no Docker image.
