# Parsers and the Protection Mechanism

Texy has two parsers, both extending the abstract `Texy\Parser` (`src/Texy/Parser.php`), which only carries the reference to the `Texy` instance (`getTexy()`) and the target `HtmlElement`:

- **`InlineParser`** (`src/Texy/InlineParser.php`) – inline syntaxes inside lines,
- **`BlockParser`** (`src/Texy/BlockParser.php`) – multi-line block constructs.

They are not called directly; instead, `HtmlElement::parseLine()` and `HtmlElement::parseBlock()` create a parser with the element as the container and run it on given text. This makes parsing recursive: any syntax handler can create an element and parse its inner content the same way the main document is parsed.

## InlineParser

`InlineParser` processes inline syntaxes with an incremental algorithm that supports nesting and complex interactions between syntaxes.

**Basic principle:** find the *first* occurrence of *any* syntax. In each iteration the parser tries all registered (and allowed) line patterns and determines which one matches closest to the current position. That syntax "wins" and gets processed. If several syntaxes match at the same position, the one registered **earlier wins** – priority follows registration order.

When the nearest match is found, the parser calls its syntax handler. The handler returns an `HtmlElement` or a string, and this result **replaces the matched text** in the parsed string. The parser then continues searching from the current position. Because the replacement may itself contain text with further syntaxes, those are found in subsequent iterations – this is what enables gradual unwrapping of nested constructs.

The public property `InlineParser::$again` gives fine control over whether the just-matched syntax should be searched for again at the same position after processing the current match. The default is `false` ("no point looking for this same syntax here again, move on"). A syntax handler sets `$parser->again = true` when it produces an element whose textual content may contain further occurrences of the same syntax.

The pass ends when the parser reaches the end of the text or no syntax has any further match. The result is a string in which all recognizable syntaxes have been replaced by their results (masked, see below), ready to be attached to the container element.

### Nesting and why masking exists

`TextNode::$text` is **decoded display text**: HTML entities from the input are decoded at parse time (`Helpers::decodeEntities()`, which also strips control characters that entities could smuggle in). Renderers escape text on output. The exceptions holding literal source text are code-like content (`phrase/code`, `phrase/notexy` → `RawTextNode`, code blocks), where the author's characters are shown verbatim.

## BlockParser

`BlockParser` uses a fundamentally different approach reflecting the nature of block constructs. The key difference is the **absence of interleaving**: while `InlineParser` lets syntaxes nest and unwrap, `BlockParser` assumes each block is a standalone unit. A line or group of lines belongs to at most one block; blocks do not overlap, cross, or nest at the `BlockParser` level. (Nesting of block content happens through recursion – a handler calls `parseBlock()` on the extracted inner text.)

`BlockParser::parse()` first fires the `beforeBlockParse` notification event (with the parser and the text by reference – `BlockModule` uses this to normalize `/--` block fences). Then it locates the beginnings of all registered block syntaxes in the text. When several match at the same position, registration order decides – earlier registration wins. Text between matched blocks is handed to `ParagraphModule`, which splits it into paragraphs and invokes the `paragraph` element handler for each.

`BlockParser` also tracks whether the parsed text comes from an indented context (`isIndented(): bool`), which influences paragraph handling in nested content such as list items.

### BlockParser API for syntax handlers

Handlers of multi-line structures use two methods:

- **`next(string $pattern, ?array &$matches): bool`** – tries to match the *next line* against the given pattern (the `Am` modifiers are added automatically). On success it fills `$matches`, moves the internal position past that line, and returns `true`; otherwise returns `false` and the position stays. Typical use: consuming successive list items or table rows in a loop.
- **`moveBackward(int $linesCount = 1): void`** – moves the internal position back over the given number of line endings. Useful when the registered pattern matched in the middle of a structure and the handler wants to re-read from its beginning with `next()`.

## Post-line processing

After the DOM tree is serialized, `Texy::stringToHtml()` performs the final transformation of the internal string (`src/Texy/Texy.php`):

1. HTML entities in the text are decoded to UTF-8.
2. The string is split by the `CONTENT_BLOCK` mark and every *textual* segment (even-indexed, i.e. outside protected blocks) is run through the registered **post-line handlers** in order: `typography` (`TypographyModule::postLine()` – quotes, dashes, non-breaking spaces, symbols) and `longwords` (`HyphenationModule::postLine()` – inserting `&shy;` soft hyphens). Each handler is skipped if disabled in `$allowed`.
3. `<`, `>`, `&` are HTML-escaped (protected content is immune – it is still masked at this point).
4. `unprotect()` replaces all protection marks with the stored HTML.
5. The `postProcess` event fires; `HtmlOutputModule` well-forms and reformats the HTML here (auto-closing tags, fixing invalid nesting against the content model, indentation, line wrapping).
6. Frozen attribute spaces are restored (`Helpers::unfreezeSpaces()`).

`Texy::processTypo()` is a shortcut that runs only steps of typographic correction (and long words, if enabled) on plain text, without any Texy parsing.
