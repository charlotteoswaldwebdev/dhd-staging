# Cloudways Git deployment — setup and safety test

## Context

This repo tracks **only our own code** (see the README). WordPress core,
third-party plugins (~356 MB), parent themes, and uploads (~248 MB) live on
the server and are not in git.

That makes one question critical before the first deploy:

> Does Cloudways' Git deployment **delete** files in the deployment path
> that are absent from the repo?

- If it **overlays** (only writes files present in the repo) → deploying to
  `public_html` is safe, and the layout in this repo is correct.
- If it **mirrors** (removes anything not in the repo) → it would destroy
  the untracked plugins and uploads.

Do not skip the test in step 3.

---

## Step 1 — Take a backup first

In the Cloudways panel: **Server → Backup and Restore → Take Backup Now**,
and confirm it completes before going further. UpdraftPlus is also installed
(`wp-content/updraft`), but the Cloudways server backup is the safety net
that covers the whole filesystem.

## Step 2 — Connect the repo

1. Cloudways panel → **Applications** → the DHD staging app
2. **Deployment via Git** in the left sidebar
3. First time only: Cloudways shows **its own SSH public key**. Copy it, then
   in GitHub go to `charlotteoswaldwebdev/dhd-staging` →
   **Settings → Deploy keys → Add deploy key**, paste it, and save.
   - Leave **Allow write access** unchecked. Deployment only needs read.
4. Back in Cloudways, enter the **Git Remote Address**:
   ```
   git@github.com:charlotteoswaldwebdev/dhd-staging.git
   ```
   then **Authenticate**.
5. **Branch:** `main`
6. **Deployment Path:** leave blank (deploys to `public_html`)

Exact field labels may differ slightly by panel version.

## Step 3 — Run the safety test

Before clicking deploy, plant marker files on the server:

```bash
./bin/deploy-safety-check.sh arm
```

Now trigger the deployment in Cloudways. Then:

```bash
./bin/deploy-safety-check.sh check
```

It reports, for each marker and for real untracked files (WooCommerce,
`hello-biz`, `wp-config.php`, the host-generated mu-plugins), whether it
survived — and prints the `plugins/` and `uploads/` directory sizes, which
should still be ~356 MB and ~248 MB.

Then tidy up:

```bash
./bin/deploy-safety-check.sh clean
```

### If the verdict is "nothing was deleted"

Done. Deploying to `public_html` is safe and this repo's layout is correct.

### If anything was deleted

**Stop using Cloudways Git deployment** and restore from the step 1 backup.

One repo per application with a single deployment path means no narrow path
covers all four tracked locations (`mu-plugins/`, two child themes, and one
plugin). The clean alternative is to deploy over SSH instead, reusing the
same path list this repo already uses — a GitHub Actions workflow on push to
`main` that rsyncs those four paths, which is the mirror image of
`bin/pull-from-staging.sh`. That also gives deploy-on-push, which Cloudways'
button does not.

---

## Deploy on push — the current method

`.github/workflows/deploy.yml` calls the **Cloudways API** on every push to
`main` that touches `wp-content/`, which triggers Cloudways' own Git
deployment. Cloudways then pulls from GitHub and deploys.

### Why not rsync over SSH

The first attempt rsynced from the GitHub runner straight to the server. It
failed with `connect to host 64.176.180.39 port 22: Connection timed out` —
a network-level block, not an auth failure.

Cloudways restricts SSH/SFTP to **whitelisted IP addresses**. GitHub-hosted
runners come from a large, constantly changing pool published at
`api.github.com/meta`, so whitelisting them would mean allowing SSH from
every GitHub Actions runner in the world. That defeats the whitelist, so the
approach was abandoned rather than weakened.

The API call is outbound HTTPS from the runner to `api.cloudways.com`, so the
SSH whitelist is irrelevant to it.

### One-time setup

1. **Keep Cloudways' Git deployment configured.** Application → Deployment
   via Git, with repo, branch `main`, and deployment path blank. The workflow
   presses that button over the API; it does not replace the configuration.
2. **Create an API Access Token.** Cloudways Platform → grid icon (bottom of
   the left nav) → **API Integration** → **Create Access Token**. Name it
   `GitHub Actions deploy`, and choose **Limited Access** with only the Git
   operation. The token is shown **once** — copy it immediately.
3. **Add three GitHub secrets** (repo → Settings → Secrets and variables →
   Actions):

   | Secret | Value |
   |---|---|
   | `CLOUDWAYS_ACCESS_TOKEN` | the token from step 2 |
   | `CLOUDWAYS_SERVER_ID` | `1657598` |
   | `CLOUDWAYS_APP_ID` | `6759210` |

   The IDs come from Cloudways' URL convention — the server hostname is
   `1657598.cloudwaysapps.com` and the app's temporary URL is
   `wordpress-1657598-6759210.cloudwaysapps.com`. Confirm them against the
   panel URL when you open the server and the application.

### API details

Authentication uses the Access Token **directly as a Bearer token**. The
`/oauth/access_token` exchange seen in older examples belongs to the legacy
API Key, which Cloudways is retiring.

```
POST https://api.cloudways.com/api/v1/git/pull
Authorization: Bearer <access token>
Content-Type: application/x-www-form-urlencoded

server_id, app_id, git_url, branch_name   (deploy_path optional)
```

Tokens expire. When deploys start failing with HTTP 401 or 403, the token has
lapsed — create a new one and update the secret.

### Verifying a deploy landed

The API call only *requests* a deployment, so a green workflow run means
"accepted", not "finished". To confirm the server actually matches the repo:

```bash
./bin/verify-sync.sh
```

Or read Application → Deployment via Git → logs in the Cloudways panel.

### The webroot side effect

Cloudways copies the **whole repo root** into the deployment path, so
`README.md`, `bin/` and `docs/` land in `public_html`. On staging they are
behind basic auth, but they name the server, the master username and the app
ID, so deny them explicitly. Add to `public_html/.htaccess`, above the
WordPress block:

```apache
# Keep repo metadata out of reach
<FilesMatch "^(README\.md|\.gitignore)$">
  Require all denied
</FilesMatch>
RedirectMatch 404 ^/(bin|docs|\.github|\.git)(/|$)
```

`.htaccess` is not tracked in this repo, so a deploy will not overwrite it.

---

## Note: the Cloudways panel button is manual

Cloudways' own Git deployment is pull-based and triggered by a button in the
panel — pushing to `main` does not trigger it. That is the reason for the
GitHub Actions workflow above. Keep one method, not both.

## Keeping the repo in step with the server

If someone edits code directly on staging (via the file manager or WP's
editor), pull it back down rather than letting it drift:

```bash
DRY_RUN=1 ./bin/pull-from-staging.sh   # see what changed on the server
./bin/pull-from-staging.sh             # bring it into the repo
git status
```
