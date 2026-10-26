<?php

declare(strict_types=1);

// Generates Doctrine ODM's persistent subclass of the golden PetCollection: an incompatible signature is a fatal error.

use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\PersistentCollection\DefaultPersistentCollectionGenerator;

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require __DIR__ . '/../../vendor/autoload.php';

$directory = $argv[1] ?? throw new InvalidArgumentException('Usage: odm-subclass.php <directory of golden files>');
require rtrim($directory, '/') . '/PetCollection.php.golden';

$generator = new DefaultPersistentCollectionGenerator(sys_get_temp_dir(), 'DtoCollectionGeneratorProxies');
echo 'ok ', $generator->loadClass('App\Collection\PetCollection', Configuration::AUTOGENERATE_EVAL), ' on PHP ', PHP_VERSION, "\n";
