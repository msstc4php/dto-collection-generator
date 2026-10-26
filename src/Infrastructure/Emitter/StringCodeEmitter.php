<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Emitter;

use Closure;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Selectable;
use LogicException;
use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollection\Behaviour\ImmutableListBehaviour;
use MSSTC4PHP\DtoCollection\Behaviour\MutableListBehaviour;
use MSSTC4PHP\DtoCollection\Behaviour\NumericBehaviour;
use MSSTC4PHP\DtoCollection\Behaviour\TextBehaviour;
use MSSTC4PHP\DtoCollection\Doctrine\OdmListBehaviour;
use MSSTC4PHP\DtoCollection\ElementType;
use MSSTC4PHP\DtoCollection\Equality\ComparatorEquality;
use MSSTC4PHP\DtoCollection\Equality\Equality;
use MSSTC4PHP\DtoCollection\Equality\StrictEquality;
use MSSTC4PHP\DtoCollection\ImmutableList;
use MSSTC4PHP\DtoCollection\MutableList;
use MSSTC4PHP\DtoCollection\TypedList;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\CodeEmitter;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Model\MethodCatalogue;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Model\MethodTemplate;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Model\ParameterTemplate;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Model\TypeShape;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\Capability;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\TargetProfile;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Validation\ClassFacts;

/**
 * Renders the class shape of dto-collection's docs/writing-a-typed-collection.md, laid out like its reference
 * classes (PER coding style).
 */
final class StringCodeEmitter implements CodeEmitter
{
    private const INDENT = '    ';

    public function emit(CollectionSpec $spec, TargetProfile $target, ClassFacts $facts): string
    {
        $shape = TypeShape::of($spec->elementType);
        $odm = $spec->variant === Variant::Odm;
        $override = $target->supports(Capability::OverrideAttribute);
        $dnf = $target->supports(Capability::DnfTypes);
        $comparators = array_values(array_filter(
            $shape->branches,
            static fn (string|array $branch): bool => is_array($branch) && count($branch) === 1 && $facts->isEquatable($branch[0]),
        ));
        $behaviours = array_values(array_filter([
            $spec->elementType->isNumeric() ? NumericBehaviour::class : null,
            $spec->elementType->isString() ? TextBehaviour::class : null,
        ]));
        $variantTrait = match ($spec->variant) {
            Variant::Immutable => ImmutableListBehaviour::class,
            Variant::Mutable => MutableListBehaviour::class,
            Variant::Odm => OdmListBehaviour::class,
        };
        $interfaces = match ($spec->variant) {
            Variant::Immutable => [ImmutableList::class, TypedList::class],
            Variant::Mutable => [MutableList::class, TypedList::class],
            Variant::Odm => [Collection::class, Selectable::class, MutableList::class, TypedList::class],
        };
        $fixed = [
            Closure::class,
            $variantTrait,
            ...$behaviours,
            ElementType::class,
            Equality::class,
            $comparators === [] ? StrictEquality::class : ComparatorEquality::class,
            ...$interfaces,
            'NoDiscard',
            ...($override ? ['Override'] : []),
        ];
        $imports = new ImportTable($spec->class, $fixed, $shape->classes());
        $name = $imports->name(...);
        $t = $shape->phpDoc($name);

        $lines = ['<?php', '', self::HEADER, '', 'declare(strict_types=1);', ''];
        if ($spec->class->namespace() !== '') {
            $lines[] = 'namespace ' . $spec->class->namespace() . ';';
            $lines[] = '';
        }
        foreach ($imports->imports() as $import) {
            $lines[] = 'use ' . $import . ';';
        }
        $lines[] = '';
        $lines[] = '/**';
        foreach ($interfaces as $interface) {
            $generic = in_array($interface, [Collection::class, Selectable::class], true) ? 'int, ' . $t : $t;
            $lines[] = sprintf(' * @implements %s<%s>', $name($interface), $generic);
        }
        if ($odm) {
            $lines[] = ' *';
            $lines[] = ' * @phpstan-consistent-constructor';
        }
        $lines[] = ' */';
        $lines[] = sprintf('%sclass %s implements %s', $odm ? '' : 'final ', $spec->class->shortName(), implode(', ', array_map($name, $interfaces)));
        $lines[] = '{';
        $lines[] = self::INDENT . sprintf('/** @use %s<%s> */', $name($variantTrait), $t);
        $lines[] = self::INDENT . sprintf('use %s;', $name($variantTrait));
        foreach ($behaviours as $behaviour) {
            $lines[] = '';
            $lines[] = self::INDENT . sprintf('use %s;', $name($behaviour));
        }
        $lines[] = '';
        $lines[] = self::INDENT . sprintf('/** @var %s<%s>|null */', $name(ElementType::class), $t);
        $lines[] = self::INDENT . sprintf('private static ?%s $type = null;', $name(ElementType::class));
        $lines[] = '';
        $lines[] = self::INDENT . sprintf('private static ?%s $sameness = null;', $name(Equality::class));

        foreach (MethodCatalogue::for($spec->variant) as $method) {
            $lines[] = '';
            array_push($lines, ...$this->method($method, $shape, $name, $dnf, $override, $odm));
        }

        $lines[] = '';
        $lines[] = self::INDENT . '/**';
        $lines[] = self::INDENT . sprintf(' * @return %s<%s>', $name(ElementType::class), $t);
        $lines[] = self::INDENT . ' */';
        $lines[] = self::INDENT . sprintf('protected function elementType(): %s', $name(ElementType::class));
        $lines[] = self::INDENT . '{';
        $lines[] = self::INDENT . self::INDENT . sprintf('return self::$type ??= %s;', $this->elementTypeExpression($shape, $name));
        $lines[] = self::INDENT . '}';
        $lines[] = '';
        $lines[] = self::INDENT . sprintf('protected function equality(): %s', $name(Equality::class));
        $lines[] = self::INDENT . '{';
        $lines[] = self::INDENT . self::INDENT . sprintf('return self::$sameness ??= %s;', $this->equalityExpression($comparators, $name));
        $lines[] = self::INDENT . '}';
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param Closure(string): string $name
     *
     * @return list<string>
     */
    private function method(MethodTemplate $method, TypeShape $shape, Closure $name, bool $dnf, bool $override, bool $odm): array
    {
        $lines = [];
        if ($method->phpDoc !== []) {
            $lines[] = self::INDENT . '/**';
            foreach ($method->phpDoc as $line) {
                $lines[] = self::INDENT . ' * ' . preg_replace('/\bT\b/', $shape->phpDoc($name), $line);
            }
            $lines[] = self::INDENT . ' */';
        }
        if ($override && $method->declaredByInterface) {
            $lines[] = self::INDENT . '#[' . $name('Override') . ']';
        }
        if ($method->noDiscard) {
            $lines[] = self::INDENT . '#[' . $name('NoDiscard') . ']';
        }
        $parameters = array_map(fn (ParameterTemplate $parameter): string => sprintf(
            '%s %s$%s',
            $this->parameterType($parameter, $shape, $name),
            $parameter->variadic ? '...' : '',
            $parameter->name,
        ), $method->parameters);
        $lines[] = self::INDENT . sprintf(
            'public %sfunction %s(%s): %s',
            $method->static ? 'static ' : '',
            $method->name,
            implode(', ', $parameters),
            $this->returnType($method, $shape, $name, $dnf),
        );
        $lines[] = self::INDENT . '{';
        $lines[] = self::INDENT . self::INDENT . $this->body($method, $odm);
        $lines[] = self::INDENT . '}';

        return $lines;
    }

    /**
     * @param Closure(string): string $name
     */
    private function parameterType(ParameterTemplate $parameter, TypeShape $shape, Closure $name): string
    {
        return match ($parameter->type) {
            'T' => $shape->native($name),
            'Closure' => $name(Closure::class),
            default => $parameter->type,
        };
    }

    /**
     * @param Closure(string): string $name
     */
    private function returnType(MethodTemplate $method, TypeShape $shape, Closure $name, bool $dnf): string
    {
        return match ($method->returns) {
            'T' => $shape->native($name),
            '?T' => $shape->nullableNative($name, $dnf) ?? throw new LogicException('An intersection element type needs DNF types; the validator rejects it below PHP 8.2'),
            default => $method->returns,
        };
    }

    private function body(MethodTemplate $method, bool $odm): string
    {
        $arguments = implode(', ', array_map(
            static fn (ParameterTemplate $parameter): string => ($parameter->variadic ? '...' : '') . '$' . $parameter->name,
            $method->parameters,
        ));
        if ($method->body === 'construct') {
            // The final variants construct self; ODM's class is subclassed, so its factories keep the subclass.
            $class = $odm ? 'static' : 'self';

            return $method->parameters === [] ? sprintf('return new %s();', $class) : sprintf('return new %s($%s);', $class, $method->parameters[0]->name);
        }
        $call = sprintf('$this->%s(%s);', (string) $method->delegate, $arguments);

        return $method->returns === 'void' ? $call : 'return ' . $call;
    }

    /**
     * @param Closure(string): string $name
     */
    private function elementTypeExpression(TypeShape $shape, Closure $name): string
    {
        $branches = array_map(static function (string|array $branch) use ($name): string {
            if (is_string($branch)) {
                return sprintf('%s::%s()', $name(ElementType::class), match ($branch) {
                    'int' => 'integer',
                    'bool' => 'boolean',
                    default => $branch,
                });
            }
            $classes = implode(', ', array_map(static fn (string $class): string => $name($class) . '::class', $branch));

            return sprintf('%s::%s(%s)', $name(ElementType::class), count($branch) === 1 ? 'ofClass' : 'intersection', $classes);
        }, $shape->branches);
        $expression = count($branches) === 1 ? $branches[0] : sprintf('%s::union(%s)', $name(ElementType::class), implode(', ', $branches));

        return $shape->nullable ? $expression . '->nullable()' : $expression;
    }

    /**
     * @param list<string|non-empty-list<string>> $comparators single-class branches whose class is Equatable
     * @param Closure(string): string $name
     */
    private function equalityExpression(array $comparators, Closure $name): string
    {
        if ($comparators === []) {
            return sprintf('new %s()', $name(StrictEquality::class));
        }
        $expression = '';
        foreach ($comparators as $index => $branch) {
            $class = $name(((array) $branch)[0]);
            $comparator = sprintf('%s::class, static fn (%s $left, %s $right): bool => $left->equals($right)', $class, $class, $class);
            $expression .= $index === 0 ? sprintf('%s::of(%s)', $name(ComparatorEquality::class), $comparator) : sprintf('->with(%s)', $comparator);
        }

        return $expression;
    }
}
