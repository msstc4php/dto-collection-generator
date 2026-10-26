<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Model;

use MSSTC4PHP\DtoCollection\Attribute\Variant;

/**
 * The typed public methods of each variant, in the order of dto-collection's reference classes
 * (docs/writing-a-typed-collection.md is the contract).
 */
final class MethodCatalogue
{
    private function __construct()
    {
    }

    /**
     * @return list<MethodTemplate>
     */
    public static function for(Variant $variant): array
    {
        $doctrineNames = $variant !== Variant::Odm;
        $methods = [
            self::factory('fromArray', [new ParameterTemplate('elements', 'array')], ['@param array<T> $elements']),
            self::factory('fromIterable', [new ParameterTemplate('elements', 'iterable')], ['@param iterable<T> $elements']),
            self::factory('empty', []),
            new MethodTemplate('of', true, [self::elements()], 'static', 'construct', null, true, false),
        ];
        if ($doctrineNames) {
            $methods[] = self::query('first', [], '?T', true);
            $methods[] = self::query('last', [], '?T', true);
        }
        $methods[] = self::query('firstOrFail', [], 'T', true);
        $methods[] = self::query('lastOrFail', [], 'T', true);
        if ($doctrineNames) {
            $methods[] = self::query('get', [self::index()], '?T', true);
        }
        $methods[] = self::query('getOrFail', [self::index()], 'T', true);
        $methods[] = self::query('at', [new ParameterTemplate('position', 'int')], '?T', true);
        $methods[] = self::query('single', [], 'T', true);
        $methods[] = self::query('singleOrNull', [], '?T', true);
        $methods[] = self::query('random', [], '?T', true);
        $methods[] = self::query('find', [self::predicate()], '?T', true, ['@param Closure(T, int): bool $predicate']);
        $methods[] = self::query('findLast', [self::predicate()], '?T', true, ['@param Closure(T, int): bool $predicate']);
        $methods[] = self::query('minBy', [self::selector()], '?T', true, ['@param Closure(T): mixed $selector']);
        $methods[] = self::query('maxBy', [self::selector()], '?T', true, ['@param Closure(T): mixed $selector']);
        if ($doctrineNames) {
            $methods[] = self::query('contains', [self::element()], 'bool', false);
        }
        $methods[] = self::query('containsAll', [self::elements()], 'bool', false);
        $methods[] = self::query('containsAny', [self::elements()], 'bool', false);
        $methods[] = self::query('positionOf', [self::element()], '?int', false);

        return [...$methods, ...match ($variant) {
            Variant::Immutable => self::withers(),
            Variant::Mutable => self::mutators(true),
            Variant::Odm => self::mutators(false),
        }];
    }

    /**
     * @return list<MethodTemplate>
     */
    private static function withers(): array
    {
        return [
            self::query('with', [self::elements()], 'self', false),
            self::query('withPrepended', [self::elements()], 'self', false),
            self::query('withSet', [self::index(), self::element()], 'self', false),
            self::query('withInsertedAt', [self::index(), self::element()], 'self', false),
            self::query('withoutElement', [self::elements()], 'self', false),
            self::query('withReplaced', [new ParameterTemplate('old', 'T'), new ParameterTemplate('new', 'T')], 'self', false),
        ];
    }

    /**
     * @param bool $doctrineNames false for ODM, whose set/remove/removeElement keep Doctrine's signatures
     *
     * @return list<MethodTemplate>
     */
    private static function mutators(bool $doctrineNames): array
    {
        $methods = [
            self::mutator('push', [self::elements()], 'void', 'doPush', false),
            self::mutator('unshift', [self::elements()], 'void', 'doUnshift', false),
        ];
        if ($doctrineNames) {
            $methods[] = self::mutator('set', [self::index(), self::element()], 'void', 'doSetAt', false);
        }
        $methods[] = self::mutator('insertAt', [self::index(), self::element()], 'void', 'doInsertAt', false);
        if ($doctrineNames) {
            $methods[] = self::mutator('removeElement', [self::element()], 'bool', 'doRemoveElement', false);
            $methods[] = self::mutator('remove', [self::index()], '?T', 'doRemoveAt', true);
        }
        $methods[] = self::mutator('pop', [], '?T', 'doPop', true);
        $methods[] = self::mutator('shift', [], '?T', 'doShift', true);

        return $methods;
    }

    /**
     * @param list<ParameterTemplate> $parameters
     * @param list<string> $phpDoc
     */
    private static function factory(string $name, array $parameters, array $phpDoc = []): MethodTemplate
    {
        return new MethodTemplate($name, true, $parameters, 'static', 'construct', null, true, true, $phpDoc);
    }

    /**
     * @param list<ParameterTemplate> $parameters
     * @param 'T'|'?T'|'self'|'bool'|'?int' $returns
     * @param list<string> $phpDoc
     */
    private static function query(string $name, array $parameters, string $returns, bool $declaredByInterface, array $phpDoc = []): MethodTemplate
    {
        return new MethodTemplate($name, false, $parameters, $returns, 'delegate', 'do' . ucfirst($name), true, $declaredByInterface, $phpDoc);
    }

    /**
     * @param list<ParameterTemplate> $parameters
     * @param 'void'|'bool'|'?T' $returns
     */
    private static function mutator(string $name, array $parameters, string $returns, string $delegate, bool $declaredByInterface): MethodTemplate
    {
        return new MethodTemplate($name, false, $parameters, $returns, 'delegate', $delegate, false, $declaredByInterface);
    }

    private static function element(): ParameterTemplate
    {
        return new ParameterTemplate('element', 'T');
    }

    private static function elements(): ParameterTemplate
    {
        return new ParameterTemplate('elements', 'T', true);
    }

    private static function index(): ParameterTemplate
    {
        return new ParameterTemplate('index', 'int');
    }

    private static function predicate(): ParameterTemplate
    {
        return new ParameterTemplate('predicate', 'Closure');
    }

    private static function selector(): ParameterTemplate
    {
        return new ParameterTemplate('selector', 'Closure');
    }
}
