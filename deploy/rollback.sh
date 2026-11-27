#!/usr/bin/env bash
source "$(dirname "$0")/common.sh"

PREV="$(cat "$TAGS/previous-tag")"
docker image inspect "php-docflow:$PREV" >/dev/null
docker image inspect "php-docflow-web:$PREV" >/dev/null

read_compose_env
write_compose_env "$PREV" "$APP_BIND_IP" "$APP_PORT"
compose up -d --remove-orphans
wait_health