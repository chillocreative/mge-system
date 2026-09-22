#!/bin/bash
# Run an artisan command with a PHP binary that actually passes arguments through.
#
#   bash artisan.sh assets:import-olak
#   bash artisan.sh assets:import-olak --commit
#
# Calling `php artisan ...` directly on this host drops the arguments — see bin/php-cli.sh.
set -e
cd "$(dirname "$0")"
PHP=$(bash bin/php-cli.sh) || exit 1
exec "$PHP" artisan "$@"
