#!/bin/bash

set -euo pipefail

PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:${PATH:-}"

usage() {
    cat <<'EOF'
Usage: ./scripts/composer-install.sh [composer arguments...]

Installs Composer dependencies using Docker, so local Composer is not required.

Default command:
  composer install --no-interaction --prefer-dist --no-progress --ignore-platform-reqs
EOF
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
    usage
    exit 0
fi

script_dir="$(cd "${BASH_SOURCE[0]%/*}" && pwd)"
repo_root="$(cd "$script_dir/.." && pwd)"

if ! command_exists docker; then
    echo "Error: docker is required." >&2
    exit 1
fi

if [[ ! -f "$repo_root/composer.json" ]]; then
    echo "Error: composer.json not found in $repo_root" >&2
    exit 1
fi

user_id="$(id -u)"
group_id="$(id -g)"

if [[ "$#" -gt 0 ]]; then
    composer_args=("$@")
else
    composer_args=(
        install
        --no-interaction
        --prefer-dist
        --no-progress
        --ignore-platform-reqs
    )
fi

echo "Running Composer in Docker..."
(cd "$repo_root" && docker run --rm \
    -u "${user_id}:${group_id}" \
    -e COMPOSER_ALLOW_SUPERUSER=1 \
    -e COMPOSER_HOME=/tmp/composer \
    -v "$repo_root:/app" \
    -w /app \
    composer:2 \
    composer "${composer_args[@]}")

echo
echo "Composer dependencies installed."
