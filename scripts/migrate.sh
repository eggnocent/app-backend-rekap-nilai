#!/bin/sh
set -eu

root_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
environment_file=${1:-/opt/nilaiku/.env}
compose_file="$root_dir/docker-compose.production.yml"

run_psql() {
    docker compose --env-file "$environment_file" -f "$compose_file" exec -T database sh -lc 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
}

run_psql <<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
SQL

for migration_file in "$root_dir"/database/migrations/*.sql; do
    [ -f "$migration_file" ] || continue
    migration_name=$(basename "$migration_file")
    applied=$(docker compose --env-file "$environment_file" -f "$compose_file" exec -T database sh -lc "psql -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\" -tAc \"SELECT 1 FROM schema_migrations WHERE version = '$migration_name'\"")
    if [ "$applied" = "1" ]; then
        continue
    fi
    run_psql < "$migration_file"
    docker compose --env-file "$environment_file" -f "$compose_file" exec -T database sh -lc "psql -v ON_ERROR_STOP=1 -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\" -c \"INSERT INTO schema_migrations (version) VALUES ('$migration_name')\""
done
