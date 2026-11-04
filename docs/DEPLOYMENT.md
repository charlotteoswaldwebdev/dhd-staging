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

## Note: deployment is manual

Cloudways' Git deployment is pull-based and triggered by a button in the
panel. Pushing to `main` does **not** deploy on its own unless you wire up
their API or a webhook.

## Keeping the repo in step with the server

If someone edits code directly on staging (via the file manager or WP's
editor), pull it back down rather than letting it drift:

```bash
DRY_RUN=1 ./bin/pull-from-staging.sh   # see what changed on the server
./bin/pull-from-staging.sh             # bring it into the repo
git status
```
