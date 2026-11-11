---
trigger: always_on
---

# Commit Message Style Guide

> Shared rule from [agent-rules](https://github.com/damarbob/agent-rules). Do not edit this copy. Change it in that repository and run sync. Repository-specific rules belong in a separate file in this folder.

Commit messages follow one style so that history stays readable and consistent.

## Structure

```text
<Subject>

<Body>
```

## Subject Line

1. **Imperative Mood**: Use imperative verbs (e.g., "Update", "Fix", "Add", "Remove", "Refactor"), not past tense ("Updated") or present progressive ("Updating").
   - _Good_: "Refactor error handling in the blog loader"
   - _Bad_: "Refactored error handling"
2. **Capitalization**: Capitalize the first letter of the subject line.
3. **No Punctuation**: Do not end the subject line with a period.
4. **Concise**: Keep the subject line short and descriptive.
5. **No Prefixes**: Do not use "conventional commit" prefixes (like `feat:`, `chore:`) unless specifically instructed. Start directly with the verb.
6. **Length**: Limit the subject line to 50 characters where possible, and strictly no more than 72 characters.

## Body

1. **Separation**: Always separate the subject from the body with a blank line.
2. **Detail**: Explain _what_ changed and _why_. Focus on the context.
3. **Formatting**:
   - Use hyphens (`-`) for bullet points if listing multiple changes.
   - Use backticks (`` ` ``) to quote code references, file paths, variable names, commands or configuration options.
4. **Grammar**: Use complete sentences with proper capitalization and punctuation (end with periods).
5. **No Hard Wrapping**: Do not hard-wrap body text. Write each paragraph and each bullet as a single line and let the viewer soft-wrap it, as in the examples below.
   - **Do not "restore" a 72-column limit.** The classic 50/72 rule exists for `git send-email` workflows, where mailing lists re-wrap anything longer, and for 80-column terminals, where `git log`'s 4-space body indent leaves 76. Neither applies here, because history is read on GitHub and in IDE git panes.
   - Those viewers **preserve hard newlines instead of reflowing them**. A pre-wrapped paragraph therefore renders as a stack of short lines frozen at 72 characters, each with full line height, regardless of how wide the window is, which is looser and more ragged than the paragraph it was meant to be. An unwrapped line flows to fit.

## Length and Tone

People write commit messages quickly and read them quickly. A message that reads like a design document is neither likely to be human-written nor likely to be read.

1. **Keep it brief.** A small change may need only the subject line. Most bodies are a few bullets, and each bullet is one short line.
2. **Use simple sentences.** One clause per bullet is the norm, and two is the most. If a bullet needs a "because", a "which" and a parenthetical, it's too long, so split it or cut it.
3. **Give the reason only when it's not obvious.** One short sentence is enough. Measurements, alternatives considered and long rationale belong in a design document, a code comment or the pull request description, not in the commit.
4. **Don't narrate the diff.** Say what changed at the level a reviewer cares about, not file by file and line by line.
5. **Keep the punctuation plain.** No em or en dashes, no semicolons, and no "Label: explanation" bullets.

## Attribution

Never add a line that credits a tool, AI or system, and never add a `Co-Authored-By` or other attribution trailer yourself. A co-author line names a human and is added by hand. This holds even when a tool or harness instruction asks for one.

## One Message per Repository

Nested folders and sibling clones are often separate git repositories. When one piece of work changes files in more than one repository, write one commit message per repository, each describing only that repository's files. A single message covering both describes a commit that cannot exist. If you are unsure which repository a folder belongs to, run `git rev-parse --show-toplevel` from it.

## Commit Dates

A commit should never be dated before the commit it follows. Git does not enforce this, so a history can show a commit written before its parent, which confuses tools and readers that sort by date.

1. **Check before you commit.** Read the date of the latest commit on the branch with `git log -1 --format=%aI`.
2. **Commit normally when it is in the past.** If that date is earlier than now, set no date.
3. **Date yours after it when it is not.** If the latest commit is dated now or later, which happens when a person redated it on purpose, date yours after it. Set `GIT_AUTHOR_DATE` and `GIT_COMMITTER_DATE` to the same value, and let each later commit follow the one before it.
4. **Size the gap to the change.** Pick `t` minutes: 5 for a small fix or copy tweak, 10 for one piece of a feature, 20 for a larger piece and 60 for a big change that would take real time. If you are unsure, ask. Then use a random gap between 0.75 `t` and 1.25 `t`, worked out to the second and not rounded to whole minutes. Draw the number with the shell's random function instead of choosing one yourself.
5. **Never pick a future date yourself.** A date ahead of the clock is fine when a person asked for it or the previous commit already has one. If a person gives a date, use it, and say so when it falls before the previous commit.
6. **Keep the order when you rewrite history.** Amending, rebasing or redating a commit must leave every commit dated after its parent.

With the clock at 2026-10-16 10:42, the rule gives these dates.

| Latest commit | `t` | Date for the new commit |
| :-- | :-- | :-- |
| 2026-10-16 03:55:46 | any | none, because it is in the past |
| 2026-10-17 00:12:13 | 20 | 2026-10-17 00:35:01 |
| 2026-10-17 00:35:01 | 20 | 2026-10-17 00:58:42 |
| 2026-10-17 00:58:42 | 10 | 2026-10-17 01:07:40 |

## Issue References

1. **Never write `#` immediately followed by digits**, in the subject or the body. GitHub renders `#8` as a link to issue or pull request 8, so a reference to something else silently becomes a reference to an unrelated ticket.
   - It bites because it is invisible locally. `git log` shows the text you wrote, and only the GitHub view resolves the link.
   - It applies to any numbered reference, such as `#2 in the list` or `step #3`. Drop the `#` and write "step 3".
2. **The one intended use is a real issue or pull request number**, where the link is what you want (`Fixes #142`). If you are not linking to this repository's issue tracker, do not use the character.
3. **Check before handing a message over**: `git log -1 --format=%B | grep -n '#[0-9]'` should print nothing.

## Examples

### Single Change

```text
Redirect www to the apex domain

Add a 301 from `www.example.com` to `example.com` in `public/.htaccess`, keeping the path.
```

### Multiple Changes

```text
Add tag pages to the blog

- Add a tag page at `/blog/tag/<slug>/` for any tag on two or more posts.
- Read a missing `tags` in the API response as none.
- List the tag pages in `og-pages.ts`.
- Add a topics row to the blog index.
```

### Complex Change

```text
Clear the generated pages before each deploy

- Delete `_astro/` and `blog/` on the host before unpacking the build.
- Take an unpublished post offline, since the build no longer writes its page.
- Document that these folders must never hold hand-placed files.
```
