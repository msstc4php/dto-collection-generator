<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Infrastructure\Emitter;

use Doctrine\Common\Collections\Collection;
use LogicException;
use MSSTC4PHP\DtoCollection\ElementType;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Emitter\ImportTable;
use PHPUnit\Framework\TestCase;

final class ImportTableTest extends TestCase
{
    private const FIXED = ['Closure', 'NoDiscard', ElementType::class, Collection::class];

    public function testImportsElementClasses(): void
    {
        $table = new ImportTable(ClassName::fromString('App\Collection\ItemList'), self::FIXED, ['App\Model\Item']);

        self::assertSame('Item', $table->name('App\Model\Item'));
        self::assertSame('ElementType', $table->name(ElementType::class));
        self::assertSame(['App\Model\Item', 'Closure', Collection::class, ElementType::class, 'NoDiscard'], $table->imports());
    }

    public function testClashWithAFixedImportIsQualified(): void
    {
        $table = new ImportTable(ClassName::fromString('App\Collection\HolderList'), self::FIXED, ['App\Model\Closure', 'App\Model\collection']);

        self::assertSame('\App\Model\Closure', $table->name('App\Model\Closure'));
        self::assertSame('\App\Model\collection', $table->name('App\Model\collection'));
        self::assertNotContains('App\Model\Closure', $table->imports());
    }

    public function testGlobalClassesAreQualified(): void
    {
        $table = new ImportTable(ClassName::fromString('App\Collection\CountableList'), self::FIXED, ['Countable']);

        self::assertSame('\Countable', $table->name('Countable'));
        self::assertSame($this->sorted(self::FIXED), $table->imports());
    }

    public function testSameNamespaceNeedsNoImport(): void
    {
        $table = new ImportTable(ClassName::fromString('App\Model\ItemList'), self::FIXED, ['App\Model\Item', 'App\Model\Closure']);

        self::assertSame('Item', $table->name('App\Model\Item'));
        self::assertSame('\App\Model\Closure', $table->name('App\Model\Closure'));
        self::assertSame($this->sorted(self::FIXED), $table->imports());
    }

    public function testElementsSharingAShortNameAreQualified(): void
    {
        $table = new ImportTable(ClassName::fromString('App\ItemList'), self::FIXED, ['A\Item', 'B\item']);

        self::assertSame('\A\Item', $table->name('A\Item'));
        self::assertSame('\B\item', $table->name('B\item'));
    }

    public function testElementSharingTheCollectionsShortNameIsQualified(): void
    {
        $table = new ImportTable(ClassName::fromString('App\ItemList'), self::FIXED, ['App\Other\ItemList']);

        self::assertSame('\App\Other\ItemList', $table->name('App\Other\ItemList'));
    }

    public function testFixedImportSharingTheCollectionsShortNameIsQualified(): void
    {
        $table = new ImportTable(ClassName::fromString('App\Collection\Collection'), self::FIXED, ['App\Model\Item']);

        self::assertSame('\\' . Collection::class, $table->name(Collection::class));
        self::assertNotContains(Collection::class, $table->imports());
        self::assertSame('Item', $table->name('App\Model\Item'));
    }

    public function testTheSameClassInAnotherCaseIsOneClass(): void
    {
        $table = new ImportTable(ClassName::fromString('App\ItemList'), self::FIXED, ['A\Item', 'a\item', 'A\Item', 'B\Tag']);

        self::assertSame('Item', $table->name('A\Item'));
        self::assertSame('Item', $table->name('a\ITEM'));
        self::assertSame('Tag', $table->name('B\Tag'));
        self::assertSame(['A\Item'], array_values(array_filter($table->imports(), static fn (string $import): bool => stripos($import, 'item') !== false)));
    }

    public function testUnknownClassIsALogicError(): void
    {
        $table = new ImportTable(ClassName::fromString('App\ItemList'), self::FIXED, []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('App\Unknown is neither imported nor an element class');
        $table->name('App\Unknown');
    }

    public function testGlobalCollection(): void
    {
        $table = new ImportTable(ClassName::fromString('ItemList'), self::FIXED, ['App\Item', 'Countable']);

        self::assertSame('Item', $table->name('App\Item'));
        self::assertSame('\Countable', $table->name('Countable'));
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        usort($names, strcasecmp(...));

        return $names;
    }
}
