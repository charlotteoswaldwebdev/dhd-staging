#!/usr/bin/env bash
#
# Confirm the staging server matches this repo byte for byte.
#
# Needed because a deploy is asynchronous: a green CI run means Cloudways
# ACCEPTED the request, not that the files landed. This compares MD5 sums of
# every tracked file.
#
#   ./bin/verify-sync.sh
#
set -euo pipefail

SSH_HOST="${SSH_HOST:-dhd-cloudways}"
REMOTE_ROOT="${REMOTE_ROOT:-/home/master/applications/rdxcacbyuw/public_html}"
LOCAL_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PATHS="mu-plugins themes/hello-biz-child-main themes/hello-theme-child-master plugins/heat-loss-calculator"

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

# Server-only files are excluded on both sides so they do not read as drift.
PRUNE="! -name automation-by-installatron.php ! -name elementor-safe-mode.php ! -path '*.duckversions*' ! -name .DS_Store"

ssh "$SSH_HOST" "cd '$REMOTE_ROOT/wp-content' && find $PATHS -type f $PRUNE -exec md5sum {} +" \
  | LC_ALL=C sort -k2 > "$tmp/remote"

( cd "$LOCAL_ROOT/wp-content" && eval "find $PATHS -type f $PRUNE -exec md5 -r {} +" ) \
  | awk '{print $1"  "$2}' | LC_ALL=C sort -k2 > "$tmp/local"

r=$(wc -l < "$tmp/remote" | tr -d ' ')
l=$(wc -l < "$tmp/local" | tr -d ' ')
echo "server: $r files"
echo "repo:   $l files"
echo

if diff -q "$tmp/remote" "$tmp/local" >/dev/null; then
  echo "IN SYNC — all $l files match byte for byte."
else
  echo "OUT OF SYNC:"
  echo
  diff <(awk '{print $2}' "$tmp/remote") <(awk '{print $2}' "$tmp/local") \
    | grep -E '^[<>]' | sed 's/^</  server only: /; s/^>/  repo only:   /' || true
  join -j2 "$tmp/remote" "$tmp/local" 2>/dev/null \
    | awk '$2 != $3 {print "  differs:     "$1}' || true
  echo
  echo "If the server is ahead, pull it:   ./bin/pull-from-staging.sh"
  echo "If the repo is ahead, deploy it:   git push  (or run the workflow)"
  exit 1
fi
