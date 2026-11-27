#!/usr/bin/env bash
source "$(dirname "$0")/common.sh"

cd "$REPO"

SHA="${1:-$(git rev-parse HEAD)}"
TAG="$(git rev-parse --short "$SHA")"

read_compose_env
PREV="$(cat "$TAGS/current-tag" 2>/dev/null || true)"

bash "$REPO/deploy/backup.sh"

write_compose_env "$TAG" "$APP_BIND_IP" "$APP_PORT"
compose build nginx app
compose up -d --remove-orphans
compose exec -T app php bin/console migrate
wait_health

if [ -n "$PREV" ] && [ "$PREV" != "$TAG" ]; then
  write_tag "$TAGS/previous-tag" "$PREV"
fi
write_tag "$TAGS/current-tag" "$TAG"

docker image prune -f --filter until=168h