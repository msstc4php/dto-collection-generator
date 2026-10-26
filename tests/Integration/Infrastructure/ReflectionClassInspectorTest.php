<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Integration\Infrastructure;

use Composer\Autoload\ClassLoader;
use Countable;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassKind;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Reflection\ReflectionClassInspector;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Animal;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Broken;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Cat;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Colour;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Dog;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Item;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Labelled;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\NeverLoaded;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Pet;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Tag;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\TestCase;

final class ReflectionClassInspectorTest extends TestCase
{
    public function testKinds(): void
    {
        $inspector = new ReflectionClassInspector();

        self::assertSame(ClassKind::OrdinaryClass, $inspector->kind(Item::class));
        self::assertSame(ClassKind::Interface, $inspector->kind(Animal::class));
        self::assertSame(ClassKind::Trait, $inspector->kind(Labelled::class));
        self::assertSame(ClassKind::Enum, $inspector->kind(Colour::class));
        self::assertSame(ClassKind::Interface, $inspector->kind(Countable::class));
        self::assertNull($inspector->kind('Nope\Missing'));
        self::assertNull($inspector->kind(Broken::class));
    }

    public function testHierarchy(): void
    {
        $inspector = new ReflectionClassInspector();

        self::assertTrue($inspector->isA(Cat::class, Animal::class));
        self::assertTrue($inspector->isA(Cat::class, Cat::class));
        self::assertFalse($inspector->isA(Tag::class, Animal::class));
        self::assertFalse($inspector->isA('Nope\Missing', Animal::class));
        self::assertTrue($inspector->isEquatable(Item::class));
        self::assertFalse($inspector->isEquatable(Tag::class));
        self::assertFalse($inspector->isEquatable(Dog::class));
    }

    public function testOdm(): void
    {
        $inspector = new ReflectionClassInspector();

        self::assertTrue($inspector->isOdmInstalled());
        self::assertTrue($inspector->hasOdmMapping(Pet::class));
        self::assertFalse($inspector->hasOdmMapping(Tag::class));
        self::assertFalse($inspector->hasOdmMapping('Nope\Missing'));
    }

    public function testLocatesWithoutLoading(): void
    {
        $inspector = new ReflectionClassInspector();
        $class = NeverLoaded::class;

        self::assertSame(dirname(__DIR__, 2) . '/Fixtures/Model/NeverLoaded.php', realpath((string) $inspector->locate($class)));
        self::assertFalse(class_exists($class, false));
        self::assertNull($inspector->locate('Nope\Missing'));
    }

    public function testLocatesAsTheAutoloaderWould(): void
    {
        $directory = new TemporaryDirectory();
        $directory->write('real/NeverLoaded.php', "<?php\n");
        symlink($directory->path . '/real', $directory->path . '/link');
        $loader = new ClassLoader($directory->path . '/vendor');
        $loader->addPsr4('MSSTC4PHP\\DtoCollectionGenerator\\Tests\\Fixtures\\Model\\', $directory->path . '/link');
        // Registered last and prepended, as Composer does: spl_autoload asks it first.
        $loader->register(true);

        try {
            self::assertSame(realpath($directory->path . '/real/NeverLoaded.php'), (new ReflectionClassInspector())->locate(NeverLoaded::class));
        } finally {
            $loader->unregister();
            unlink($directory->path . '/link');
            $directory->remove();
        }
    }

    public function testFiles(): void
    {
        $inspector = new ReflectionClassInspector();

        self::assertSame(dirname(__DIR__, 2) . '/Fixtures/Model/Item.php', $inspector->fileOf(Item::class));
        self::assertNull($inspector->fileOf(Countable::class));
        self::assertNull($inspector->fileOf('Nope\Missing'));
    }
}
