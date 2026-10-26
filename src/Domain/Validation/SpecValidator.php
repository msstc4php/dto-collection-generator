<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Validation;

use Closure;
use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Model\TypeShape;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Shared\Path;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassKind;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\Capability;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\TargetProfile;

/**
 * Rejects the specs that would not give a working class, before anything is written (spec §7). Missing classes and
 * traits never get here: ElementType refuses them when the spec is built.
 *
 * @internal
 */
final class SpecValidator
{
    /**
     * @param list<CollectionSpec> $specs
     * @param Closure(string $file): bool $isGeneratedFile whether an existing class file was written by this generator
     * @param Closure(ClassName $class): ?string $ownTarget the collection's target file when we wrote it: then the class
     *                                                      is located, not loaded, as an output for an older
     *                                                      dto-collection may end in a fatal error PHP cannot catch
     *
     * @return list<CollectionSpec> the specs without errors
     */
    public function validate(array $specs, TargetProfile $target, ClassFacts $facts, Diagnostics $diagnostics, Closure $isGeneratedFile, Closure $ownTarget): array
    {
        $valid = [];
        $taken = [];
        foreach ($specs as $spec) {
            $problems = new Diagnostics();
            $first = $taken[$spec->class->key()] ?? null;
            if ($first !== null) {
                $problems->error(sprintf(
                    '%s: another spec already generates %s (%s); PHP class names ignore case',
                    $spec->class->fullName(),
                    $first->class->fullName(),
                    $first->origin->absolute(),
                ), $spec->origin);
            } elseif ($spec->class->namespace() === '') {
                $problems->error(sprintf('%s: a collection needs a namespace; a file without one cannot import the classes it uses', $spec->class->fullName()), $spec->origin);
            } elseif (!$this->isNamedLikeItsElement($spec, $problems)) {
                $taken[$spec->class->key()] = $spec;
                $ownFile = $ownTarget($spec->class);
                if ($ownFile === null) {
                    $this->checkExisting($spec, $facts, $problems, $isGeneratedFile);
                } else {
                    $this->checkDeclaredElsewhere($spec, $ownFile, $facts, $problems);
                }
            }
            $this->checkElementType($spec, $target, $facts, $problems);
            $diagnostics->merge($problems);
            if (!$problems->hasErrors()) {
                $valid[] = $spec;
            }
        }

        return $valid;
    }

    private function isNamedLikeItsElement(CollectionSpec $spec, Diagnostics $problems): bool
    {
        foreach (TypeShape::of($spec->elementType)->classes() as $class) {
            if (strcasecmp($class, $spec->class->fullName()) === 0) {
                $problems->error(sprintf('%s: a collection cannot have the name of its element class %s', $spec->class->fullName(), $class), $spec->origin);

                return true;
            }
        }

        return false;
    }

    private function checkDeclaredElsewhere(CollectionSpec $spec, string $target, ClassFacts $facts, Diagnostics $problems): void
    {
        $located = $facts->locate($spec->class->fullName());
        if ($located !== null && Path::normalize($located) !== Path::normalize($target)) {
            $problems->error(sprintf(
                '%s is also declared in %s, which the autoloader would load instead of the generated %s; remove one of them',
                $spec->class->fullName(),
                Path::normalize($located),
                $target,
            ), $spec->origin);
        }
    }

    /**
     * @param Closure(string $file): bool $isGeneratedFile
     */
    private function checkExisting(CollectionSpec $spec, ClassFacts $facts, Diagnostics $problems, Closure $isGeneratedFile): void
    {
        $class = $spec->class->fullName();
        if (!$facts->kind($class) instanceof ClassKind) {
            return;
        }
        $file = $facts->fileOf($class);
        if ($file === null) {
            $problems->error(sprintf('%s already exists, built into PHP or an extension; choose another class name', $class), $spec->origin);
        } elseif (!$isGeneratedFile($file)) {
            $problems->error(sprintf('%s already exists in %s, which dto-collection-generator did not write; choose another class name', $class, $file), $spec->origin);
        }
    }

    private function checkElementType(CollectionSpec $spec, TargetProfile $target, ClassFacts $facts, Diagnostics $problems): void
    {
        $collection = $spec->class->fullName();
        $shape = TypeShape::of($spec->elementType);
        $this->checkMarkedClass($spec, $shape, $facts, $problems);
        if ($shape->hasIntersection() && !$target->supports(Capability::DnfTypes)) {
            $problems->error(sprintf(
                '%s: an intersection element type needs target PHP %s or newer, where %s can be declared; the target is PHP %s',
                $collection,
                Capability::DnfTypes->minimumVersion(),
                $shape->nullableNative(static fn (string $name): string => $name),
                $target->php,
            ), $spec->origin);
        }
        if ($spec->variant === Variant::Odm) {
            $this->checkOdm($spec, $shape, $facts, $problems);
        }
    }

    private function checkMarkedClass(CollectionSpec $spec, TypeShape $shape, ClassFacts $facts, Diagnostics $problems): void
    {
        $marked = $spec->markedClass?->fullName();
        if ($marked === null) {
            return;
        }
        foreach ($shape->branches as $branch) {
            if (is_array($branch) && array_filter($branch, static fn (string $class): bool => !$facts->isA($marked, $class)) === []) {
                return;
            }
        }
        $problems->error(sprintf(
            '%s: %s, which carries the attribute, is not an %s',
            $spec->class->fullName(),
            $marked,
            $shape->native(static fn (string $name): string => $name),
        ), $spec->origin);
    }

    private function checkOdm(CollectionSpec $spec, TypeShape $shape, ClassFacts $facts, Diagnostics $problems): void
    {
        $collection = $spec->class->fullName();
        if (!$facts->isOdmInstalled()) {
            $problems->error(sprintf('%s: the ODM variant needs doctrine/mongodb-odm, which is not installed', $collection), $spec->origin);

            return;
        }
        foreach ($shape->branches as $branch) {
            if (is_string($branch)) {
                $problems->error(sprintf('%s: an ODM collection holds documents; %s is not one', $collection, $branch), $spec->origin);

                return;
            }
        }
        if (count($shape->branches) > 1 || $shape->hasIntersection()) {
            // ODM's DefaultPersistentCollectionGenerator prints the null of a union return type as "?null": a parse error.
            $problems->error(sprintf(
                '%s: an ODM collection needs a single class or interface as its element type, not %s: Doctrine ODM cannot generate its persistent collection for a union or intersection',
                $collection,
                $shape->native(static fn (string $name): string => $name),
            ), $spec->origin);

            return;
        }
        foreach ($shape->classes() as $class) {
            if ($facts->kind($class) !== ClassKind::Interface && !$facts->hasOdmMapping($class)) {
                $problems->warning(sprintf('%s: %s has no ODM document attribute; make sure it is mapped (in XML, for example)', $collection, $class), $spec->origin);
            }
        }
    }
}
