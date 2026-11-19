# Changelog

All notable changes to HyperCMS are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added

- **Upgrade warning**: Listed the entry versions that name one field id more than once after `hyper:upgrade`, because the new format keeps only the last value (a3c028b).
- **Upgrade stress test**: Added `check.ps1 -Stress`, which upgrades a reconstructed 0.1 database of 12000 entries and 36000 versions with large bodies, emoji and odd field lists (191ab16).

### Fixed

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
