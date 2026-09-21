<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Support\Migrator;

Migrator::run(false);

echo "Migrations up to date.\n";
