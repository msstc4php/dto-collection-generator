<?php

declare(strict_types=1);

// Generates Doctrine ODM's persistent subclass of every golden ODM collection: an incompatible signature is a fatal error.

use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\PersistentCollection\DefaultPersistentCollectionGenerator;

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require __DIR__ . '/../../vendor/autoload.php';

$directory = $argv[1] ?? throw new InvalidArgumentException('Usage: odm-subclass.php <directory of golden files>');
$generator = new DefaultPersistentCollectionGenerator(sys_get_temp_dir(), 'DtoCollectionGeneratorProxies');
$checked = 0;
foreach (glob(rtrim($directory, '/') . '/*.php.golden') ?: [] as $file) {
    $code = (string) file_get_contents($file);
    if (preg_match('/^class (\w+) implements Collection, /m', $code, $match) !== 1) {
        continue;
    }
    require_once $file;
    echo 'ok ', $generator->loadClass('App\Collection\\' . $match[1], Configuration::AUTOGENERATE_EVAL), ' on PHP ', PHP_VERSION, "\n";
    $checked++;
}
if ($checked === 0) {
    throw new RuntimeException('No ODM collection in ' . $directory);
}
