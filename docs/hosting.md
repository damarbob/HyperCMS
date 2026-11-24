# Running HyperCMS on shared hosting

This guide is for someone who has a hosting account with a file manager, a database and a control panel, and no shell. You upload the release zip, open the site in a browser, and an install page does the rest.

## What your host must offer

| Needed | Why | How to check |
| :-- | :-- | :-- |
| PHP 8.1 or newer, with the extensions `intl`, `mbstring`, `json`, `mysqli` and `pdo_mysql` | The framework and the data engine use them | The PHP section of the control panel, usually called "Select PHP version" or "PHP extensions". The install page also lists what is missing |
| MySQL 8.0.13 or newer, or MariaDB 10.11 or newer | The data engine (StarDust) refuses anything older | In phpMyAdmin the server version is on the front page. Or run `SELECT VERSION();` in its SQL tab, which answers `10.11.6-MariaDB` or `8.0.36` |
| One database and one database user | HyperCMS keeps all its data there | The "MySQL databases" section of the control panel |
| Either a cron line, or a scheduled fetch of a web address | The background work needs a call about once a minute | "Cron jobs" in the control panel. If there is none, an online cron service can fetch an address for you every minute |

A host with neither a cron line nor a scheduled fetch cannot run HyperCMS. Indexing of filterable fields and the clean up after deleting a model would never finish, and StarDust is built to refuse such a host. A host with a database older than the versions above is out of reach until StarDust lowers its floor.

## Upload the package

Download `hypercms-<version>.zip` from the release page. It already holds the PHP packages and the front end scripts, so nothing has to be installed on the host.

Unzip it on your computer and upload the folder with your file manager or FTP, or upload the zip and extract it in the file manager if your host allows that. Make sure the hidden files `public/.htaccess` and `writable/.htaccess` come along. Many file managers have a "show hidden files" switch, and FTP programs hide them by default.

The folder holds this.

| Folder | Holds |
| :-- | :-- |
| `public/` | The only folder visitors should reach. This is the web root |
| `app/`, `modules/`, `vendor/` | The code. Visitors must not reach it |
| `writable/` | Sessions, logs, cache and uploaded files. It must be writable, and visitors must not reach it |

### When you can choose the web root

Point the domain, or a subdomain, at the `public` folder, for example `/home/you/hypercms/public`. Most hosts let you do this under "Domains" or "Addon domains".

### When the web root is fixed, such as public_html

Keep everything except `public/` outside the web root, for example in `/home/you/hypercms/`. Then copy what is inside `public/` into `public_html`, including `.htaccess`. Open the copied `index.php` and change this line so it points at the real place of the application.

```php
require FCPATH . '../app/Config/Paths.php';
```

For the example above it becomes the line below.

```php
require '/home/you/hypercms/app/Config/Paths.php';
```

Nothing else moves. Uploaded files stay in `writable/uploads`, outside the web root, and are served by HyperCMS itself at `/media/{id}/{name}`.

### Folders that must be writable

`writable/` and the folders inside it (`cache`, `logs`, `session`, `uploads`) must be writable by the web server. On most shared hosts the usual permission of 755 is enough, and 775 is the next step up if the install page still complains. The project folder must also let the install page create the `.env` settings file. If it cannot, create an empty `.env` there yourself and make it writable.

## The install page

Open your site in a browser. A site that has not been installed sends every address to `/install`.

1. **Check the list at the top.** Every line should show OK. A red line names what the host lacks, and the form stays locked until it is fixed.
2. **Open `writable/install.key` with your file manager and paste what it holds.** The page made that file the first time it was opened. Only someone who can read the site's files can do this, which stops a stranger who finds the address first from setting up your site.
3. **Fill in the database.** Use the host, name, user and password from the control panel. Leave the table prefix empty unless the database is shared with other software, see below. The page tries the connection, checks the server version and refuses a database that already holds a finished HyperCMS install.
4. **Check the site address.** It is guessed from the address you opened. Use `https://` if the site has a certificate.
5. **Make the first account.** It becomes the superadmin, the one role that can do everything. The password must pass the same rules as every other account.
6. **Say how the background work will run**, and press "Save and continue". The page writes `.env` and shows a last page with an "Install now" button.

"Install now" makes the tables, prepares the data engine and creates your account. It takes a few seconds on a normal host. If a step fails the page says which one and why, and you can press "Try again". The steps that finished are skipped.

When the install ends, the page shows what to set up for the background work, with your own address or path filled in, and then it is gone. `/install` answers 404 from then on, even if the database is down.

If you leave the page unused, it stays open, but it can never finish without the key file. Install soon after uploading anyway.

## Upgrading a site from an earlier version

Take a backup of the database first, one you know how to restore. The upgrade only adds tables and leaves the old ones as they are, but a backup is the one thing that undoes a mistake.

Upload the new package over the old files and keep your `.env`. The first visit to the site, at any address, sends you to the upgrade page, because the data is behind the code. The token API answers 503 with a "come back later" text until the upgrade is done.

1. **Open `writable/upgrade.key` with your file manager and paste what it holds.** Like the install page, the upgrade page is only for someone who can read the site's files.
2. **Read what the upgrade found.** It lists the entries of each model, entries it will leave behind because they never had a saved version, and every field it cannot convert, which becomes a text field with its stored values kept. A database server that is too old is shown here too, and the start button stays locked.
3. **Press "Start the upgrade" and keep the page open.** The work runs in requests of a few seconds each, and the page asks for the next one by itself. If the browser closes, open the page again and it carries on, since every step skips what is done.

If a step fails, the page says why and offers "Try again". When it ends, it shows what was copied and converted and a link to sign in, and the upgrade page answers 404 from then on.

The page checks the result by counting, and does not compare every entry with its source. A site with a shell can run `php spark hyper:upgrade --dry-run` first for the full report of what would change, and `php spark hyper:upgrade` does the same work with that comparison.

## The background work

HyperCMS hands some work to StarDust to do after you save. It builds the index of a field you flag as filterable, finishes a model you deleted, and completes a save that a crash cut short. None of it runs by itself on a host, so something has to call it about once a minute. Use one of the two ways below, never both.

### With a cron line

Add this in the cron section of your control panel, to run every minute.

```cron
* * * * * /usr/local/bin/php /home/you/hypercms/spark hyper:tick --budget 50 >> /home/you/hypercms-tick.log 2>&1
```

The path to PHP differs between hosts, and your host's help page for cron jobs names it. The `--budget 50` keeps one run inside a minute, and a smaller `max_execution_time` on the host is respected automatically. A run that starts while another is still going does nothing.

### With a scheduled web address

If the host has no cron but an online cron service can fetch an address for you, ask it to fetch this every minute.

```text
https://your-site.example/hyper/tick?key=THE-SECRET
```

The secret is the setting `hyperEngine.tickSecret` in `.env`. The install page makes one when you choose this way and shows the full address once. To set one by hand, put a random text of at least 32 characters in `.env`.

```ini
hyperEngine.tickSecret = "paste-a-long-random-text-here"
```

Until a secret of at least 32 characters is set, the address answers 404, as if it did not exist. A wrong key gets 403. A key in the address is written to the access log of your host and of any service in between, so a service that can send headers should send the key as `X-Tick-Key` instead. If you suspect the key has leaked, put a new one in `.env` and update the service. The answer is plain text, such as `Tick finished after 0.0 of 50 seconds in 1 round(s), stopped because idle.`

### Check that it works

Make a model, add a text field, tick "filterable" and save. The model page shows the field as being indexed. Within a minute or two of the first call it changes to ready. If it never does, open the tick address by hand and read the answer, or look at the files in `writable/logs`.

## Sharing a database, the table prefix

A host that gives you one database usually shares it with other software, and a table called `users` or `settings` may already be there. Set a table prefix, such as `blog_`, and every table of the site carries it, including the account tables and the data engine's tables, so the site has `blog_users`, `blog_hyper_models` and `blog_sd_entry_data`.

The install page has a field for it. By hand, it is the setting `database.default.DBPrefix` in `.env`. Choose it before installing. Changing it later does not rename any table, so a site would simply look for tables that are not there. An existing site keeps the tables it has, with no prefix.

## When something goes wrong

| What you see | What to do |
| :-- | :-- |
| A blank page or a 500 error | Open the newest file in `writable/logs`. It names the cause |
| The install page lists a red line | Fix that line. For a missing extension use the PHP section of the control panel. For a folder, change its permission |
| "The key does not match the file" | Open `writable/install.key` again and copy everything on its first line |
| "The database cannot be reached" | Check the host name, which is often `localhost` but sometimes a name the host gives you, and the user and password |
| "This database server is too old" | See the table at the top. Use a database that meets it, or another host |
| You need to change a setting after the install | Edit `.env`. Reload the site afterwards |

To see the real error on screen while you look for a problem, set `CI_ENVIRONMENT = "development"` in `.env`, and set it back to `"production"` afterwards. A site left in development shows internal details to visitors.
