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

`InlineParser::parse()` walks the text left to right, holding **one candidate match per pattern** and refreshing it whenever the position moves past it:

1. **Candidates.** Each registered (and allowed) pattern contributes its first match; as the position advances, a stale candidate is re-searched from the current position. Searching each pattern only once up front would lose matches – a pattern returns non-overlapping matches, so a wrong early one (say, a `**` inside a code span pairing with the next `**`) would swallow the region where the right one sits.
2. **Best candidate wins.** The **leftmost** candidate is processed; at the same offset the **longer match wins**, and equal-length ties fall back to **registration order**. This is the entire collision-resolution mechanism.
3. **Handler call.** The syntax handler returns an `InlineNode`, a `ContentNode`, or `null`.
   - A `ContentNode` return is a **sequence**: its children are spliced into the result in place of a single node. An empty one consumes the match without emitting anything (useful for constructs that only collect data). Returning the result of `$context->parseInline()` directly therefore splices, not nests.
   - `null` means the handler **refused** the match: the text is left alone and later matches – including overlapping ones from other patterns – still get their chance. Invariant: the plain text preceding a match is emitted as a `TextNode` only once a handler *accepts*; a rejected match must not consume or duplicate it. Refusal doubles as a **priority mechanism**: a syntax that recognizes it should not win in a given context steps aside for the patterns behind it, without any global priority table.
4. **Text gaps** between accepted matches become `TextNode`s.

Because matching happens once on the raw input, a handler's output is never re-interpreted – there is no masking, no placeholder mechanism, no re-entry. What the old string-based pipeline achieved with protection marks now follows from the AST structure itself.

A handler sees only its own match, never the surrounding text. A pattern that needs a neighbouring character *as a value* (not merely as a guard) captures it through a lookbehind group: `(?:(?<=(.))|(?<!.))` puts the preceding character into group 1, or leaves it unset at the start of the text.

### Collisions in practice

- Different lengths of the same symbol (`***x***` vs `**x**` vs `*x*`) are resolved by the longer-match rule.
- Same position, same length: earlier registration wins. Built-in modules register their patterns in `beforeParse()` during `process()`; the `Engine` keeps insertion order per name, so a custom pattern registered before the first `process()` call actually precedes all built-ins, and re-registration on later calls keeps the original position. A name registered via `addLineSyntax()`/`addBlockSyntax()` is owned by the consumer: module registration never overwrites it, so overriding a built-in syntax (registering under its `Syntax::*` name) works and survives repeated parsing.
- Keep patterns specific (e.g. wikilink `[text|url]` requires the pipe, markdown link `[text](url)` requires `](`) so most collisions never arise.

### TextNode holds display text

`TextNode::$text` is **display text**: renderers escape it on output. Text gaps between matches hold the source verbatim – the parser applies no content policy. HTML entity decoding is a property of the *language*, not of the parser, so it is an ordinary registered syntax: Texy's `html/entity` (`HtmlModule`, disable via `$allowed['html/entity']`) turns each valid reference into its own `TextNode` with decoded content and an exact source range. The pattern and decoding logic are shared through `Helpers::EntityPattern` / `Helpers::decodeEntity()` so other languages (texy/markdown) reuse them. The exceptions holding literal source text are code-like content (`phrase/code`, `phrase/notexy` → `RawTextNode`, code blocks), where the author's characters are shown verbatim.

Every decoder that can create control characters must sanitize its output with `Helpers::stripControlChars()` – input normalization strips raw control bytes, but a decoded entity is born *after* normalization and would otherwise collide with the internal marker alphabet.

## BlockParser

Blocks never nest or overlap at parser level – every line belongs to at most one block construct. `BlockParser::parse()`:

1. Trims trailing newlines of the input (they are not part of any line, so `next()` never yields a phantom empty last line), then matches all registered block patterns against the whole text (multiline mode is forced) and sorts matches by position, then by registration order.
2. Walks the sorted matches; text between them goes to the **gap handler** – `ParagraphModule::parseText()` (wired in `Texy::parse()`).
3. Calls the syntax handler of each match. The handler may consume additional lines through the parser API. As in the inline parser, the handler returns a `BlockNode`, a `ContentNode` (a **sequence** of blocks spliced into the result; an empty one consumes the match silently), or `null` to reject – the position then stays for the gap handler.

Handlers of multi-line structures use the navigation API on `$context->getBlockParser()`:

- **`next(string $pattern, ?array &$matches, ?array &$offsets = null): bool`** – matches the *next line* against the pattern (`Am` modifiers added automatically); on success fills `$matches`, advances past the line and returns `true`. Typical use: consuming successive list items or table rows in a loop.
- **`moveBackward(int $linesCount = 1): void`** – moves back over line endings; used when the registered pattern matched in the middle of a structure and the handler wants to re-read from its beginning with `next()`.
- **`getNodes(): array`** – the blocks produced so far at this nesting level, gap-handler output included; the last one is the block immediately preceding the current match. The gap handler runs *before* the match handler, so a paragraph sitting right before the construct is already there. This makes context-sensitive rules ("this construct may not interrupt a paragraph") a structural check instead of a fragile lookbehind, and since the nodes are live objects, a handler may deliberately graft consumed text onto the preceding block (lazy paragraph continuation).

## ParagraphModule (the gap handler)

`ParagraphModule` is not pattern-based: it receives the text between block matches, splits it on blank lines, and parses each paragraph's inline content. Details that matter:

- A paragraph-level modifier (`.[class]` on its own line-end) is extracted before inline parsing.
- **Hard line breaks:** with `$texy->paragraphModule->mergeLines` (default), a newline followed by indentation becomes a break, other newlines merge into spaces; without it every newline is a break. The break is carried through inline parsing as an internal `\r` marker inside text and expanded afterwards into `LineBreakNode`s – which is why `\r` can never occur in input text (normalization removed it) and why `stripControlChars` deliberately preserves `\x0D`.
- Text starting with a block-level HTML tag is parsed without soft-line-break processing and flagged `ParagraphNode::$blockHtml` – the renderer then emits no `<p>` wrapper.
- Whether a `<p>`, a non-text wrapper (`$htmlOutput->nontextParagraph`, default `div`), or no wrapper is emitted is a **render-phase** decision based on analyzing the paragraph's children (text vs. replaced elements vs. markup) – see `Renderer::renderParagraph()`.

## Other internal markers

- `\r` – hard line break marker inside `ParagraphModule` (see above).
- `\x13` – temporary mask for escaped pipes in `TableModule` while splitting a row on `|`; restored in each cell.
- Bytes `\x15`–`\x17` are the marker alphabet of the typography pass ([architecture.md](architecture.md#typography-over-the-ast)). Input normalization strips all control bytes, so none of them can arrive from outside.
