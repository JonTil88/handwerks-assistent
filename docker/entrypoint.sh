#!/bin/sh
set -e

# After a fresh "git clone" the dependencies are missing. They are installed
# once if needed, so "clone and run" works without an extra step.
if [ ! -f /app/vendor/autoload.php ]; then
    echo "Installing dependencies …"
    composer install --no-interaction --prefer-dist --no-progress --no-scripts
fi

# The container runs as root, the files should belong to the host user.
if [ -n "${DOCKER_UID}" ] && [ -n "${DOCKER_GID}" ] && [ "$(id -u)" = "0" ]; then
    chown -R "${DOCKER_UID}:${DOCKER_GID}" /app/vendor /app/var 2>/dev/null || true
fi

exec "$@"
