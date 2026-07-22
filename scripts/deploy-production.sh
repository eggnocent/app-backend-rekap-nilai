#!/bin/sh
set -eu

root_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
environment_file=${1:-/opt/nilaiku/.env}
compose_file="$root_dir/docker-compose.production.yml"

git -C "$root_dir" pull --ff-only
"$root_dir/scripts/backup-postgres.sh" "$environment_file"
docker compose --env-file "$environment_file" -f "$compose_file" build app
docker compose --env-file "$environment_file" -f "$compose_file" up -d --remove-orphans
"$root_dir/scripts/migrate.sh" "$environment_file"
