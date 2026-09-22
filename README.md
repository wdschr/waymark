# Waymark

A self-hosted bookmark manager built for cheap shared hosting (specifically
Heart Internet's shared hosting, which runs its own **eXtend control
panel** - not cPanel). It's inspired by Linkwarden but is a from-scratch
rewrite: Linkwarden's stack (Next.js, Postgres, Redis, headless-browser
archiving) simply cannot run on that kind of account, so Waymark is built
entirely on plain PHP and MySQL/MariaDB instead.

Nothing in the application code is cPanel- or eXtend-specific - it's plain
PHP/PDO/MySQL and would deploy the same way on any shared host that gives
you a MySQL database, file upload, and cron. Only the deployment steps
below are written specifically for eXtend's screens.

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

## Deploying to Heart Internet (eXtend Control Panel)

eXtend groups the tools you need under **Web Tools** on the account
dashboard. Screens and exact wording can shift between Heart Internet
plans/updates - if something below doesn't match what you see, the
"Assumptions to verify" section at the end lists what to double-check.

### 1. Create the database

1. Log into your **eXtend Control Panel** and scroll to **Web Tools**.
2. Click **MySQL Databases**.
3. Enter a username for the database (6-9 characters, and it can't contain
   "test", "root", "mysql", or "alive"). Click **Generate Password** (or set
   your own), then click **Create**.

Unlike cPanel, eXtend creates the database and its user together as a
single step - **the database is given the same name as the username you
just entered**. There's no separate "create database" / "create user" /
"add user to database" sequence.

4. On the MySQL Databases page, note the exact **hostname** shown for your
   new database - Heart Internet does not always use `localhost` for this;
   use whatever the panel actually shows you.

### 2. Upload the files

Upload the contents of this repo to your hosting account via eXtend's
**File Manager** or FTP. Where exactly depends on how you want it exposed:

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
and you don't want to paste it in): open **phpMyAdmin** via
`MySQL Databases > Manage` in eXtend, import `schema.sql` yourself under
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

### 5. Set up the dead-link checker as a scheduled task

The dead-link checker can't run as a background process (shared hosting
doesn't allow that), so it runs in small batches on a schedule instead.

1. In eXtend, go to **Web Tools > Scheduled Tasks**.
2. Before filling this in, open **Website Help & Diagnostics > Paths and
   Versions** in a separate tab and note the exact PHP CLI interpreter path
   for your account - don't guess it, it varies by PHP version.
3. Back on Scheduled Tasks, enter the command in the
   `/[INTERPRETER] /[FILE] [ARGUMENT]` form eXtend expects, e.g.:

   ```
   /usr/bin/php /home/sites/example.com/public_html/waymark/cron/check_links.php
   ```

   (adjust both paths to match what "Paths and Versions" and your upload
   location actually show).
4. Click **Test Command** to confirm it runs cleanly before saving.
5. Set the schedule to run every 5-15 minutes and click **Update**. eXtend
   only allows **up to three scheduled tasks per account**, so this uses one
   of them.

This is resumable and safe to run as often as every 5 minutes: each run
checks up to 50 links (oldest-checked first) and stops early if it's
approaching the execution-time budget, so it never risks a fatal timeout
and never re-checks the same links before working through the backlog.

**If your plan doesn't support running a PHP file directly this way**,
`cron/check_links.php` also accepts being triggered over HTTP instead, once
`cron_secret` is set in `config.php`. Enter this as the command on the same
Scheduled Tasks screen, using `curl`'s path (from "Paths and Versions") as
the interpreter and the URL as the argument:

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
  `mbstring` extensions enabled. eXtend lets you pick a PHP version under
  **Web Tools > Switch PHP Version**; these extensions should be enabled by
  default on any version you pick, but it's worth a quick check. If
  switching there doesn't seem to take effect, Heart Internet's own
  documentation says to add a line like `AddHandler application/x-httpd-php82 .php`
  (adjust the version number) to `.htaccess` as a fallback - this repo's
  `.htaccess` doesn't include that line since the exact version number is
  account-specific, so add it yourself if needed.
- **No Composer.** The app has zero third-party dependencies, so this
  shouldn't matter, but if you do confirm Composer is available on your
  account, that's worth knowing for future work.
- **MariaDB 10.0.5+ / MySQL 5.6+** using the InnoDB storage engine, which is
  required for `FULLTEXT` indexes on InnoDB tables (`schema.sql` creates
  everything as InnoDB). Any current Heart Internet MySQL version should be
  new enough, but it's worth confirming if you're on an older or legacy
  plan.
- **Apache with `.htaccess` support (`AllowOverride`)**, which is what
  Heart Internet's shared platform runs on. If your `.htaccess` files
  appear to be ignored, the `Require all denied` rules protecting
  `config.php` and `includes/` won't take effect - check with support if
  in doubt.
- **PHP sessions can write to their default save path.** The app uses
  native PHP sessions with default settings; if your account restricts
  `session.save_path`, you may need to set one explicitly in `config.php`'s
  bootstrap or via a custom `php.ini`.
- **Running a PHP file directly from Scheduled Tasks is supported** on
  eXtend (confirmed in Heart Internet's own documentation, which gives the
  exact `/[INTERPRETER] /[FILE] [ARGUMENT]` form used in step 5 above), so
  the PHP CLI path should work rather than needing the HTTP/curl fallback -
  but confirm the exact interpreter path via "Paths and Versions" rather
  than assuming `/usr/bin/php` is correct for your account.
- **eXtend allows up to three scheduled tasks per account.** Not a problem
  for this app (it only needs one), but worth knowing if you plan to add
  more automation later.
- **SSH access is a separate, formal request** on Heart Internet shared
  hosting (an application form with ID verification), not enabled by
  default - and this app doesn't need it anywhere in this workflow.
  Everything above is driven through eXtend's web UI (phpMyAdmin via MySQL
  Databases > Manage, File Manager, Scheduled Tasks).

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
