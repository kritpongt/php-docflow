#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

export APP_IMAGE_TAG="${APP_IMAGE_TAG:-verify}"
export APP_BIND_IP="${APP_BIND_IP:-127.0.0.1}"
export APP_PORT="${APP_PORT:-18080}"

docker compose -f docker-compose.yml config --quiet
docker compose -f docker-compose.prod.yml config --quiet
docker compose -f docker-compose.prod.yml build nginx app
docker compose -f docker-compose.prod.yml up -d --wait --wait-timeout 120
curl -fsS "http://$APP_BIND_IP:$APP_PORT/api/healthcheck"
docker compose -f docker-compose.prod.yml down -v