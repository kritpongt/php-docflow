#!/usr/bin/env bash
set -euo pipefail

REPO=/srv/php-docflow
COMPOSE_PROD="$REPO/docker-compose.prod.yml"
COMPOSE_ENV=/etc/php-docflow/compose.env
APP_ENV=/etc/php-docflow/app.env
TAGS=/etc/php-docflow

compose() {
	docker compose --env-file "$COMPOSE_ENV" \
		-f "$COMPOSE_PROD" "$@"
}

atomic_write() {
	local path=$1 tmp
	tmp="$(mktemp "${path}.XXXXXX")"
	cat > "$tmp"
	chmod 600 "$tmp"
	mv "$tmp" "$path"
}

write_compose_env() {
	local tag=$1 bind_ip=$2 port=$3
	printf 'APP_IMAGE_TAG=%s\nAPP_BIND_IP=%s\nAPP_PORT=%s\n' "$tag" "$bind_ip" "$port" \
		| atomic_write "$COMPOSE_ENV"
}

write_tag() {
	printf '%s\n' "$2" | atomic_write "$1"
}

read_compose_env() {
	if [ -f "$COMPOSE_ENV" ]; then
		. "$COMPOSE_ENV"
	fi
	APP_BIND_IP="${APP_BIND_IP:-192.168.168.28}"
	APP_PORT="${APP_PORT:-8080}"
}

wait_health() {
	local url="http://${APP_BIND_IP:-127.0.0.1}:${APP_PORT:-8080}/api/healthcheck"
	local i
	for i in $(seq 1 30); do
		if curl -fsS "$url" >/dev/null; then
			return 0
		fi
		sleep 2
	done
	echo "healthcheck failed: $url" >&2
	return 1
}

read_db_driver() {
	DB_DRIVER="$(sed -n 's/^[[:space:]]*DB_DRIVER[[:space:]]*=[[:space:]]*//p' "$APP_ENV" \
		| tail -n 1 | tr -d '\r' | tr -d '\"' | tr -d "'")"

	case "$DB_DRIVER" in
		mysql|pgsql) ;;
		*)
			echo "unsupported DB_DRIVER: $DB_DRIVER" >&2
			return 1
			;;
	esac
}