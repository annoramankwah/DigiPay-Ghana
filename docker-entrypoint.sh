#!/bin/bash
set -e

# Railway assigns a dynamic $PORT; the base image listens on 80 by default.
PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "Waiting for the database to accept connections..."
php -r '
require "bootstrap.php";
use App\Config\Database;
$max = 30;
for ($i = 0; $i < $max; $i++) {
    try {
        Database::serverConnection();
        echo "Database reachable.\n";
        exit(0);
    } catch (\Throwable $e) {
        echo "  ({$i}) not ready yet: {$e->getMessage()}\n";
        sleep(2);
    }
}
fwrite(STDERR, "Database never became reachable.\n");
exit(1);
'

echo "Running migrations..."
php migrate.php

echo "Seeding demo data (idempotent)..."
php seed.php

# Belt-and-braces: whatever mix of MPM modules the base image shipped
# with, force exactly one (prefork, required by mod_php) before starting.
echo "Normalizing Apache MPM modules..."
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf
rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork >/dev/null 2>&1 || true
ls -la /etc/apache2/mods-enabled/ | grep -i mpm || echo "  (no mpm_* modules listed)"

echo "Starting Apache on port ${PORT}..."
exec apache2-foreground
