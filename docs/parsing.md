# Parsers

Texy has two parsers producing AST nodes:

- **`InlineParser`** (`src/Texy/InlineParser.php`) – inline syntaxes inside lines,
- **`BlockParser`** (`src/Texy/BlockParser.php`) – multi-line block constructs.

Neither is used directly; parsing goes through a **`ParseContext`** (`src/Texy/ParseContext.php`), which holds one instance of each and exposes:

- `parseInline(string $text, int $baseOffset = 0): ContentNode`
- `parseBlock(string $text, int $baseOffset = 0): ContentNode` – clones the block parser, so nested block parsing is isolated from the outer parser's position state,
- `getBlockParser()` – access to the running block parser's navigation API (`next()`, `moveBackward()`).

This makes parsing recursive: any syntax handler can call `$context->parseInline()` / `parseBlock()` on extracted inner text and attach the resulting children to its node. Nesting of syntaxes happens **only** through this explicit recursion – the parsers never re-scan a handler's output.

## InlineParser

`InlineParser::parse()` runs in one pass over the raw text:

1. **Match everything.** Every registered (and allowed) pattern is matched against the whole text with `preg_match_all` + offset capture.
2. **Sort.** All matches are ordered by offset; at the same offset the **longer match wins**, and equal-length ties fall back to **registration order** (the sort is stable). This is the entire collision-resolution mechanism.
3. **Process left to right, without overlaps.** Matches overlapping an already-consumed region are skipped. For each surviving match the syntax handler is called; it returns an `InlineNode` or `null`.
   - `null` means the handler **refused** the match (e.g. an unknown abbreviation): the text is left alone and later matches – including overlapping ones from other patterns – still get their chance. Invariant: the plain text preceding a match is emitted as a `TextNode` only once a handler *accepts*; a rejected match must not consume or duplicate it.
4. **Text gaps** between accepted matches become `TextNode`s.

Because matching happens once on the raw input, a handler's output is never re-interpreted – there is no masking, no placeholder mechanism, no re-entry. What the old string-based pipeline achieved with protection marks now follows from the AST structure itself.

### Collisions in practice

- Different lengths of the same symbol (`***x***` vs `**x**` vs `*x*`) are resolved by the longer-match rule.
- Same position, same length: earlier registration wins. Built-in modules register their patterns in `beforeParse()` during `process()`; the `Engine` keeps insertion order per name, so a custom pattern registered before the first `process()` call actually precedes all built-ins, and re-registration on later calls keeps the original position.
- Keep patterns specific (e.g. wikilink `[text|url]` requires the pipe, markdown link `[text](url)` requires `](`) so most collisions never arise.

### TextNode holds display text

`TextNode::$text` is **decoded display text**: HTML entities from the input are decoded at parse time (`Helpers::decodeEntities()`, which also strips control characters that entities could smuggle in). Renderers escape text on output. The exceptions holding literal source text are code-like content (`phrase/code`, `phrase/notexy` → `RawTextNode`, code blocks), where the author's characters are shown verbatim.

## BlockParser

Blocks never nest or overlap at parser level – every line belongs to at most one block construct. `BlockParser::parse()`:

1. Matches all registered block patterns against the whole text (multiline mode is forced), sorts matches by position, then by registration order.
2. Walks the sorted matches; text between them goes to the **gap handler** – `ParagraphModule::parseText()` (wired in `Texy::parse()`).
3. Calls the syntax handler of each match. The handler may consume additional lines through the parser API; returning `null` rejects the match and the position stays for the gap handler.

Handlers of multi-line structures use the navigation API on `$context->getBlockParser()`:

- **`next(string $pattern, ?array &$matches, ?array &$offsets = null): bool`** – matches the *next line* against the pattern (`Am` modifiers added automatically); on success fills `$matches`, advances past the line and returns `true`. Typical use: consuming successive list items or table rows in a loop.
- **`moveBackward(int $linesCount = 1): void`** – moves back over line endings; used when the registered pattern matched in the middle of a structure and the handler wants to re-read from its beginning with `next()`.

## ParagraphModule (the gap handler)

`ParagraphModule` is not pattern-based: it receives the text between block matches, splits it on blank lines, and parses each paragraph's inline content. Details that matter:

- A paragraph-level modifier (`.[class]` on its own line-end) is extracted before inline parsing.
- **Hard line breaks:** with `$texy->mergeLines` (default), a newline followed by indentation becomes a break, other newlines merge into spaces; without it every newline is a break. The break is carried through inline parsing as an internal `\r` marker inside text and expanded afterwards into `LineBreakNode`s – which is why `\r` can never occur in input text (normalization removed it) and why `decodeEntities` deliberately preserves `\x0D`.
- Text starting with a block-level HTML tag is parsed without soft-line-break processing and flagged `ParagraphNode::$blockHtml` – the renderer then emits no `<p>` wrapper.
- Whether a `<p>`, a non-text wrapper (`$htmlOutput->nontextParagraph`, default `div`), or no wrapper is emitted is a **render-phase** decision based on analyzing the paragraph's children (text vs. replaced elements vs. markup) – see `Renderer::renderParagraph()`.

## Other internal markers

- `\r` – hard line break marker inside `ParagraphModule` (see above).
- `\x13` – temporary mask for escaped pipes in `TableModule` while splitting a row on `|`; restored in each cell.
- Bytes `\x15`–`\x17` are the marker alphabet of the typography pass ([architecture.md](architecture.md#typography-over-the-ast)). Input normalization strips all control bytes, so none of them can arrive from outside.
