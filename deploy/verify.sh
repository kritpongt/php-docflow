#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

PROJECT=php-docflow-verify
export APP_ENV_FILE="./app.env"
export APP_IMAGE_TAG="verify"
export APP_BIND_IP="127.0.0.1"
export APP_PORT="18080"

if [[ ! -r "$APP_ENV_FILE" ]]; then
	echo "missing: $APP_ENV_FILE" >&2
	exit 1
fi

compose(){
	docker compose -p "$PROJECT" -f docker-compose.prod.yml "$@"
}

cleanup(){
	local rc=$?
	if (( rc != 0 )); then
		echo "--- preflight failed (rc=$rc) ---" >&2
		compose logs --no-color --tail=100 || true
	fi
	compose down -v --remove-orphans || true
	exit "$rc"
}
trap cleanup EXIT

compose config --quiet
compose config --volumes
compose build nginx app
compose up -d --wait --wait-timeout 90
curl -fsS "http://$APP_BIND_IP:$APP_PORT/api/healthcheck"