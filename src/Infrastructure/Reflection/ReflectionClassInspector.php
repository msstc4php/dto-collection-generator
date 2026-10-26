<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Reflection;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Annotations\AbstractDocument;
use MSSTC4PHP\DtoCollection\Equatable;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\ClassInspector;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassKind;
use ReflectionAttribute;
use ReflectionClass;
use Throwable;

/**
 * @internal
 */
final class ReflectionClassInspector implements ClassInspector
{
    public function kind(string $class): ?ClassKind
    {
        $reflection = $this->reflect($class);
        if (!$reflection instanceof ReflectionClass) {
            return null;
        }

        return match (true) {
            $reflection->isEnum() => ClassKind::Enum,
            $reflection->isInterface() => ClassKind::Interface,
            $reflection->isTrait() => ClassKind::Trait,
            default => ClassKind::OrdinaryClass,
        };
    }

    public function isA(string $class, string $parent): bool
    {
        return $this->reflect($class) instanceof ReflectionClass && is_a($class, $parent, true);
    }

    public function isEquatable(string $class): bool
    {
        return $this->isA($class, Equatable::class);
    }

    public function hasOdmMapping(string $class): bool
    {
        $reflection = $this->reflect($class);

        return $reflection instanceof ReflectionClass
            && $this->isOdmInstalled()
            && $reflection->getAttributes(AbstractDocument::class, ReflectionAttribute::IS_INSTANCEOF) !== [];
    }

    public function isOdmInstalled(): bool
    {
        return class_exists(DocumentManager::class);
    }

    public function fileOf(string $class): ?string
    {
        $file = $this->reflect($class)?->getFileName();

        return $file === false ? null : $file;
    }

    /**
     * @return ReflectionClass<object>|null
     */
    private function reflect(string $class): ?ReflectionClass
    {
        try {
            // class_exists() covers enums.
            if (class_exists($class) || interface_exists($class) || trait_exists($class)) {
                return new ReflectionClass($class);
            }
        } catch (Throwable) {
            // Its file fails to load (a missing parent, a parse error): reported as a missing class.
        }

        return null;
    }
}
