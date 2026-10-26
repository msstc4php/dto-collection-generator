# dto-collection-generator: дизайн

Дата: 2026-10-26 (UTC). Статус: принят (владелец поручил проектирование и реализацию
без согласования: «делай дальше сам»).

Пометка **[решение Claude]** означает выбор, сделанный без явного подтверждения
владельца; остальное сказано владельцем (здесь или в дизайне dto-collection,
`~/PhpstormProjects/msstc4php/dto-collection/docs/internal/specs/2026-10-25-dto-collection-design.md`).

## 1. Зачем

`msstc4php/dto-collection` даёт трейты и интерфейсы. Конкретный класс с нативной
типизацией (`push(Item ...$elements)`, `firstOrFail(): Item`) состоит из полусотни
однострочников. Его форма зафиксирована контрактом
`dto-collection/docs/writing-a-typed-collection.md`. Генератор пишет эти классы по спекам,
чтобы человек их не писал и не сопровождал.

Спеки бывают двух видов (решение владельца):

- повторяемый атрибут `#[TypedCollection]` на DTO. Он живёт в dto-collection, поэтому DTO
  зависит только от runtime-пакета;
- конфиг-файл, для скаляров и чужих классов, на которые атрибут не повесить.

На один DTO можно описать несколько коллекций, у каждой свой FQCN и вариант.

## 2. Цели

- По каждой спеке получить файл класса, который соответствует контракту
  writing-a-typed-collection.md для своего варианта (immutable, mutable, ODM).
- Учитывать ключ целевой версии PHP (решение владельца): код использует то, что эта
  версия даёт.
- Повторяемый результат: тот же вход даёт байт-в-байт тот же выход. Режим `--check` для CI.
- Ошибки спек находятся до записи файлов и сообщаются все сразу, с местом
  (файл:строка атрибута или файл#json-pointer конфига).
- Сопровождаемость и отсутствие багов, как в dto-collection: TDD, PHPStan max,
  Infection 100 %.

## 3. Не цели

- Генерация DTO. Это dto-generator.
- Подстановка коллекций в поля DTO. Это проект 3 (dto-generator: «объект-коллекция
  вместо массива»).
- Doctrine ORM (не поддерживается в dto-collection).
- Рукописные дополнения в сгенерированных классах. Расширять нужно своим классом или
  трейтом рядом, а сгенерированный файл перезаписывается.
- Отдельные линии генератора под PHP 8.5 [решение Claude]. Сам генератор — одна линия
  `main`, а разница целевых версий живёт внутри него.

## 4. Анти-цели

- Сгенерированный код, который не проходит PHPStan max или падает на какой-то из целевых
  версий PHP.
- Тихая перезапись рукописного файла.
- Сокращённые имена (как в dto-collection).

## 5. Ограничения

- Пакет `msstc4php/dto-collection-generator`, неймспейс `MSSTC4PHP\DtoCollectionGenerator`,
  каталог `~/PhpstormProjects/msstc4php/dto-collection-generator`, ветка `main`
  [решение Claude, по аналогии].
- Сам генератор работает на PHP 8.1+ (рефлексия атрибутов, enum `Variant` из
  dto-collection). PHPStan `phpVersion: 80100`, lint на `php:8.1-cli`.
- Зависимости: `msstc4php/dto-collection ^1.0` (атрибут, `Variant`, `ElementType`,
  `Equatable`), `nikic/php-parser ^5.1`, `symfony/console`, `symfony/yaml` в тех же
  диапазонах, что у dto-generator. Когда выйдет dto-collection 2.x, ограничение
  расширяется до `^1.0 || ^2.0`.
- dto-collection пока не опубликован, поэтому для разработки он берётся `path`-репозиторием
  `../dto-collection` с явной версией 1.0.0. Это фиксируется в known-issues.
- Целевые версии: **8.1–8.5**. Ниже 8.1 dto-collection не работает, и это ошибка конфига.
  Публичный API линий dto-collection одинаков, поэтому для 8.5 генерируется тот же
  контракт.
- Инструменты и конвенции копируются из dto-generator: Makefile, phpstan, rector,
  php-cs-fixer, deptrac, infection (≤ 4 потоков), roave BC-check после первого тега.

## 6. Вход

### 6.1 Конфиг

Файл `dto-collection-generator.yaml` (или `.json`) в корне проекта. Формат и правила
разбора те же, что у dto-generator: неизвестный ключ — ошибка, пути относительно
конфига, все проблемы собираются в `Diagnostics` [решение Claude].

```yaml
version: 1
target:
  php: auto            # auto | '8.1' … '8.5'; auto — нижняя граница require.php из composer.json
sources:               # каталоги, где искать #[TypedCollection]
  - src/Dto
collections:           # спеки для скаляров и чужих классов
  - class: App\Collection\IntegerList
    variant: immutable # immutable | mutable | odm
    elementType: int   # обязателен здесь
    nullable: false    # необязателен, по умолчанию false
paths:                 # необязателен: куда класть файлы по префиксу неймспейса
  App\Collection\: src/Collection
```

- `target.php: auto` берёт нижнюю границу `require.php` ближайшего `composer.json`, как
  dto-generator. Нет границы — 8.1 с предупреждением; ниже 8.1 — ошибка; выше 8.5 — 8.5 с
  предупреждением.
- Пути файлов: сначала `paths`, потом PSR-4 из `autoload` и `autoload-dev` composer.json;
  выбирается самый длинный префикс. Класс вне всех префиксов — ошибка [решение Claude].

### 6.2 Атрибут

`#[TypedCollection(class:, variant:, nullable: false, elementType: null)]` на классе в
одном из `sources`. Без `elementType` тип элемента — помеченный класс.

Поиск [решение Claude]:

1. Файлы `*.php` в `sources`, где встречается текст `TypedCollection`, разбираются
   php-parser'ом с `NameResolver`. Берутся классы, у которых есть атрибут с полным именем
   `MSSTC4PHP\DtoCollection\Attribute\TypedCollection`.
2. Найденные классы загружаются автозагрузчиком проекта, и каждый атрибут создаётся через
   `ReflectionAttribute::newInstance()`. Так константные выражения (`Foo::class`,
   `self::NAME`) вычисляет сам PHP, а проверки конструктора атрибута (пустое имя,
   nullable ODM) срабатывают как в рантайме. Исключение конструктора становится
   диагностикой с местом атрибута.

Рефлексия нужна и дальше: `Equatable`, существование классов, принадлежность помеченного
класса к `elementType`, ODM-маппинг. Генератор поэтому запускается в процессе с
автозагрузчиком проекта (`vendor/bin/dto-collection-generator`). Он не исполняет ничего,
кроме загрузки найденных классов и классов из типов элементов.

Спеки из конфига проверяются тем же конструктором атрибута: генератор строит из записи
`new TypedCollection(...)`, чтобы правила были в одном месте.

## 7. Проверки спек

Ошибки (генерация не пишет ничего):

- имя класса коллекции не является корректным FQCN, или его короткое имя —
  зарезервированное слово PHP (`List`, `Array`, `Iterable`…);
- одинаковый FQCN у двух спек (без учёта регистра);
- FQCN совпадает с существующим классом, который не сгенерирован этим генератором, или с
  классом элемента;
- `elementType` не разбирается `ElementType::parse()`;
- класс или интерфейс из типа элемента не существует;
- помеченный класс не принадлежит `elementType` (для intersection — всем его классам,
  для union — хотя бы одной ветке);
- intersection-тип при целевой версии ниже 8.2: позиции `?T` (`first(): ?T`, `pop(): ?T`)
  требуют DNF `(A&B)|null` [решение Claude];
- ODM: тип элемента со скалярной веткой; doctrine/mongodb-odm не установлен.

Предупреждения:

- ODM: у класса элемента нет атрибута ODM-маппинга (`Document`, `EmbeddedDocument`,
  `MappedSuperclass`, `File`, `View`, `QueryResultDocument`; проверка через
  `AbstractDocument` с `IS_INSTANCEOF`). Маппинг может быть в XML, поэтому это не ошибка.
  Интерфейсы не проверяются.
- спека в конфиге для класса, на котором уже стоит атрибут с тем же FQCN, — это дубль, и он
  остаётся ошибкой из списка выше.

## 8. Выход

### 8.1 Класс

Ровно контракт writing-a-typed-collection.md:

- immutable и mutable — `final class`, ODM — не `final`, `@phpstan-consistent-constructor`,
  `implements Collection, Selectable, MutableList, TypedList`;
- трейт варианта, плюс `NumericBehaviour` для `int`, `float`, `int|float` без null и
  `TextBehaviour` для `string` без null (решают `ElementType::isNumeric()`/`isString()`);
- `elementType()` строится вызовами фабрик `ElementType` (`ofClass`, `integer`, `union`,
  `intersection`, `nullable`), а не разбором строки в рантайме;
- `equality()` выводится статически и повторяет `EqualityResolver::forType()`: для каждой
  одноклассовой ветки, чей класс реализует `Equatable`, — компаратор
  `ComparatorEquality::of(X::class, static fn (X $left, X $right): bool => $left->equals($right))`,
  следующие через `->with()`; иначе `new StrictEquality()`;
- PHPDoc: `@implements …<T>`, `@use …<T>`, типы замыканий `Closure(T, int): bool`;
- `#[\NoDiscard]` по правилам контракта.

Файл: строка `// @generated by msstc4php/dto-collection-generator — DO NOT EDIT`,
`declare(strict_types=1);`, неймспейс, импорты по алфавиту, PER-раскладка (принтер
копируется из dto-generator). Принтер получает целевую версию.

### 8.2 Что зависит от целевой версии

`Capability` с минимальной версией, как в dto-generator [решение Claude]:

| Capability | Версия | Что даёт |
|---|---|---|
| `DNF_TYPES` | 8.2 | intersection-типы элементов: `?T` печатается как `(A&B)\|null` |
| `OVERRIDE_ATTRIBUTE` | 8.3 | `#[\Override]` на методах, которые реализуют метод интерфейса |

На 8.4 и 8.5 новых возможностей для этого кода нет, поэтому их вывод совпадает с 8.3.

### 8.3 Запись

Как `FilesystemWriter` dto-generator, упрощённо [решение Claude]:

- один манифест `.dto-collection-generator.manifest.json` рядом с конфигом: относительный
  путь → sha256;
- файл без строки `@generated` в начале не перезаписывается (ошибка);
- файл из старого манифеста, который больше не генерируется, удаляется, если не изменён
  руками; изменённый остаётся, и это ошибка-конфликт;
- атомарная запись (временный файл + rename), сравнение без учёта CRLF/LF;
- режимы `write`, `check` (ничего не пишет; устаревший вывод — код 1), `dry-run`.

## 9. Точки входа

- CLI `vendor/bin/dto-collection-generator generate [--config=] [--check] [--dry-run]
  [--format=text|json]`. Коды выхода как у dto-generator: 0 ок, 1 устарело, 2 ошибка
  генерации, 3 ошибка конфига или использования.
- Программный фасад `DtoCollectionGenerator::generator()` / `::run()`.
- Composer-плагин: `post-autoload-dump`, настройки в `extra.dto-collection-generator:
  {config, failOnError}`, запуск отдельным процессом, как у dto-generator [решение Claude].

## 10. Архитектура

Hex-слои как в dto-generator, правило зависимостей в deptrac [решение Claude]:

| Слой | Что |
|---|---|
| Domain | `CollectionSpec`, `Target` (`PhpVersion`, `Capability`, `TargetProfile`), `Diagnostic`, модель класса для эмиттера |
| DomainService | проверка спек, построение модели класса по спеке и профилю |
| Application | use-case `Generate` (конфиг → спеки → проверки → модели → план записи), порты `SpecSource`, `ClassInspector`, `CodeEmitter`, `FileWriter`, `ProjectLayout` |
| Infrastructure | YAML/JSON-конфиг, сканер атрибутов (php-parser + рефлексия), рефлексия классов, php-parser-эмиттер, файловый writer, composer.json |
| Presentation | CLI, composer-плагин |

`ClassInspector` — порт над рефлексией: существует ли класс, реализует ли `Equatable`,
есть ли ODM-маппинг, `is_a`. В юнит-тестах его подменяет in-memory двойник.

## 11. Качество

- TDD; PHPStan max со strict-правилами без baseline; PHP-CS-Fixer; Rector; deptrac;
  Infection MSI 100 %.
- Golden-файлы: для каждого варианта и каждой профильной версии (8.1, 8.2, 8.3) набор
  спек — класс, скаляры `int`/`float`/`string`/`bool`, nullable, union, intersection
  (8.2+), Equatable, ODM. `UPDATE_SNAPSHOTS=1` их переписывает.
- `make test-targets`: каждый golden-набор на своей версии PHP в Docker (`php -l`,
  smoke-скрипт, который создаёт коллекции и вызывает методы, PHPStan max), плюс для
  ODM-набора генерация persistent-подкласса ODM на doctrine/collections 2 и 3, как
  `PetCollectionTest` в dto-collection.
- Функциональные тесты CLI и плагина на временном проекте.
- Документация пользователя: README, `docs/configuration.md`; CHANGELOG; внутренние
  заметки в `.claude/docs/`.

## 12. Что остаётся строителю

- Точные имена классов модели и портов.
- Формат текстового отчёта CLI (в духе dto-generator).
- Набор вызовов в smoke-скрипте.
