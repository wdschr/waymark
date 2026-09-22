# Waymark

A self-hosted bookmark manager built for cheap shared hosting - the kind of
account that gives you a MySQL database, file upload, and cron, but no
root, no Docker, and no persistent background processes. It's inspired by
Linkwarden but is a from-scratch rewrite: Linkwarden's stack (Next.js,
Postgres, Redis, headless-browser archiving) simply cannot run on that
kind of account, so Waymark is built entirely on plain PHP and
MySQL/MariaDB instead.

Nothing in the application code is tied to any particular hosting
provider or control panel - it's plain PHP/PDO/MySQL and deploys the same
way on any shared host that gives you those three things. The deployment
steps below are written in generic, cPanel-style terms (MySQL Databases,
phpMyAdmin, File Manager, Cron Jobs); the exact screen names and layout
will vary a bit by host, but the underlying steps are the same everywhere.

## Architecture

- **Plain PHP, no framework, zero third-party dependencies.** Composer is
  not assumed to be available on the target account (see "Assumptions" below)
  - if you confirm it is, that opens the door to a router/ORM in a future
    revision, but v1 deliberately doesn't need it.
- **Per-page scripts, not a front controller.** Each page is its own `.php`
  file (`index.php`, `login.php`, `link_save.php`, ...) rather than a single
  `index.php` that dispatches by path. A front controller needs mod_rewrite
  configured correctly, and deployment here is "copy files, done" - it
  should not depend on Apache rewrite rules being present or correctly
  interpreted on every shared-hosting account. Plain scripts work the moment
  the files exist, on any Apache/LiteSpeed config.
- **PDO with prepared statements** for every query, never string-concatenated
  SQL.
- **MySQL FULLTEXT search**, no external search engine. A denormalized
  `tags_cache` column on `links` (comma-separated tag names) is kept in sync
  whenever tags change, because MySQL FULLTEXT indexes can't span a join -
  this lets one `FULLTEXT(title, description, url, tags_cache)` index cover
  tag search too.
- **No background workers.** The dead-link checker is a cron-triggered
  script that processes a small, time-boxed batch per run and picks up where
  it left off next time (see below) - there is no daemon, queue, or
  long-running process anywhere in this app.
- Config lives in `config.php` (gitignored). Copy `config.sample.php` to
  `config.php` and fill in your real values.

## Deploying to shared hosting

Most budget shared-hosting control panels (cPanel and its many lookalikes)
group these tools under similar names - **MySQL Databases**, **File
Manager**, **Cron Jobs**. Exact wording and screen layout varies by host;
if something below doesn't quite match what you see, the "Assumptions to
verify" section at the end lists what to double-check with your host's own
docs or support.

### 1. Create the database

1. Log into your hosting control panel and find **MySQL Databases** (it
   may be under a "Databases" section).
2. Create a new database, then create a database user with a strong
   password, then add that user to the database with all privileges.
   Some panels combine these into one step and automatically give the
   database the same name as the username you entered - if so, just
   follow the single form it gives you.
3. Note the exact **hostname** shown for your new database - it isn't
   always `localhost`; use whatever the panel actually shows you.

### 2. Upload the files

Upload the contents of this repo to your hosting account via your host's
**File Manager** or FTP/SFTP. Where exactly depends on how you want it
exposed:

- To serve it at your domain root, upload everything into `public_html/`.
- To serve it at a subpath (e.g. `example.com/waymark/`), upload
  everything into `public_html/waymark/`.

Either way, upload the whole tree as-is - `.htaccess` files are included to
block direct access to `config.php`, `schema.sql`, and the `includes/` and
`partials/` directories, so there's no separate "keep this out of the
webroot" step to do manually.

**Do not upload `config.php`** (it doesn't exist yet at this point) or your
local `.git` directory.

### 3. Run the setup wizard

Visit `install/` on your domain (e.g. `https://example.com/waymark/install/`)
in a browser. This is a small web-based installer, in the same spirit as
Roundcube's - it walks you through:

1. **Requirements** - checks your PHP version and required extensions.
2. **Database** - enter the host/name/user/password from step 1; it tests
   the connection before letting you continue.
3. **Schema** - imports `schema.sql` for you (safe to re-run - it skips
   automatically if the tables already exist).
4. **Configuration** - generates a random session secret and cron secret
   for you (nothing to type or remember), and writes `config.php`. If the
   app directory isn't writable, it shows you the file's contents to save
   manually via File Manager instead of failing outright.
5. **Your account** - creates your first user and logs you straight in.

**When it finishes, delete the `install/` directory** (or at least
`install/index.php`) via File Manager or FTP. The wizard refuses to run
again once `config.php` exists (it'll show a warning and require an
explicit override to proceed), but leaving a working installer reachable on
a live site is still a bad idea - it's the single biggest thing to remember
from this whole guide.

**If you'd rather do it by hand** (or the wizard can't write `config.php`
and you don't want to paste it in): open **phpMyAdmin** from your
control panel's database tools, import `schema.sql` yourself under
the **Import** tab, then copy `config.sample.php` to `config.php` and fill
in `db.host`/`db.name`/`db.user`/`db.pass` (from step 1), a `session_secret`
and `cron_secret` (32+ random bytes as hex each - `php -r "echo bin2hex(random_bytes(32));"`
if you have CLI access), and `base_url`. Then visit `register.php` to
create your account.

### 4. Try it

- Save a link from the dashboard (`index.php`) - watch the title,
  description and preview image populate automatically.
- Create a collection, enable sharing on it, and open the share link in a
  private/incognito window to confirm it's readable without logging in.
- Visit `bookmarklet.php`, drag the button to your bookmarks bar, and use it
  on a few pages.
- Export your links (`export.php`) and re-import the file (`import.php`) to
  confirm the round-trip works.

### 5. Set up the dead-link checker as a cron job

The dead-link checker can't run as a background process (shared hosting
doesn't allow that), so it runs in small batches on a schedule instead.

1. In your control panel, find **Cron Jobs** (sometimes called "Scheduled
   Tasks").
2. Before filling this in, find your account's exact PHP CLI interpreter
   path - most panels show this under something like "Select PHP
   Version" or a "Paths" page; don't guess it, it varies by PHP version
   and by host.
3. Enter the command in whatever form your panel expects, e.g.:

   ```
   /usr/bin/php /home/yourusername/public_html/waymark/cron/check_links.php
   ```

   (adjust both paths to match your account's real interpreter path and
   upload location).
4. If your panel offers a "test command" option, use it to confirm the
   command runs cleanly before saving.
5. Set the schedule to run every 5-15 minutes and save. Some budget hosts
   cap how many cron jobs an account can have (three is a common limit) -
   this app only needs the one.

This is resumable and safe to run as often as every 5 minutes: each run
checks up to 50 links (oldest-checked first) and stops early if it's
approaching the execution-time budget, so it never risks a fatal timeout
and never re-checks the same links before working through the backlog.

**If your plan doesn't support running a PHP file directly this way**,
`cron/check_links.php` also accepts being triggered over HTTP instead, once
`cron_secret` is set in `config.php`. Enter this as the command on the same
cron screen, using `curl`'s path as the interpreter and the URL as the
argument:

```
/usr/bin/curl -s "https://example.com/waymark/cron/check_links.php?token=YOUR_CRON_SECRET"
```

## Security notes

- Passwords are hashed with `password_hash()` / verified with
  `password_verify()`.
- Every state-changing form/endpoint checks a session-bound CSRF token,
  **except** `quickadd.php` (the bookmarklet's target). That endpoint is
  deliberately authenticated by a personal `bookmarklet_token` instead,
  because the bookmarklet works by submitting a genuine cross-site form POST
  from whatever page you're on - a session CSRF token can't survive that
  trip by design. Keep your bookmarklet link private, and regenerate the
  token from `bookmarklet.php` if you think it's leaked.
- All output is escaped with `htmlspecialchars()` via the `h()` helper.
- The metadata fetcher and dead-link checker share one SSRF-guarded HTTP
  client (`includes/http_fetch.php`):
  - only `http://` and `https://` are allowed;
  - the hostname is DNS-resolved and every resulting IP is checked against
    private, loopback, link-local and other reserved ranges (both IPv4 and
    IPv6) before any connection is attempted;
  - the request is then pinned to that already-checked IP address
    (`CURLOPT_RESOLVE`), so a DNS answer that changes between the check and
    the connection can't be used to bypass the filter;
  - redirects are followed manually, one at a time, up to 3 hops, with the
    same host/IP validation repeated on every hop;
  - connect/total timeouts are kept under 5 seconds and the response body is
    capped at 1MB, so a slow or huge response can't hold up the request.
- If metadata fetching fails or times out for any reason, the link is still
  saved with just its URL - you can fill in the title/description manually
  or hit "Refresh metadata" later.
- `install/index.php` (the setup wizard) refuses to run once `config.php`
  already exists, unless explicitly overridden - but it's still a working
  installer if left in place, so delete it once setup is done (the wizard's
  own final screen repeats this).

## Assumptions to verify before deploying

These were assumed about the target hosting account. Confirm them (or
adjust the code) before relying on this in production:

- **PHP 8.1 or 8.2** with the `pdo_mysql`, `curl`, `dom`/`libxml`, and
  `mbstring` extensions enabled. Most control panels let you pick a PHP
  version under something like "Select PHP Version"; these extensions
  should be enabled by default on any version you pick, but it's worth a
  quick check. If switching there doesn't seem to take effect, some hosts
  need a line like `AddHandler application/x-httpd-php82 .php` (adjust
  the version number) added to `.htaccess` as a fallback - this repo's
  `.htaccess` doesn't include that line since the exact version number is
  account-specific, so add it yourself if your host needs it.
- **No Composer.** The app has zero third-party dependencies, so this
  shouldn't matter, but if you do confirm Composer is available on your
  account, that's worth knowing for future work.
- **MariaDB 10.0.5+ / MySQL 5.6+** using the InnoDB storage engine, which is
  required for `FULLTEXT` indexes on InnoDB tables (`schema.sql` creates
  everything as InnoDB). Any current shared-hosting MySQL/MariaDB version
  should be new enough, but it's worth confirming if you're on an older or
  legacy plan.
- **Apache with `.htaccess` support (`AllowOverride`)**, which is standard
  on most shared-hosting platforms. If your `.htaccess` files appear to be
  ignored, the `Require all denied` rules protecting `config.php` and
  `includes/` won't take effect - check with your host's support if in
  doubt.
- **PHP sessions can write to their default save path.** The app uses
  native PHP sessions with default settings; if your account restricts
  `session.save_path`, you may need to set one explicitly in `config.php`'s
  bootstrap or via a custom `php.ini`.
- **That running a PHP file directly from a cron job is supported on your
  host.** Most shared-hosting panels support this, but the exact command
  syntax varies - check your host's own documentation if the form shown
  in step 5 above doesn't match what your panel expects, and confirm the
  real interpreter path rather than assuming `/usr/bin/php` is correct for
  your account.
- **Some hosts cap the number of cron jobs per account** (three is a
  common limit on budget plans). Not a problem for this app (it only
  needs one), but worth knowing if you plan to add more automation later.
- **SSH access isn't guaranteed on budget shared hosting** - some hosts
  don't offer it at all, others require a separate request or a plan
  upgrade. This app doesn't need it anywhere in this workflow; everything
  above is driven through your host's web-based control panel (phpMyAdmin,
  File Manager, Cron Jobs).

## Feature scope

Implemented in v1: a browser-based setup wizard, accounts
(register/login/logout), link save with automatic metadata fetch
(SSRF-guarded), collections, freeform tags, FULLTEXT search,
Netscape-format bookmark import/export, a bookmarklet for quick-add, a
cron-driven dead-link checker, and token-based public sharing of a
collection.

**Not supported in the shared-hosting model** (and not planned - these
genuinely need infrastructure this environment doesn't have):

- Full page archiving, screenshots, or PDF capture (needs a headless
  browser).
- Real-time collaboration or websockets (needs a persistent connection).
- OAuth/SSO login providers.
- A native browser extension (the bookmarklet replaces this).
- Redis, a queue system, or any second running service.

## License

MIT - see [LICENSE](LICENSE).
