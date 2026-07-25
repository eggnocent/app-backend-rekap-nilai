#!/bin/sh
set -eu

root_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
environment_file=${1:-/opt/nilaiku/.env}
compose_file="$root_dir/docker-compose.production.yml"

docker compose --env-file "$environment_file" -f "$compose_file" exec -T database sh -lc 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < "$root_dir/database/seeders/visualization.sql"
