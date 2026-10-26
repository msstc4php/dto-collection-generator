<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Domain\Spec;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Exception\InvalidClassName;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClassNameTest extends TestCase
{
    public function testParts(): void
    {
        $name = ClassName::fromString('App\Collection\ItemList');

        self::assertSame('App\Collection\ItemList', $name->fullName());
        self::assertSame('ItemList', $name->shortName());
        self::assertSame('App\Collection', $name->namespace());
        self::assertSame('app\collection\itemlist', $name->key());
    }

    public function testGlobalNamespace(): void
    {
        $name = ClassName::fromString('\ItemList');

        self::assertSame('ItemList', $name->fullName());
        self::assertSame('ItemList', $name->shortName());
        self::assertSame('', $name->namespace());
    }

    public function testReservedWordsAreAllowedAsNamespaceSegments(): void
    {
        self::assertSame('App\List\Items', ClassName::fromString('App\List\Items')->fullName());
        self::assertSame('App\Enum', ClassName::fromString('App\Enum')->fullName());
    }

    public function testEqualsIgnoringCase(): void
    {
        $name = ClassName::fromString('App\ItemList');

        self::assertTrue($name->equalsIgnoringCase(ClassName::fromString('app\itemlist')));
        self::assertFalse($name->equalsIgnoringCase(ClassName::fromString('App\ItemLists')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => ['', 'it is empty'];
        yield 'blank' => ['  ', 'it is empty'];
        yield 'empty segment' => ['App\\\\ItemList', 'it is not a valid class name'];
        yield 'trailing separator' => ['App\ItemList\\', 'it is not a valid class name'];
        yield 'leading digit' => ['App\1List', 'it is not a valid class name'];
        yield 'space' => ['App\Item List', 'it is not a valid class name'];
        yield 'list' => ['App\List', '"List" is a reserved word'];
        yield 'array' => ['App\Array', '"Array" is a reserved word'];
        yield 'iterable lower case' => ['App\iterable', '"iterable" is a reserved word'];
        yield 'mixed' => ['App\Collection\Mixed', '"Mixed" is a reserved word'];
        yield 'readonly' => ['Readonly', '"Readonly" is a reserved word'];
        yield 'self' => ['App\Self', '"Self" is a reserved word'];
    }

    #[DataProvider('invalid')]
    public function testRejectsInvalidNames(string $name, string $reason): void
    {
        $this->expectException(InvalidClassName::class);
        $this->expectExceptionMessage(sprintf('"%s" cannot name a class: %s', $name, $reason));

        ClassName::fromString($name);
    }
}
