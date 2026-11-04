#!/usr/bin/env bash
#
# Pull DHD's custom code down from the Cloudways staging server.
#
#   ./bin/pull-from-staging.sh              # real sync
#   DRY_RUN=1 ./bin/pull-from-staging.sh    # preview only
#   SSH_HOST=other-alias ./bin/pull-from-staging.sh
#
# Only code we wrote is synced. WP core, third-party plugins and parent
# themes are managed by WordPress' own updater and stay on the server.
#
set -euo pipefail

SSH_HOST="${SSH_HOST:-dhd-cloudways}"
REMOTE_ROOT="${REMOTE_ROOT:-/home/master/applications/rdxcacbyuw/public_html}"
LOCAL_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Paths relative to wp-content/ that we keep in git.
PATHS=(
  mu-plugins
  themes/hello-biz-child-main
  themes/hello-theme-child-master
  plugins/heat-loss-calculator
)

EXCLUDES=(
  --exclude=.git
  --exclude=.duckversions
  --exclude=.DS_Store
  --exclude=node_modules
  --exclude=automation-by-installatron.php
  --exclude=elementor-safe-mode.php
  --exclude='*.log'
  --exclude='*.bak'
  --exclude='*.orig'
  --exclude='*.sql'
  --exclude='*.zip'
)

FLAGS=(-a --delete)
[[ "${DRY_RUN:-0}" == "1" ]] && FLAGS+=(--dry-run -v)
# Compression and stats are GNU-rsync only; macOS ships openrsync.
rsync --version 2>&1 | grep -qi openrsync || FLAGS+=(-z --info=stats1)

echo "host   : $SSH_HOST"
echo "remote : $REMOTE_ROOT/wp-content/"
echo "local  : $LOCAL_ROOT/wp-content/"
[[ "${DRY_RUN:-0}" == "1" ]] && echo "mode   : DRY RUN (nothing written)"
echo

for p in "${PATHS[@]}"; do
  if ! ssh "$SSH_HOST" "test -d '$REMOTE_ROOT/wp-content/$p'"; then
    echo "skip   : wp-content/$p (not on server)"
    continue
  fi
  echo "sync   : wp-content/$p"
  mkdir -p "$LOCAL_ROOT/wp-content/$p"
  rsync "${FLAGS[@]}" "${EXCLUDES[@]}" \
    "$SSH_HOST:$REMOTE_ROOT/wp-content/$p/" \
    "$LOCAL_ROOT/wp-content/$p/"
done

echo
echo "Done. Review with:  git -C \"$LOCAL_ROOT\" status"
