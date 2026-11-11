---
trigger: always_on
---

# Writing Style Guide

> Shared rule from [agent-rules](https://github.com/damarbob/agent-rules). Do not edit this copy. Change it in that repository and run sync. Repository-specific rules belong in a separate file in this folder.

How text is written. The goal is text that reads as if a person wrote it, because a reader who spots machine-written patterns stops trusting the content behind them.

## Scope

This guide covers every text a person reads:

- READMEs, documentation, changelogs, contributing guides and design documents.
- Code comments and docblocks.
- Commit messages and pull request descriptions.
- Terminal output, `--help` text, error and exception messages, and interface copy.
- Release notes, websites, social posts, and video scripts and their on-screen text.

It applies in every language the text is published in.

It does **not** cover files written for AI agents. That means `CLAUDE.md`, `AGENTS.md` and the `.agent/rules/` folder. Those stay optimised for agents, so the patterns below are fine there. Code is always exempt, including identifiers, commands, SQL, JSON, YAML and anything inside backticks.

A repository can narrow or extend this scope, and name the tests that enforce parts of it, in a rules file of its own.

## Banned

These are strong signals of machine-written text. Don't use them in covered text.

### The em dash and the en dash

Never use `—` (em dash) or `–` (en dash). Pick whichever of these fits the sentence:

- Split it into two sentences.
- Use a comma.
- Put the aside in parentheses.
- Name the relationship with a word such as "so", "because", "which means" or "with".

| Instead of | Write |
| :-- | :-- |
| Fast setup — no config files, no build step. | Setup is fast, with no config files and no build step. |
| Writes always go to the primary — replicas are read-only. | Replicas are read-only, so writes always go to the primary. |
| Node 18–22 | Node 18 to 22 |

The ordinary hyphen `-` is not affected. Hyphenated words such as "end-to-end", "on-screen" and "read-only" stay as they are. A hyphen standing in for a dash, as in "setup - no config" or "setup -- no config", is the same pattern and is banned too.

An empty table cell stays empty or says "n/a". It never holds a dash.

### Colons and semicolons in headings and titles

A heading, page title, release title, slide title, video title or post title never contains `:` or `;`. People keep titles clean, and a split "Name: subtitle" title is one of the most recognisable machine patterns.

| Instead of | Write |
| :-- | :-- |
| Acme: Fast Search for Everyone | Fast search in Acme |
| Exports: How They Work | How exports work |

### Colons anywhere in social posts and video

Social copy, video narration and on-screen video text don't use colons at all, prose included. Those channels have a stricter rule than everything else.

## Use sparingly

These are normal in human writing too, so they're not banned. Reach for something else first.

### Colons in prose

A colon that introduces a list or explanation in the middle of a paragraph is acceptable, but a full stop usually reads better.

| Instead of | Write |
| :-- | :-- |
| A short walkthrough: install the package, create a config, run the first build, and read the output. | This walkthrough goes from an empty folder to a first build. You'll install the package, create a config, run the build and read the output. |

Structural colons are fine. That covers `key: value` in a config example, a time such as 14:30, and a ratio such as 80:20. Before a code block, prefer a sentence ending in a full stop ("Run the build once.") over one ending in a colon.

### Semicolons

Use two sentences, or join the clauses with a word.

| Instead of | Write |
| :-- | :-- |
| The loader reads the file; the parser builds the tree; the writer emits the result. | The loader reads the file and the parser builds the tree. The writer then emits the result. |

## Overclaiming and stacked claims

A strong claim is a legitimate marketing tool. Used once, beside a concrete fact, it's fine. Stacked, it backfires.

Stacked claims are prohibited by default. That means two or more overclaims in one line or block, such as "Blazing-fast filtering with unparalleled scalability", or a superlative in every sentence. Don't write them unprompted, and don't relax this because the text is marketing.

1. Write stacked claims only when the user asks for them in that request.
2. Even then, ask the user to confirm before you write them, and that includes promotional copy such as a launch video, a landing page or a post. Many people don't realise how stacked claims read. Explain the implications in a few sentences. Readers discount the whole text, so the true claims lose trust along with the inflated ones. Every claim invites a check, and one that fails discredits the rest. Some regions regulate advertising claims, and absolute claims draw the most attention. Then do what the user decides, and don't repeat the warning.
3. The evidence exception is almost never available. Stacked claims are acceptable only when every claim is backed by complete underlying data that the reader can inspect. For sweeping claims such as "100% accurate", "zero downtime", "the fastest" or "the most secure", complete data is statistically out of reach, so treat the exception as closed. Never use it to justify a claim.

The working pattern is one overclaim where it matters most, with a concrete fact next to it. The words below are the ones to count. Any of them is fine once.

- **Overclaims.** Unparalleled, unprecedented, unmatched, unrivalled, revolutionary, groundbreaking, game-changing, game-changer, cutting-edge, state-of-the-art, next-generation, best-in-class, world-class, industry-leading, enterprise-grade, bulletproof, blazing fast, lightning-fast, effortless, ultimate, perfect, flawless, limitless, infinitely.
- **Words generated text reaches for.** Delve, seamless, seamlessly, robust, powerful, leverage, comprehensive, unlock, empower, elevate, supercharge, harness, streamline, transformative, holistic, pivotal, crucial, paramount, myriad, plethora, tapestry, landscape, realm, journey, "in today's fast-paced world".

| Instead of | Write |
| :-- | :-- |
| Blazing-fast filtering with unparalleled scalability. | Filtering runs on the database's own indexes, with no separate search service to operate. |

## Other habits

Repeated bold labels, lists of three, filler openers and summary closers are also signs of machine-written text. Fit the shape of each section to its content, and don't give every section the same one.

## Voice

- **About 80% formal and 20% conversational.** Think of a well-written post by an engineer explaining their own project. Address the reader as "you", use contractions where they read naturally, and say "we" when speaking as the project's maker.
- **Adjust by text type.** A tutorial, a README introduction, a website and social posts sit at the conversational end of that range. Lookup pages (configuration fields, error codes, CLI reference), design documents, contributing guides and test documentation sit nearer fully formal. Code comments are plain and short and say why rather than what. Commit messages are brief.
- **Vary the rhythm.** Mix long sentences with short ones. Don't give every section the same intro, bullets and wrap-up shape. Mix prose with lists instead of turning everything into bullets. A little unevenness is what makes text read as human.
- **Never introduce errors on purpose.** Variation means sentence length, structure and punctuation choices. It never means typos or broken grammar.
- **Error and exception messages** say what happened and, where it helps, what to do next, in one or two plain sentences.

## Before handing text over

1. Search the changed text for `—` and `–`, and for a spaced hyphen used as a dash. There should be none.
2. Check every heading and title for `:` and `;`.
3. Look at each semicolon and each colon in prose, and rewrite it unless it's clearly the best choice.
4. Check the draft against the overclaim list, and for the habits named under Other habits.
5. Read the draft as its reader would. If any part sounds like a template, rewrite it.
