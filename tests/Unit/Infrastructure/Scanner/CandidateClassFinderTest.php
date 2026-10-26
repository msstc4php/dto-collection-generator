<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Infrastructure\Scanner;

use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Scanner\CandidateClassFinder;
use PHPUnit\Framework\TestCase;

final class CandidateClassFinderTest extends TestCase
{
    public function testEveryMarkedClassOfAFile(): void
    {
        $code = <<<'PHP_WRAP'
            <?php
            namespace App;
        
            use MSSTC4PHP\DtoCollection\Attribute\TypedCollection;
        
            #[TypedCollection(class: 'App\FirstList')]
            final class First {}
        
            final class Unmarked {}
        
            #[\Other\Attribute, TypedCollection(class: 'App\SecondList')]
            #[TypedCollection(class: 'App\ThirdList')]
            enum Second {}
            PHP_WRAP;

        self::assertSame([
            ['class' => 'App\First', 'lines' => [6]],
            ['class' => 'App\Second', 'lines' => [11, 12]],
        ], (new CandidateClassFinder())->find($code));
    }

    public function testAnonymousClassesAreSkipped(): void
    {
        $code = "<?php\n\$object = new #[\\MSSTC4PHP\\DtoCollection\\Attribute\\TypedCollection(class: 'A')] class {};\n";

        self::assertSame([], (new CandidateClassFinder())->find($code));
    }
}
