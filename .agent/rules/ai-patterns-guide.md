---
trigger: always_on
---

# Patterns AI Repeats

> Shared rule from [agent-rules](https://github.com/damarbob/agent-rules). Do not edit this copy. Change it in that repository and run sync. Repository-specific rules belong in a separate file in this folder.

Text, structure and code written by AI share recognisable habits. Each habit is fine once. Repeated, or stacked together, they make the work read as generated, and a reader who spots them stops trusting what is behind them. This guide lists the habits so you can catch them in your own draft.

The fix is the same everywhere. Fit the shape to the content, vary it from one part to the next, and prefer a concrete fact to a general claim.

## In prose

- Filler openers such as "It's worth noting that", "Note that", "Here's the thing", "Let's dive in", "In summary", "In conclusion" and "Whether you're X or Y".
- "Not just X, it's Y" and "not X, but Y" used as a hook.
- Lists of exactly three ("fast, safe and simple") when the real count is two or four.
- A closing sentence on every paragraph that restates the paragraph.
- Stacked hedging, such as "may potentially" or "can generally help to".
- Stock words such as delve, seamless, robust, powerful, leverage, comprehensive, unlock, empower, elevate, streamline, holistic, pivotal, tapestry, landscape, realm and journey.
- Emoji in headings or as bullet markers.

| Instead of | Write |
| :-- | :-- |
| It's worth noting that the cache is cleared on deploy. | The cache is cleared on deploy. |
| The tool is fast, reliable, and easy to use. | The tool starts in under a second. |
| Not just a linter, it's a complete quality platform. | It lints, and it fixes what it can. |

## In structure

- Every section has the same shape, such as an intro sentence, three bullets and a wrap-up line.
- Bullets are all the same length and grammar.
- A bold label and a colon open every bullet.
- Headings follow one template, such as "Why X matters" repeated, or a question for every heading.
- A table or list stands where one sentence would do, or headings sit over a two-sentence answer.
- A symmetric "pros and cons" or "benefits and challenges" pair is added for balance.
- A "TL;DR", "Key takeaways" or "Conclusion" section appears by default.
- Numbered steps are used for things that have no order.

Pick the form from the content. A sequence gets numbers, a comparison gets a table, and an explanation gets paragraphs. Let sections differ in length.

## In chat replies

- Praise or agreement before the answer, such as "Great question!", "Certainly!" or "You're absolutely right".
- Restating the request before answering it.
- Closing offers such as "Let me know if you'd like..." and "I hope this helps".
- Narrating what you are about to do instead of doing it.
- Headings, bold and bullets around a short answer.
- A list of alternatives nobody asked for.
- Apologising in a loop, or agreeing with a correction before checking it.

Answer first, in plain sentences. State the result or the decision, then the reason if it is needed.

## In code and commits

- Comments that restate the line below them.
- A docstring on every trivial function.
- Names ending in Helper, Manager, Util or Handler when a precise name exists.
- A `try` and `catch` around code that cannot fail, or one that catches and swallows the error.
- New abstractions, options or files that the task did not ask for.
- A refactor mixed into a fix.
- A commit message or pull request description that narrates the diff file by file, or fills a Summary and Test plan template for a one-line change.

## In numbers and evidence

- Never invent a statistic, quote, testimonial, benchmark, customer, date or version. If you don't have it, say so or leave it out.
- Round figures such as "10x faster", "99.9%" and "thousands of users" need a source, or they go.
- A figure needs its context. Say what was measured, on what, and against what.
- Claims that sweep ("the fastest", "the most secure") need the same proof as a figure, or they go.

## Before handing over

1. Read the draft as a stranger. Do two sections share the same shape?
2. Count the bullets that open with a bold label.
3. Look for any figure you cannot source.
4. Cut the first and the last sentence of each section. If nothing is lost, leave them cut.
5. Check each list. Does its length serve the content, or is it three out of habit?
