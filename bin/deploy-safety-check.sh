#!/usr/bin/env bash
#
# Find out whether Cloudways' Git deployment DELETES files in the
# deployment path that are absent from the repo.
#
# Why this matters: this repo tracks only our own code. If a deploy
# deletes untracked files, it would wipe ~356 MB of third-party plugins
# and ~248 MB of uploads. Test before trusting it.
#
# Usage:
#   ./bin/deploy-safety-check.sh arm     # plant marker files on staging
#   <then trigger a deploy in the Cloudways panel>
#   ./bin/deploy-safety-check.sh check   # report what survived
#   ./bin/deploy-safety-check.sh clean   # remove the markers
#
set -euo pipefail

SSH_HOST="${SSH_HOST:-dhd-cloudways}"
REMOTE_ROOT="${REMOTE_ROOT:-/home/master/applications/rdxcacbyuw/public_html}"

# Marker files, in increasing order of how much their loss would hurt.
MARKERS=(
  "DEPLOY-SENTINEL.txt"
  "wp-content/DEPLOY-SENTINEL.txt"
  "wp-content/mu-plugins/DEPLOY-SENTINEL.txt"
  "wp-content/themes/DEPLOY-SENTINEL.txt"
  "wp-content/plugins/DEPLOY-SENTINEL.txt"
  "wp-content/uploads/DEPLOY-SENTINEL.txt"
)

# Real files that exist on the server but are NOT in the repo.
# These are the genuine canaries.
CANARIES=(
  "wp-content/mu-plugins/elementor-safe-mode.php"
  "wp-content/mu-plugins/automation-by-installatron.php"
  "wp-content/plugins/woocommerce/woocommerce.php"
  "wp-content/themes/hello-biz/style.css"
  "wp-config.php"
)

case "${1:-}" in
  arm)
    echo "Planting markers on $SSH_HOST ..."
    for m in "${MARKERS[@]}"; do
      ssh "$SSH_HOST" "mkdir -p \"\$(dirname '$REMOTE_ROOT/$m')\" && printf 'deploy safety marker — safe to delete\n' > '$REMOTE_ROOT/$m'"
      echo "  armed  $m"
    done
    echo
    echo "Now trigger a deployment in the Cloudways panel, then run:"
    echo "  ./bin/deploy-safety-check.sh check"
    ;;

  check)
    echo "=== Markers (planted by this script) ==="
    gone=0
    for m in "${MARKERS[@]}"; do
      if ssh "$SSH_HOST" "test -f '$REMOTE_ROOT/$m'"; then
        printf "  SURVIVED  %s\n" "$m"
      else
        printf "  DELETED   %s\n" "$m"
        gone=$((gone + 1))
      fi
    done

    echo
    echo "=== Canaries (real untracked files) ==="
    for c in "${CANARIES[@]}"; do
      if ssh "$SSH_HOST" "test -e '$REMOTE_ROOT/$c'"; then
        printf "  SURVIVED  %s\n" "$c"
      else
        printf "  DELETED   %s\n" "$c"
        gone=$((gone + 1))
      fi
    done

    echo
    echo "=== Sizes (should be unchanged: plugins ~356M, uploads ~248M) ==="
    ssh "$SSH_HOST" "du -sh '$REMOTE_ROOT/wp-content/plugins' '$REMOTE_ROOT/wp-content/uploads' 2>/dev/null"

    echo
    if [[ "$gone" -eq 0 ]]; then
      echo "VERDICT: nothing was deleted. Deploying to public_html is safe."
    else
      echo "VERDICT: $gone item(s) deleted. DO NOT deploy to public_html."
      echo "         Narrow the deployment path, or track everything."
    fi
    ;;

  clean)
    for m in "${MARKERS[@]}"; do
      ssh "$SSH_HOST" "rm -f '$REMOTE_ROOT/$m'" && echo "  removed  $m"
    done
    ;;

  *)
    echo "Usage: $0 {arm|check|clean}" >&2
    exit 1
    ;;
esac
