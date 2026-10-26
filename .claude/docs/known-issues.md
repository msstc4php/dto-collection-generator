# Known issues and traps

- **CI cannot install dto-collection yet**: `composer.json` takes it from `../dto-collection`.
  Switch to a `vcs` repository or Packagist once dto-collection is published.
- **8.5 is lint-only in `make test-targets`**: dto-collection 1.x declares `php <8.5`. The 8.5
  output equals 8.4's; widen the constraint to `^1.0 || ^2.0` when 2.x exists.
- **`ElementType::intersection()` is typed `ElementType<object>`**, so the generated
  `elementType()` of an intersection collection carries a `@phpstan-ignore return.type,
  assign.propertyType`. Fix it in dto-collection first, then drop the ignore here (it would turn
  into an unmatched ignore).
- **Generic interfaces in an element type** (`Traversable`, `IteratorAggregate`) make PHPStan max
  report missing type arguments in the generated class; a spec cannot carry them. The golden
  intersection uses `Animal&Equatable` for that reason.
- **PHP turns numeric array keys like `"1"` into ints**: config `paths` keys and PSR-4 prefixes
  are cast back to string where compared (found by mutation testing; it used to be a TypeError).
- **A collection whose short name equals one of its file's imports** (`Collection`, `Closure`,
  `ElementType`, …): `ImportTable` qualifies that import (`\Doctrine\Common\Collections\Collection`)
  instead of importing it.
- **Doctrine ODM cannot subclass a union element type**: `DefaultPersistentCollectionGenerator::formatType()`
  prints the `null` of `Cat|Dog|null` as `?null` (ParseError). The validator therefore allows only a
  single class or interface for the ODM variant. Lift the rule when ODM fixes `formatType()`.
- **Loading the previous output can be fatal**: a generated class for an older dto-collection may fail
  to link (missing abstract method) — uncatchable. The validator skips the "already exists" check when
  the target file starts with our header, so such a class is never loaded.
- **Case-only renames on macOS/Windows**: the stale scan skips any manifest entry whose inode is a file written in
  this run (covers Unicode case folding and symlinked spellings; tested with hard links on Linux). The file may keep
  its old spelling on disk — documented (`git mv`), not fixed.
- **The manifest is compared byte for byte** with what `writeManifest` would write (CRLF ignored), so an old
  absolute or reordered manifest is "outdated" once and rewritten.
- **Abandoned warnings only for `.php` files inside the config directory**: other stale entries of an untrusted
  manifest are dropped silently, so a planted manifest cannot point the user at an arbitrary file.
- **Manifest paths are relative to the config, with `../`** (`Path::relative()`); absolute only across
  drives or UNC shares.
- **No `tempnam()` for the atomic write**: it creates files with mode 0600, which the rename keeps.
- **The manifest is not trusted**: a stale entry is deleted only if it is a `.php` file that
  starts with the generated header (an automated security review flagged arbitrary deletion).
- **Infection and file permissions**: a `chmod 000` test does not reproduce under Infection's
  stream interceptor; one mutant in `ComposerProjectLayout::psr4` is ignored for that reason.
- **`$TMPDIR` is shared with other sessions on this machine**: write logs to the session
  scratchpad, not `$TMPDIR/check.log`.
- **Rector rewrites strings to `::class`** in tests (`'\Foo\Bar'` → `Bar::class`, losing the
  leading backslash): write `'\\' . Bar::class` when the backslash matters.
