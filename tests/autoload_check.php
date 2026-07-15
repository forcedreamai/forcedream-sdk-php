<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if (!class_exists('ForceDream\\ForceDream')) {
    fwrite(STDERR, "Autoload failed to resolve ForceDream\\ForceDream\n");
    exit(1);
}

echo "Autoload OK: ForceDream\\ForceDream resolved.\n";
