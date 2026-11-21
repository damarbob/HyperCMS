# Regression captures

A capture is a saved copy of what one request returned. This folder holds 148 of them, taken from a seeded database, together with the scripts that send the same requests again and name every answer that changed. Run it before and after a change that should leave the API and the admin answering as they did, such as a refactor of the data layer.

Nothing here asserts that an answer is right. The check tells you which answers differ from the baseline, and you decide whether a difference is the change you meant to make.

## What is in the folder

| Path | Contents |
| :-- | :-- |
| `golden/` | 86 answers from the `api/v1` endpoints, one JSON file per request |
| `golden-admin/` | 30 admin page requests, 11 API calls made with an admin session, and 21 write flows |
| `seed.sql` | The database the captures were taken from |
| `harness.php` | Sends the API requests, and compares two folders of captures |
| `admin_harness.php` | Signs in, loads the admin pages and runs the write flows |
| `probe.php` | Sends hostile sort and find values to the list endpoints |
| `upgrade.php` | Tests `php spark hyper:upgrade` on the seed put back into the 0.1 layout |
| `stress.php` | Tests the same command on a large database of awkward data |
| `measure.php` | Builds models of generated entries and times a search and a sort on a model field, with the PHP peak memory of each |
| `db.php`, `lib.php` | Restore and drop the database |
| `watched.php`, `env.ps1` | The tables the write flows watch, and the helper that points `.env` at another database |
| `seed.php` | The script that filled the database before it was dumped |
| `check.ps1`, `down.ps1` | Run the whole check, and clean up afterwards |

An API capture stores the request, the status code and the JSON body. An admin page capture stores the status, the redirect target, the page title, and the size and SHA-1 of the HTML with its CSRF tokens masked. A write flow capture also lists the rows the request added, changed or removed in `hyper_models`, `hyper_model_revisions`, `hyper_entries`, `hyper_entry_revisions`, `hyper_counters`, `sd_entry_data` and `settings`.

The seed has three accounts, so the captures cover who gets in as well as what comes back. `capture_admin` is a superadmin and makes most of the requests. `capture_dev` is a developer, and `capture_user` is in the `user` group, which is outside the staff groups. Files that start with `anon_` were sent with no login at all. Product is the one model marked public, and Restricted is open to developers only.

## Before you run it

The two `.ps1` scripts are PowerShell and were written on Windows. You also need PHP with the `pdo_mysql` extension and a MySQL or MariaDB server that is already running.

The database is the one named by `database_default_database` in your `.env`, because that is the one the app opens. **Every run drops that database, restores it from `seed.sql`, and runs `php spark hyper:upgrade --yes` on it, because the seed is in the old layout and the app reads the `hyper_` tables.** The scripts put a `regression_marker` table into a database they create, and they refuse to drop one that holds tables without it. Even so, set `.env` to a name you use for nothing else.

## Running the check

```powershell
powershell -File tests\regression\check.ps1 -Admin -Probes
```

Without switches only the API captures run. `-Admin` adds the admin pages and write flows, and `-Probes` adds the hostile values. The script first lints the PHP files you changed, then starts `php spark serve` on port 8099 if nothing is listening there. Each baseline ends with a count.

```text
--- golden
86 files compared, 0 differ
--- golden-admin
62 files compared, 0 differ
```

A file that differs is named on a line of its own. The answers from the run are in `captures/api` and `captures/admin`, which git ignores, so compare the named file with the one in the baseline folder to see what moved. For an admin page the full HTML of the run is in `captures/admin/bodies`.

The probes sign in as `capture_admin` first. A probe is safe when the database never complains about its text, and `EXPOSED` means the text reached SQL. `REFUSED` means the request was turned away before any query ran, so that probe tested nothing.

`-Upgrade` tests the upgrade command. The seed is in today's layout, so the script first undoes what an upgrade does. It rewrites the entry fields into the old list format, drops the current version columns and the newer indexes, and records the table migration under its old name. Then it runs `php spark hyper:upgrade` and checks that every entry, model and version came back as the seed had it. Each check prints `ok` or `FAIL`.

```powershell
powershell -File tests\regression\check.ps1 -Upgrade
```

That database is a reconstruction. It was not made by a real 0.1 install, so try the command on a copy of a live database before trusting it there.

`-Stress` tests the same command on a bigger reconstruction, and it takes about four minutes. The script replaces the seed's entries with 12000 entries and 36000 versions, built from assumptions about what a real 0.1 database could hold. Some bodies are 1 to 3 MB, as with pictures pasted into the text. Some text has emoji, some lists repeat a field id or have an odd shape, and some entries are trashed. The upgrade runs with PHP's 128 MB memory limit, the one a Docker container has, and the script compares every version and every link with what it generated. Use `--entries=2000` on `stress.php` for a quicker run.

Two checkouts can run side by side. `-Port 8101` moves the web server, `-Database hyper_lane_a` rewrites the first `database_default_database` line of that checkout's `.env` before the run, and `-Watch other.php` swaps the list of tables the write flows watch. Give `down.ps1` the same `-Port` and `-Database` to clean up.

When you're done, stop the web server and drop the database.

```powershell
powershell -File tests\regression\down.ps1
```

Runs leave Debug Toolbar and session files behind in `writable/`.

## Measuring a saved list

A search, or a sort on a model field, reads every entry of the model once and keeps the ordered ids in the cache, so its cost grows with the size of the model. `measure.php` builds models of generated entries in a database that holds the seed and has been upgraded, and times the requests on them.

```powershell
php tests\regression\measure.php build . hyper_measure --entries=10000 --large=5
php tests\regression\measure.php run . hyper_measure --out=figures.json
```

`--large=5` gives 5 percent of the entries a body of 1 to 3 MB and the rest a body of about 1 KB. A model is built once and reused, because a model of 50,000 entries holds about 5 GB, and the build turns the server's binary log off for its own sessions so that this is not written twice. Each request runs in a PHP process of its own with a 128 MB limit, once with an empty cache (the list is built) and once with the list cached, on the first page and on a page from the middle. `drop` removes a model's HyperCMS rows, and the StarDust rows go when the database is dropped.

## When the baseline should change

A change that is meant to alter an answer needs a new baseline. Run the check, read the differences until each one is explained, then record.

```powershell
powershell -File tests\regression\check.ps1 -Admin -Record
```

Commit the rewritten files with the change that caused them, so the diff of the baseline shows a reviewer what moved.

## Limits

The admin page captures compare a hash of the HTML, and the HTML depends on `.env`. The baseline was recorded with `CI_ENVIRONMENT = development` and `app_baseURL = 'http://localhost:8080/'`, on PHP 8.4 and MySQL 8.0. A different base URL, app name, locale or set of active modules changes those hashes before you touch any code. If that is your setup, record a baseline from an unchanged checkout first and compare against that.

Two things are masked on purpose. The dashboard shows a quote fetched from an external site, and a response with status 500 keeps only its error type and message, since a development error page prints file paths and line numbers.

`seed.sql` was dumped after `seed.php` ran on an empty, migrated database. If a migration changes the tables the captures read, the dump has to be rebuilt by hand in the same way.
