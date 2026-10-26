<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Presentation\Cli;

use MSSTC4PHP\DtoCollectionGenerator\Presentation\Cli\JsonReport;
use PHPUnit\Framework\TestCase;

final class JsonReportTest extends TestCase
{
    public function testReadableAsIs(): void
    {
        self::assertSame(
            "{\n    \"status\": \"ok\",\n    \"diagnostics\": [],\n    \"changes\": [\n        {\n            \"kind\": \"create\",\n            \"path\": \"src/Ünicode/ItemList.php\"\n        }\n    ]\n}",
            JsonReport::encode(['status' => 'ok', 'diagnostics' => [], 'changes' => [['kind' => 'create', 'path' => 'src/Ünicode/ItemList.php']]]),
        );
    }

    public function testInvalidUtf8IsReplaced(): void
    {
        self::assertStringContainsString("\"path\": \"src/\u{FFFD}.php\"", JsonReport::encode(['status' => 'ok', 'diagnostics' => [], 'changes' => [['kind' => 'create', 'path' => "src/\xE9.php"]]]));
    }
}
