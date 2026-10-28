# Known issues and traps

- **dto-collection comes from a `vcs` repository** (`github.com/msstc4php/dto-collection`) until it
  is on Packagist; then drop `repositories` from `composer.json`. A change to dto-collection
  reaches the generator only through a tag there.
- **8.5 is lint-only in `make test-targets`**: dto-collection 1.x declares `php <8.5`. The 8.5
  output equals 8.4's; widen the constraint to `^1.0 || ^2.0` when 2.x exists.
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
- **Case-only renames on macOS/Windows**: the stale scan skips any manifest entry whose identity ("dev:ino" from
  stat) is a file written in this run (covers Unicode case folding and symlinked spellings; tested with hard links on
  Linux). Inode 0 means unknown (some file systems, older PHP on Windows): then an entry differing from a written
  path only in letter case after realpath() is kept instead. Stale entries count only inside the config directory or a directory the run
  writes into. The file may keep
  its old spelling on disk — documented (`git mv`), not fixed.
- **The manifest is compared byte for byte** with what `writeManifest` would write (CRLF ignored), so an old
  absolute or reordered manifest is "outdated" once and rewritten.
- **"Declared elsewhere" compares files, not strings**: `FileIdentity::same()` (dev:ino, else realpath), because
  realpath() on macOS keeps the letter case it is given.
- **Composer's loaders come in autoload order**: `register(true)` prepends to `ClassLoader::getRegisteredLoaders()`;
  `locate()` and the generated target are compared by `realpath()`.
- **Abandoned warnings only for `.php` files in scope**: other stale entries of an untrusted
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
- **Local tools hide two CI-only failures**: `tools/vendor` carries PhpStorm's stubs, so locally
  PHPStan and Rector see `Ds\Vector` even without ext-ds (CI does not: name such classes as
  strings, Rector skip in `rector.php`); and the local platform pin (8.1) installs symfony/console
  ≤7, while CI on PHP 8.4 gets 8, which has no `Application::add()` — use a command loader.
