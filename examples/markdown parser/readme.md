# A Markdown Parser on the Texy Engine

> **Looking for Markdown support? Use [texy/markdown](https://github.com/dg/texy-markdown).**
>
>     composer require texy/markdown
>
> ```php
> $markdown = Texy\Markdown\Markdown::gfm();
> $html = $markdown->toHtml($text, $texy);
> ```
>
> This directory is not that package. It is the experiment the package grew out of, kept
> as a readable proof that the engine carries a foreign language – see below.

`Texy\Parsing\Engine` contains nothing specific to the Texy language. It is a registry of
regular expressions with handlers, a block parser, an inline parser and a gap handler for
whatever the block patterns do not claim. This example builds a **different markup
language** on it, CommonMark, to show where that boundary really lies.

## The package this became

`texy/markdown` is the finished version of the idea, and it differs in every dimension
that matters:

| | this example | texy/markdown |
|---|---|---|
| CommonMark 0.31.2 | 611 of 652 | **652 of 652** |
| GitHub Flavored Markdown | – | **all five extensions** |
| HTML output | its own, matching the reference implementation | **Texy's renderer**: typography, sanitization, well-forming, Latte |
| Markdown output | – | **renders any Texy AST back to Markdown** |
| Size | ~900 lines, one class | a package |

Because both produce the same `Texy\Nodes\*` tree, converting between the two languages
is a matter of picking a parser and a renderer, and a Markdown document gets everything
Texy does to a Texy one.

What this example is still good for is understanding the engine: it stays short enough
to read in one sitting, which the package no longer is.

```bash
php "markdown parser/demo.php"              # parse a sample document
php "markdown parser/spec-test.php"         # score against the official test suite
php "markdown parser/spec-test.php" Links -v   # one section, with a diff per failure
```

## Where it stands

**611 of 652 CommonMark 0.31.2 examples (94%)**, in roughly 900 lines. The remaining
failures are unfinished corners of this example (tabs as columns inside list content,
multiline link destinations, chained reference links), not walls the engine puts up –
`texy/markdown` closed every one of them without changing the engine.

Thirteen of the twenty-six sections are complete, emphasis and strong emphasis among them
(all 132 examples, the hardest section of the spec), along with fenced code blocks, code
spans, ATX headings, autolinks, backslash escapes, entity references and paragraphs. The
thirteen that are not:

| Section | | Section | |
|---|---|---|---|
| Thematic breaks | 95% | Setext headings | 89% |
| HTML blocks | 95% | Links | 88% |
| Images | 95% | Hard line breaks | 87% |
| Raw HTML | 95% | Indented code blocks | 83% |
| Block quotes | 92% | Link reference definitions | 78% |
| Lists | 92% | Tabs | 73% |
| List items | 90% | | |

Parsing and rendering the 200 KB spec document takes about 50 ms.

## What the engine gives you and what it does not

**Block constructs fit the model directly.** A pattern recognises the first line, the
handler reads the rest itself with `BlockParser::next()` and recurses into
`ParseContext::parseBlock()` for the content. Block quotes with lazy continuation, all
seven kinds of HTML block and fenced code came out right straight away.

**Inline constructs fit as long as a match can be decided locally.** Where it cannot, the
handler returns `null` and the position goes back to the other patterns. That is how this
parser enforces "links may not nest" and how it gives code spans and raw HTML the
precedence the spec wants (see `parseLink()`).

**Context-sensitive rules use the parser API instead of regex tricks.** Whether a list
may interrupt a paragraph depends on what block precedes it - `getNodes()` answers that
structurally, and since the nodes are live, the handler grafts a non-interrupting line
onto the preceding paragraph as its lazy continuation (see `parseList()`). A construct
that only collects data, like a link reference definition, returns an empty `ContentNode`:
the match is consumed and nothing is emitted.

**Emphasis does not fit at all, and that is the interesting part.** Whether `*` opens
emphasis, closes it or is literal text depends on the whole sequence of delimiter runs in
the container, not on anything visible at the match. A regex approximation of the rules
stalls at 56% of the emphasis examples. So the parser only *marks* the runs
(`DelimiterNode`) and the pairing happens afterwards over the AST, in
`Markdown::processEmphasis()`. That single change takes emphasis from 56% to 100%.

Reference links go the same way: `[foo]` cannot be resolved while parsing, because
`[foo]: /url` may come after the paragraph that uses it. The node is created unresolved
and either resolved or degraded back to literal text in the transform phase, which is
exactly what Texy's own `LinkReferenceModule` does.

The lesson is not that the engine is short of something. It is that **the parse phase is
for what a match can decide and the transform phase is for what it cannot**, and a
language whose spec is written as a stack algorithm needs both.

The package takes that lesson further than this example does. There, *no* inline content
is parsed during the block phase: every construct that carries some leaves it deferred and
one transform pass does the lot, at which point every link reference definition in the
document is already known and a bracket can simply be refused when it resolves to nothing.
That is what closes the last of the gaps this example still has.

## Files

| File | |
|---|---|
| `Markdown.php` | the parser: pattern registration, handlers, transform phase |
| `nodes.php` | the few AST nodes Markdown needs beyond `Texy\Nodes\*` |
| `HtmlRenderer.php` | AST to HTML, matching the reference implementation byte for byte |
| `demo.php` | parse a sample document and render it |
| `spec-test.php` | score against the CommonMark suite (downloaded on first run) |
