#!/bin/sh
set -eu

root_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
environment_file=${1:-/opt/nilaiku/.env}
backup_dir=${BACKUP_DIR:-/var/backups/nilaiku}
compose_file="$root_dir/docker-compose.production.yml"

case "$backup_dir" in
    /var/backups/nilaiku|/var/backups/nilaiku/*) ;;
    *) exit 1 ;;
esac

umask 077
mkdir -p "$backup_dir"
backup_file="$backup_dir/nilaiku-$(date -u +%Y%m%dT%H%M%SZ).dump"
docker compose --env-file "$environment_file" -f "$compose_file" exec -T database sh -lc 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$backup_file"
find "$backup_dir" -type f -name 'nilaiku-*.dump' -mtime +6 -delete
