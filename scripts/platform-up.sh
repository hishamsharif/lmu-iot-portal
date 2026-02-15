#!/bin/bash

set -euo pipefail

PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:${PATH:-}"

usage() {
    cat <<'EOF'
Usage: ./scripts/platform-up.sh [--vite]

Options:
  --vite    Start Vite dev server (npm run dev)
  -h, --help
EOF
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

is_process_running() {
    local pid="${1:-}"
    local search_term="${2:-}"

    if [[ -z "$pid" ]] || [[ ! "$pid" =~ ^[0-9]+$ ]]; then
        return 1
    fi

    if ! kill -0 "$pid" >/dev/null 2>&1; then
        return 1
    fi

    if [[ -n "$search_term" ]]; then
        local actual_cmd
        actual_cmd=$(ps -p "$pid" -o command= 2>/dev/null || echo "")

        # Use a more flexible check: it should contain both 'php' and parts of the command
        # or just the search term if it's specific enough.
        # We'll use a simple substring check.
        if [[ "$actual_cmd" != *"$search_term"* ]]; then
            return 1
        fi
    fi

    return 0
}

include_vite=0

for arg in "$@"; do
    case "$arg" in
        --vite)
            include_vite=1
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $arg" >&2
            usage
            exit 1
            ;;
    esac
done

script_dir="$(cd "${BASH_SOURCE[0]%/*}" && pwd)"
repo_root="$(cd "$script_dir/.." && pwd)"
artisan_path="$repo_root/artisan"
state_dir="$repo_root/storage/platform"
log_dir="$repo_root/storage/logs/platform"
manifest_path="$state_dir/manifest"

mkdir -p "$state_dir" "$log_dir"

if ! command_exists docker; then
    echo "Error: docker is required." >&2
    exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
    echo "Error: docker compose is required." >&2
    exit 1
fi

if ! command_exists php; then
    echo "Error: php is required." >&2
    exit 1
fi

if [[ ! -f "$artisan_path" ]]; then
    echo "Error: artisan not found at $artisan_path" >&2
    exit 1
fi

if [[ "$include_vite" -eq 1 ]]; then
    if ! command_exists npm; then
        echo "Warning: npm is not available. Skipping Vite." >&2
        include_vite=0
    elif [[ ! -d "$repo_root/node_modules" ]]; then
        echo "Warning: node_modules directory not found. Skipping Vite." >&2
        include_vite=0
    fi
fi

echo "Starting NATS broker..."
(cd "$repo_root" && docker compose -f docker-compose.nats.yml up -d)

services=(
    $'reverb\tphp artisan reverb:start --port=8090\treverb.log'
    $'listen-states\tphp artisan iot:listen-for-device-states\tlisten-states.log'
    $'listen-presence\tphp artisan iot:listen-for-device-presence\tlisten-presence.log'
    $'ingest-telemetry\tphp artisan iot:ingest-telemetry\tingest-telemetry.log'
    $'horizon\tphp artisan horizon\thorizon.log'
    $'schedule\tphp artisan schedule:work\tschedule.log'
)

if [[ "$include_vite" -eq 1 ]]; then
    services+=($'vite\tnpm run dev\tvite.log')
fi

summary_lines=()
failed_count=0

start_service() {
    local name="$1"
    local command="$2"
    local log_name="$3"
    local pid_file="$state_dir/$name.pid"
    local log_file="$log_dir/$log_name"
    local status=""
    local pid="-"

    if [[ -f "$pid_file" ]]; then
        local existing_pid
        existing_pid="$(tr -d '[:space:]' < "$pid_file")"

        if is_process_running "$existing_pid" "$command"; then
            status="running"
            pid="$existing_pid"
            summary_lines+=("$name|$status|$pid|$log_file|$command")
            return
        fi

        rm -f "$pid_file"
    fi

    touch "$log_file"

    nohup bash -c "cd '$repo_root' && exec $command" >> "$log_file" 2>&1 &
    local started_pid=$!

    sleep 1

    if is_process_running "$started_pid" "$command"; then
        printf '%s\n' "$started_pid" > "$pid_file"
        status="started"
        pid="$started_pid"
    else
        status="failed"
        failed_count=$((failed_count + 1))
        rm -f "$pid_file"
    fi

    summary_lines+=("$name|$status|$pid|$log_file|$command")
}

for entry in "${services[@]}"; do
    IFS=$'\t' read -r name command log_name <<< "$entry"
    start_service "$name" "$command" "$log_name"
done

{
    echo "# name|pid|command|log"
    for line in "${summary_lines[@]}"; do
        IFS='|' read -r name status pid log_file command <<< "$line"

        if [[ "$pid" =~ ^[0-9]+$ ]]; then
            echo "$name|$pid|$command|$log_file"
        fi
    done
} > "$manifest_path"

echo
echo "Platform startup summary:"
for line in "${summary_lines[@]}"; do
    IFS='|' read -r name status pid log_file command <<< "$line"
    printf '  - %-16s %-8s pid=%-8s log=%s\n' "$name" "$status" "$pid" "$log_file"
done

echo
if [[ "$failed_count" -gt 0 ]]; then
    echo "Startup completed with $failed_count failure(s)." >&2
    exit 1
fi

echo "Platform is up."
