<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Domain\Model;

use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollection\ImmutableList;
use MSSTC4PHP\DtoCollection\MutableList;
use MSSTC4PHP\DtoCollection\TypedList;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Model\MethodCatalogue;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Model\MethodTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MethodCatalogueTest extends TestCase
{
    private const READING = ['fromArray', 'fromIterable', 'empty', 'of', 'first', 'last', 'firstOrFail', 'lastOrFail', 'get', 'getOrFail', 'at', 'single', 'singleOrNull', 'random', 'find', 'findLast', 'minBy', 'maxBy', 'contains', 'containsAll', 'containsAny', 'positionOf'];

    public function testImmutableMethods(): void
    {
        self::assertSame(
            [...self::READING, 'with', 'withPrepended', 'withSet', 'withInsertedAt', 'withoutElement', 'withReplaced'],
            $this->names(MethodCatalogue::for(Variant::Immutable)),
        );
    }

    public function testMutableMethods(): void
    {
        self::assertSame(
            [...self::READING, 'push', 'unshift', 'set', 'insertAt', 'removeElement', 'remove', 'pop', 'shift'],
            $this->names(MethodCatalogue::for(Variant::Mutable)),
        );
    }

    public function testOdmLeavesDoctrinesNamesToTheTrait(): void
    {
        self::assertSame(
            ['fromArray', 'fromIterable', 'empty', 'of', 'firstOrFail', 'lastOrFail', 'getOrFail', 'at', 'single', 'singleOrNull', 'random', 'find', 'findLast', 'minBy', 'maxBy', 'containsAll', 'containsAny', 'positionOf', 'push', 'unshift', 'insertAt', 'pop', 'shift'],
            $this->names(MethodCatalogue::for(Variant::Odm)),
        );
    }

    /**
     * @return iterable<string, array{Variant, class-string}>
     */
    public static function variants(): iterable
    {
        yield 'immutable' => [Variant::Immutable, ImmutableList::class];
        yield 'mutable' => [Variant::Mutable, MutableList::class];
        yield 'odm' => [Variant::Odm, MutableList::class];
    }

    /**
     * Fails when a dto-collection release adds a typed method to, or moves one out of, the interfaces: #[\Override]
     * on a method no interface declares is a compile error.
     *
     * @param class-string $interface
     */
    #[DataProvider('variants')]
    public function testDeclaredByInterfaceMatchesDtoCollection(Variant $variant, string $interface): void
    {
        foreach (MethodCatalogue::for($variant) as $method) {
            $declared = method_exists($interface, $method->name) || method_exists(TypedList::class, $method->name);
            self::assertSame($declared, $method->declaredByInterface, $method->name);
        }
    }

    public function testMutatorsMayBeDiscarded(): void
    {
        $discardable = array_values(array_filter(
            MethodCatalogue::for(Variant::Mutable),
            static fn (MethodTemplate $method): bool => !$method->noDiscard,
        ));

        self::assertSame(['push', 'unshift', 'set', 'insertAt', 'removeElement', 'remove', 'pop', 'shift'], $this->names($discardable));
    }

    public function testShapes(): void
    {
        $methods = [];
        foreach (MethodCatalogue::for(Variant::Mutable) as $method) {
            $methods[$method->name] = $method;
        }

        self::assertSame(['static', true, true], [$methods['of']->returns, $methods['of']->static, $methods['of']->parameters[0]->variadic]);
        self::assertSame('T', $methods['of']->parameters[0]->type);
        self::assertSame('construct', $methods['of']->body);
        self::assertSame(['@param array<T> $elements'], $methods['fromArray']->phpDoc);
        self::assertSame(['@param iterable<T> $elements'], $methods['fromIterable']->phpDoc);
        self::assertSame([], $methods['empty']->parameters);
        self::assertSame(['@param Closure(T, int): bool $predicate'], $methods['find']->phpDoc);
        self::assertSame(['@param Closure(T): mixed $selector'], $methods['maxBy']->phpDoc);
        self::assertSame(['?T', 'doGet', 'int', 'index'], [$methods['get']->returns, $methods['get']->delegate, $methods['get']->parameters[0]->type, $methods['get']->parameters[0]->name]);
        self::assertSame(['?int', 'doPositionOf'], [$methods['positionOf']->returns, $methods['positionOf']->delegate]);
        self::assertSame(['void', 'doSetAt'], [$methods['set']->returns, $methods['set']->delegate]);
        self::assertSame(['?T', 'doRemoveAt'], [$methods['remove']->returns, $methods['remove']->delegate]);
        self::assertSame(['bool', 'doContainsAll', true], [$methods['containsAll']->returns, $methods['containsAll']->delegate, $methods['containsAll']->parameters[0]->variadic]);
        self::assertSame(['T', 'doSingle'], [$methods['single']->returns, $methods['single']->delegate]);
        self::assertSame('delegate', $methods['single']->body);
    }

    public function testImmutableQueriesReturnSelf(): void
    {
        foreach (MethodCatalogue::for(Variant::Immutable) as $method) {
            if (str_starts_with($method->name, 'with')) {
                self::assertSame('self', $method->returns, $method->name);
                self::assertSame('do' . ucfirst($method->name), $method->delegate);
            }
        }
    }

    /**
     * @param list<MethodTemplate> $methods
     *
     * @return list<string>
     */
    private function names(array $methods): array
    {
        return array_map(static fn (MethodTemplate $method): string => $method->name, $methods);
    }
}
