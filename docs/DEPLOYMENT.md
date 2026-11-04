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

## Deploy on push (GitHub Actions) — the current method

`.github/workflows/deploy.yml` rsyncs the four tracked paths to staging on
every push to `main` that touches `wp-content/`. It replaces clicking Deploy
in the Cloudways panel, and unlike that button it does **not** copy repo meta
files (`README.md`, `bin/`, `docs/`, `.github/`) into the webroot.

### One-time setup

1. **Add the deploy key to Cloudways.** Server Management → SSH Public Keys →
   add the contents of `~/.ssh/dhd-deploy.pub`, labelled `github-actions`.
2. **Add the private key to GitHub.** Repo → Settings → Secrets and variables
   → Actions → New repository secret:
   - Name: `DEPLOY_SSH_KEY`
   - Value: the whole of `~/.ssh/dhd-deploy`, including the
     `-----BEGIN`/`-----END` lines.
3. **Disable Cloudways' Git deployment** so the two cannot fight, and remove
   what it already left in the webroot:
   ```bash
   ssh dhd-cloudways 'cd /home/master/applications/rdxcacbyuw/public_html && rm -rf bin docs README.md'
   ```

### Why a separate key

`~/.ssh/dhd-deploy` is a dedicated, passphrase-less ed25519 keypair used only
by CI. Charlotte's personal key is not in GitHub Secrets, so revoking CI
access is a matter of deleting one key in Cloudways.

### Safety properties

- The server's host key is **pinned** in the workflow. No
  `StrictHostKeyChecking=no` anywhere — a changed host key fails the deploy.
- `--delete` is scoped to the four tracked paths only.
- Files in the `--exclude` list are **protected from deletion** on the server,
  which is what preserves `automation-by-installatron.php` and
  `elementor-safe-mode.php`. (rsync only deletes excluded files when given
  `--delete-excluded`, which this workflow never passes.)
- A `concurrency` group prevents two deploys running at once.

### Dry run

Actions tab → **Deploy to staging** → **Run workflow** → tick
*Dry run*. It reports what would change without writing anything.

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
