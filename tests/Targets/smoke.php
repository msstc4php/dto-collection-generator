<?php

declare(strict_types=1);

// Loads the golden collections of one profile directory on the container's PHP and calls them; a notice fails it.

use App\Collection\AnimalEquatableList;
use App\Collection\AnimalList;
use App\Collection\BooleanList;
use App\Collection\ClosureHolderList;
use App\Collection\FloatList;
use App\Collection\IntegerList;
use App\Collection\ItemList;
use App\Collection\MutableItemList;
use App\Collection\MutableStringList;
use App\Collection\NullableTagList;
use App\Collection\PetCollection;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Cat;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Closure as ClosureHolder;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Dog;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Item;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Pet;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Tag;

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require __DIR__ . '/../../vendor/autoload.php';

$directory = $argv[1] ?? throw new InvalidArgumentException('Usage: smoke.php <directory of golden files>');
$files = glob(rtrim($directory, '/') . '/*.php.golden');
if ($files === false || $files === []) {
    throw new RuntimeException('No golden files in ' . $directory);
}
foreach ($files as $file) {
    require $file;
}

function check(bool $condition, string $what): void
{
    if (!$condition) {
        throw new RuntimeException('Failed: ' . $what);
    }
}

$items = ItemList::of(new Item('a'), new Item('b'));
check($items->count() === 2 && $items->first()?->sku === 'a' && $items->firstOrFail()->sku === 'a', 'ItemList reads');
check($items->filter(static fn (Item $item): bool => $item->sku === 'b') instanceof ItemList, 'ItemList::filter keeps the class');
check($items->contains(new Item('a')) && !$items->contains(new Item('z')), 'ItemList compares with equals()');
check($items->with(new Item('c'))->count() === 3 && $items->withoutElement(new Item('a'))->count() === 1, 'ItemList withers');

$mutable = MutableItemList::empty();
$mutable->push(new Item('a'), new Item('b'));
$mutable->set(0, new Item('c'));
check($mutable->pop()?->sku === 'b' && $mutable->remove(0)?->sku === 'c' && $mutable->isEmpty(), 'MutableItemList mutators');

$pets = PetCollection::of(new Pet('Rex'));
$pets->push(new Pet('Ace'));
$pets->add(new Pet('Bo'));
check($pets->count() === 3 && $pets->firstOrFail()->name === 'Rex' && $pets->containsAny(new Pet('Ace')), 'PetCollection');

check(IntegerList::of(1, 2, 3)->sum() === 6 && IntegerList::of(1, 2, 3)->max() === 3, 'IntegerList numbers');
check(FloatList::of(1, 2)->sum() === 3.0, 'FloatList widens ints');

$strings = MutableStringList::empty();
$strings->push('a', 'b');
check($strings->join(',') === 'a,b', 'MutableStringList::join');

check(BooleanList::of(true, false)->countWhere(static fn (bool $value): bool => $value) === 1, 'BooleanList');

$tags = NullableTagList::of(null, new Tag());
check($tags->first() === null && $tags->firstOrFail() === null && $tags->last() instanceof Tag, 'NullableTagList');

$animals = AnimalList::of(new Cat(), new Dog(), null);
check($animals->last() === null && $animals->contains(new Cat()) && !$animals->contains(new Dog()), 'AnimalList equality per branch');

check(ClosureHolderList::of(new ClosureHolder())->count() === 1, 'ClosureHolderList');

if (class_exists(AnimalEquatableList::class)) {
    $cats = AnimalEquatableList::of(new Cat());
    $cats->push(new Cat('Kit'));
    check($cats->count() === 2 && $cats->first() instanceof Cat && $cats->pop() instanceof Cat, 'AnimalEquatableList');
}

echo 'ok ', $directory, ' on PHP ', PHP_VERSION, "\n";
