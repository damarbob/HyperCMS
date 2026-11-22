# Changelog

All notable changes to HyperCMS are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

## [0.1.0-alpha.9] - 2026-11-22

This release closes three security holes that have been open since 0.1.0-alpha.5. It needs no database changes and no upgrade steps.

If a site ran an earlier release on the public internet, search its web server access log for `2e656e76`, which is the hex of `.env`, and rotate the database password, `encryption.key` and any API key in that `.env`. Do this whatever the log shows, because the hole was open for a long time.

### Added

- **File access probes**: Added probes to `tests/regression/probe.php` for the public file route, uploads, renames, `create-file`, `bulk-action`, `delete-files`, `extract` and the history pages (c54b0eb).

### Changed

- **File manager root**: Used `fileManager.productionPath` as the root in every environment, created it when missing, and fell back to `public/` when it points at the project folder or above it. `fileManager.nonProductionPath` is no longer read (d43be11).
- **Refused file names**: Refused dot files, names that end in a dot or space, and the extensions `php`, `phtml`, `phar`, `pht`, `php3` to `php8`, `phps`, `shtml`, `cgi`, `htaccess` and `htpasswd` anywhere after the first part of a name. Files already uploaded under such names are no longer served (aa24229, cf4c7be).
- **Archives**: Checked every entry of a ZIP file before extracting it, and refused the whole archive when one entry has an unsafe name or leaves the folder (d43be11).

### Security

- **Public file route**: Served files for `/public/file-server/serve/{path}` only from the public folder. The route needs no login and used to read from the project folder too, so the hex of `.env` returned the file with the database password (cf4c7be).
- **Uploads that run as code**: Checked the file name on upload, rename, create file, save file, paste, bulk copy and move, and extract, so a `.php` file can no longer be put into the web root (d43be11).
- **File manager paths**: Resolved every path against the root, which refuses `../` in the source and destination of rename, paste, bulk actions, compress, extract and delete. These could copy, move or delete files outside the root (d43be11).
- **History pages**: Applied the per-model group check to `admin/model-data/{id}`, `admin/entry-data/{id}` and both `clear-history` actions. An account in the `user` group could open the history of a model open to developers only (500e845).
- **Folder prefix check**: Stopped `validate_directory` from accepting a sibling folder such as `public_old` as a child of `public` (aa24229).

## [0.1.0-alpha.8] - 2026-11-19

This release hardens `hyper:upgrade` and fixes two issues in the API. The upgrade steps are the same as for 0.1.0-alpha.7. The upgrade test and the stress test both pass on MariaDB 10.4.32.

### Added

- **Upgrade warning**: Listed the entry versions that name one field id more than once after `hyper:upgrade`, because the new format keeps only the last value (a3c028b).
- **Upgrade stress test**: Added `check.ps1 -Stress`, which upgrades a reconstructed 0.1 database of 12000 entries and 36000 versions with large bodies, emoji and odd field lists (191ab16).

### Security

- **Development token route**: Limited `admin/access-token/generate` to the scopes the calling token already has, because a login token could mint one with every scope. The route exists only outside production (09eb277).

### Fixed

- **Model endpoint**: Answered a logged-in `POST /api/v1/model` without an `id` with a 400, where it ended in a 500 and a critical log entry (dbfb1e0).
- **Upgrade memory**: Read at most 8 MB of entry text at a time in `hyper:upgrade`, because a run of 1 MB bodies exhausted the 128 MB limit PHP has in Docker (a1af075).
- **Upgrade emoji**: Kept emoji in entry fields when `hyper:upgrade` converts them on a three byte `utf8` database, where each one became a question mark (05374b8).

## [0.1.0-alpha.7] - 2026-11-19

This release moves the data layer from code inside the app to the `damarbob/stardust` package, and it closes the API endpoints that anyone could call without logging in. A 0.1 database needs `php spark hyper:upgrade` before it runs on this version.

### Added

- **StarDust package**: Replaced the data layer inside the app with `damarbob/stardust` at `^0.2.0-alpha.2`.
- **Upgrade command**:
  - Added `php spark hyper:upgrade [--dry-run] [--yes]` to bring a 0.1 database onto the package tables (05daea3).
  - Documented the steps in the README under "Upgrading from 0.1".
- **Token API**:
  - Added `POST /api/v1/auth/login`, which returns an access token (328c1df).
  - Added read-only `GET /api/v1/entries-resource` and `GET /api/v1/entries-resource/{id}` (3215a6f, 3cf75db).
  - Limited the entries endpoints to the `superadmin`, `admin` and `developer` groups (7badc56).
- **Public models**: Added a public switch on the model form, so a model marked public stays readable on `/api/v1/model` without a login (4a06eca).
- **Regression captures**: Added `tests/regression/` with saved API and admin responses, a seed database and `check.ps1` to compare a running copy against them (3cbeaa0).

### Changed

- **Open endpoints**:
  - Required a login session or an access token on `/api/v1/models`, `/api/v1/model`, `/api/v1/model-data`, `/api/v1/entry-data`, `/api/v1/entries` and the Data Comparison data routes. Callers that used these without a login now get a 401 (a82a215).
  - Kept `/api/v1/model` open for models marked public.
- **Model user groups**: Applied each model's user groups across the entries API, and limited `model-data` to superadmins (3631dfa).
- **Access tokens**:
  - Expired login tokens after 30 days and added `expires_at` to the login response (beb4801).
  - Refused every access token that has no expiry date (417d801).
- **Data access**: Moved every controller, filter and module onto the new `ModelStore` and `EntryStore` classes in `app/Data/`, and moved `SyntaxProcessor` to `App\Libraries\SyntaxProcessor`.

### Fixed

- **Model versions list**: Fixed the 500 error when `model-data` was called without a sort column (4cf44e3).
- **Case-sensitive systems**: Loaded the StarDust models by full class name, which fixes the crash on a fresh Linux install reported in GitHub issue 3 (690d6c5).
- **Removed properties**: Served the old `BaseController` model properties on demand, so modules that still read them keep working (d853ce5).
- **Docker entrypoint**: Waited for the database before running setup and fixed the owner of `writable/logs` (ecb8659).
- **Line endings**: Kept shell scripts on LF line endings with `.gitattributes`, so `.docker/entrypoint.sh` still runs after a clone on Windows.

### Security

- **Sort and find injection**: Accepted only plain column names in the sort parameter, escaped the sort field in the model and entry data endpoints, and escaped the `find` filter (7ccd238, 7e2637d, df8631c).
