<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Scanner;

use MSSTC4PHP\DtoCollection\Attribute\TypedCollection;
use PhpParser\Error;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Finds the class-likes of a file that carry #[TypedCollection], without loading the file: only those get loaded.
 *
 * @phpstan-type Candidate array{class: string, lines: list<int>}
 */
final class CandidateClassFinder
{
    private readonly Parser $parser;

    public function __construct()
    {
        // The project's own code may use syntax newer than the generator's PHP floor.
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return list<Candidate> each class with the lines of its TypedCollection attributes, in declaration order
     *
     * @throws Error when the file does not parse
     */
    public function find(string $code): array
    {
        $statements = $this->parser->parse($code) ?? [];
        $traverser = new NodeTraverser(new NameResolver());
        $statements = $traverser->traverse($statements);

        $candidates = [];
        foreach ((new NodeFinder())->findInstanceOf($statements, ClassLike::class) as $class) {
            $lines = [];
            foreach ($class->attrGroups as $group) {
                foreach ($group->attrs as $attribute) {
                    if (strcasecmp($attribute->name->toString(), TypedCollection::class) === 0) {
                        $lines[] = $attribute->getStartLine();
                    }
                }
            }
            if ($lines !== [] && $class->namespacedName !== null) {
                $candidates[] = ['class' => $class->namespacedName->toString(), 'lines' => $lines];
            }
        }

        return $candidates;
    }
}
