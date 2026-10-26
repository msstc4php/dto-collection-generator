<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Spec;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Exception\InvalidClassName;

/**
 * A fully qualified class name that a generated class can be declared with.
 */
final class ClassName
{
    private const PATTERN = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/';

    /**
     * PHP's reserved words that cannot be a class name (namespace segments may use them since PHP 8.0).
     */
    private const RESERVED = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch', 'class',
        'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty',
        'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'false',
        'final', 'finally', 'float', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements',
        'include', 'include_once', 'instanceof', 'insteadof', 'int', 'interface', 'isset', 'iterable', 'list',
        'match', 'mixed', 'namespace', 'never', 'new', 'null', 'object', 'or', 'parent', 'print', 'private',
        'protected', 'public', 'readonly', 'require', 'require_once', 'return', 'self', 'static', 'string',
        'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var', 'void', 'while', 'xor', 'yield',
    ];

    private function __construct(private readonly string $name)
    {
    }

    public static function fromString(string $name): self
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw self::invalid($name, 'it is empty');
        }
        $full = str_starts_with($trimmed, '\\') ? substr($trimmed, 1) : $trimmed;
        if (preg_match(self::PATTERN, $full) !== 1) {
            throw self::invalid($name, 'it is not a valid class name');
        }
        $self = new self($full);
        if (in_array(strtolower($self->shortName()), self::RESERVED, true)) {
            throw self::invalid($name, sprintf('"%s" is a reserved word', $self->shortName()));
        }

        return $self;
    }

    public function fullName(): string
    {
        return $this->name;
    }

    public function shortName(): string
    {
        $separator = strrpos($this->name, '\\');

        return $separator === false ? $this->name : substr($this->name, $separator + 1);
    }

    public function namespace(): string
    {
        $separator = strrpos($this->name, '\\');

        return $separator === false ? '' : substr($this->name, 0, $separator);
    }

    /**
     * PHP compares class names case-insensitively.
     */
    public function key(): string
    {
        return strtolower($this->name);
    }

    public function equalsIgnoringCase(self $other): bool
    {
        return $this->key() === $other->key();
    }

    private static function invalid(string $name, string $reason): InvalidClassName
    {
        return new InvalidClassName(sprintf('"%s" cannot name a class: %s', $name, $reason));
    }
}
