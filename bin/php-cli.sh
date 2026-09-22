#!/bin/bash
# Print the path to a real PHP *CLI* binary, or fail loudly.
#
# On this cPanel host the bare `php` in PATH is the CGI binary. php-cgi does not populate
# $argv, so `php artisan migrate --force` silently drops its arguments: artisan sees no
# command, prints the command list, and exits 0. A deploy script that trusts that exit code
# reports success while never having migrated — which is how unapplied migrations reached
# production before. Always resolve through here rather than calling `php` directly.
#
# Override with PHP_CLI_BIN=/path/to/php if the search below ever picks the wrong one.

for candidate in \
    "$PHP_CLI_BIN" \
    php8.3 php83 php8.2 php82 \
    /opt/cpanel/ea-php83/root/usr/bin/php \
    /opt/cpanel/ea-php82/root/usr/bin/php \
    /usr/local/bin/ea-php83 \
    /usr/local/bin/ea-php82 \
    /usr/local/bin/php \
    php
do
    [ -n "$candidate" ] || continue
    bin=$(command -v "$candidate" 2>/dev/null) || continue
    # PHP_SAPI is the only claim worth trusting here: the CLI SAPI always populates $argv,
    # the CGI one may not, and the file name tells you nothing about which you have.
    if [ "$("$bin" -r 'echo PHP_SAPI;' 2>/dev/null)" = "cli" ]; then
        echo "$bin"
        exit 0
    fi
done

echo "ERROR: no PHP CLI binary found — every candidate resolved to a non-CLI SAPI." >&2
echo "       Artisan arguments would be dropped silently. Find one with:" >&2
echo "         for P in /opt/cpanel/ea-php*/root/usr/bin/php /usr/local/bin/php* php; do" >&2
echo "           command -v \"\$P\" >/dev/null && echo \"\$P => \$(\"\$P\" -r 'echo PHP_SAPI;')\"; done" >&2
echo "       then re-run with PHP_CLI_BIN=/that/path" >&2
exit 1
