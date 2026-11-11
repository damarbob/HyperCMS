---
trigger: always_on
---

# Changelog Style Guide

> Shared rule from [agent-rules](https://github.com/damarbob/agent-rules). Do not edit this copy. Change it in that repository and run sync. Repository-specific rules belong in a separate file in this folder.

A changelog follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) so that history stays readable and consistent. This guide sets the layout and the item format. It does not set a versioning scheme, so a repository states its own, for example whether it uses pre-release labels, in a rules file of its own.

## Structure

The changelog groups notable changes for each release.

### Version Headers

1. **Format**: Use `## [Version] - YYYY-MM-DD`.
2. **Example**: `## [1.4.0] - 2026-02-22`.

### Categories

Organize changes under these specific category headers:

- `### Added` for new features.
- `### Changed` for changes in existing functionality.
- `### Deprecated` for soon-to-be removed features.
- `### Removed` for now removed features.
- `### Fixed` for any bug fixes.
- `### Security` in case of vulnerabilities.

## Item Formatting

1. **Feature Grouping**: Group related changes under a bolded feature or domain name.
   - Format: `- **Feature Name**: Description of change`
   - The bold label is intended here. A changelog is scanned more than it is read, so this is the one list where a label on every bullet helps.
2. **Sub-items**: If a feature has multiple distinct changes, use bulleted sub-items indented by two spaces.

   ```markdown
   - **Blog tags**:
     - Tag pages for any tag on two or more posts.
     - A topics row on the blog index.
   ```

3. **Commit Hashes**: Optional, and expected mainly for released versions where an entry maps cleanly onto one or two commits. Conclude the entry with the short hash(es) in parentheses when they add traceability.
   - Hashes should be exactly 7 characters.
   - If multiple commits apply, separate them with a comma and a space.
   - Example: `(cb4cb2c, 7942ab7)`
   - Omit them where an entry summarises a whole phase or a long-running branch, since per-bullet hashes would be noise rather than traceability.
4. **Language & Grammar**: Start descriptions with active, past-tense verbs (e.g., "Implemented", "Added", "Refactored", "Fixed") or concise descriptive noun phrases (e.g., "Tag pages for the blog"). End descriptions with a period.
5. **Code References**: Use backticks (`` ` ``) to quote code references, file names, paths and variables (e.g., `deploy.sh`, `public/.htaccess`, `BLOG_API_URL`).

## Example

```markdown
## [1.4.0] - 2026-02-22

### Added

- **Blog tags**: Added tag pages for any tag on two or more published posts (a732a06, 0dc2aa7).
- **Deploy workflow**:
  - Rebuilt the site from GitHub Actions on every push to `main` (cb4cb2c).
  - Redirected `www` to the apex domain (796dfa0).

### Fixed

- **Blog cleanup**: Cleared the generated pages on the host before each deploy (ecfbaa6, 6f0cfab).
```
