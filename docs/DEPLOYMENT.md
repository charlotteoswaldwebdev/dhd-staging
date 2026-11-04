# Deploying DHD staging

## Context

This repo tracks **only our own code** (see the README). WordPress core,
third-party plugins (~356 MB), parent themes, and uploads (~248 MB) live on
the server and are not in git.

Deployment is **manual**: push to GitHub, then pull on Cloudways.

---

## The routine

### 1. Before you start work — check for drift

If anyone edited code on staging through WP's editor or the file manager,
the repo is behind and deploying would silently revert their change.

```bash
./bin/verify-sync.sh
```

It MD5-compares every tracked file against the server. If the server is
ahead, bring the changes into the repo first:

```bash
./bin/pull-from-staging.sh
git status
```

### 2. Push your changes

```bash
git push
```

### 3. Pull them on Cloudways

Cloudways panel → **Applications** → the DHD staging app → **Deployment via
Git** → **Pull**.

### 4. Confirm it landed

```bash
./bin/verify-sync.sh
```

Expect `IN SYNC — all N files match byte for byte`.

---

## One-time setup (already done)

1. **Backup.** Server → Backup and Restore → Take Backup Now.
2. **Connect the repo.** Application → Deployment via Git. Cloudways shows
   its own SSH public key — add it to the GitHub repo under
   **Settings → Deploy keys**, leaving *Allow write access* unchecked.
   Then enter the remote address and authenticate:
   ```
   git@github.com:charlotteoswaldwebdev/dhd-staging.git
   ```
   Branch `main`, Deployment Path blank (deploys to `public_html`).
3. **Safety test.** Verified with `bin/deploy-safety-check.sh`: a deploy
   **overlays** rather than mirrors, so untracked files — the third-party
   plugins and `wp-content/uploads` — are left alone. Re-run the test if
   Cloudways ever changes how deployment works:
   ```bash
   ./bin/deploy-safety-check.sh arm     # before a deploy
   ./bin/deploy-safety-check.sh check   # after it
   ./bin/deploy-safety-check.sh clean
   ```

## Keep repo metadata out of the webroot

Cloudways copies the **whole repo root** into the deployment path, so
`README.md`, `bin/` and `docs/` end up in `public_html`. On staging they sit
behind basic auth, but they name the server, the master username and the app
ID. Deny them in `public_html/.htaccess`, above the WordPress block:

```apache
# Keep repo metadata out of reach
<FilesMatch "^(README\.md|\.gitignore)$">
  Require all denied
</FilesMatch>
RedirectMatch 404 ^/(bin|docs|\.git)(/|$)
```

`.htaccess` is not tracked here, so a deploy will not overwrite it.

---

## Why there is no deploy-on-push

Worth recording so nobody rebuilds it:

- **rsync from GitHub Actions is impossible.** Cloudways restricts SSH/SFTP
  to whitelisted IPs. GitHub-hosted runners come from a large, shifting pool,
  so whitelisting them would mean allowing SSH from every GitHub Actions
  runner anywhere. Attempting it gave
  `connect to host 64.176.180.39 port 22: Connection timed out`.
- **Cloudways' deployment API needs account ownership.** Triggering
  `POST /api/v1/git/pull` requires an API Access Token, and API Integration is
  only available to the primary Cloudways account owner. We have master
  server credentials, not account ownership.

If account ownership ever changes, the API route becomes viable again.

---

## What git does not cover

The active child theme is 113 lines in total. Nearly all of this site's
design — pages, templates, headers, global colours and fonts, Elementor kit
settings — lives in the **database**, not in files. Git deployment moves PHP,
CSS and JS only. Elementor work still has to be done in the admin and
migrated separately.
