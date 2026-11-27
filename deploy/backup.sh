#!/usr/bin/env bash
source "$(dirname "$0")/common.sh"

read_db_driver

BACKUP_DIR=/var/backups/php-docflow
mkdir -p "$BACKUP_DIR"

TS="$(date +%F_%H%M%S)"
TMP="$(mktemp "$BACKUP_DIR/.db-$TS.XXXXXX")"

dump_db() {
	case "$DB_DRIVER" in
		pgsql)
			compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"'
			;;
		mysql)
			compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump "$MYSQL_DATABASE"'
			;;
	esac
}

if dump_db | gzip > "$TMP"; then
	if [ -s "$TMP" ]; then
		chmod 600 "$TMP"
		mv "$TMP" "$BACKUP_DIR/db-$TS.sql.gz"
	else
		rm -f "$TMP"
		echo "backup is empty" >&2
		exit 1
	fi
else
	rm -f "$TMP"
	exit 1
fi

find "$BACKUP_DIR" -name 'db-*.sql.gz' -mtime +14 -delete