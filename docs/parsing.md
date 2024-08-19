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

The ability to handle nested syntaxes poses a fundamental challenge: how to prevent already-generated HTML tags from being re-interpreted as new syntax.

Take `"odkaz **tučný** text":URL`. The parser first finds the link syntax; its handler builds an `<a>` element whose content is the raw inner text `odkaz **tučný** text`. The stringified element goes back into the parsed text, and parsing continues – next the `**bold**` phrase inside is found and processed. But if the `<a href="...">` markup were present verbatim in the text, its characters could match other patterns and get mangled.

The solution is `Texy::protect()`: instead of real HTML, a unique placeholder made of control characters is inserted. When an `HtmlElement` is converted to a string during parsing (`toString()`), the result looks like `\x17\x18\x19\x17odkaz **tučný** text\x17\x18\x1A\x17` rather than `<a href="...">odkaz **tučný** text</a>`. Real HTML tags are never present in the text during parsing – only placeholders – while the inner text remains visible to the parser so nested syntaxes can still be found.

At the very end of processing, `Texy::unprotect()` replaces all placeholders with their stored values. Only at that moment do real HTML tags enter the output.

### Syntax collisions

A collision occurs when several syntaxes can match at the same position and the system must pick one.

A typical example is different lengths of the same symbol: `***text***` (`phrase/strong+em`), `**text**` (`phrase/strong`), `*text*` (`phrase/em-alt`). `PhraseModule` resolves this by registering the syntaxes from longest to shortest, so with three asterisks `phrase/strong+em` is processed first and shorter syntaxes never get the chance (`src/Texy/Modules/PhraseModule.php`).

Another example is bracketed constructs: `phrase/wikilink` (`[text|url]`) versus `link/reference` (`[ref]`). Both start with `[`. Pattern specificity resolves it: the wikilink pattern requires a pipe inside the brackets; if there is none, the pattern fails and `link/reference` gets its chance. Registration order matters here too.

Rules of thumb: register more specific syntaxes before more general ones, and make patterns as specific as possible to avoid false matches.

## Protection marks

`Texy::protect(string $child, string $contentType): string` stores the string in an internal table and returns a key of the form:

```
{contentType} {octal counter encoded with bytes \x18–\x1F} {contentType}
```

Different kinds of content use different content-type marker bytes, defined as constants on `Texy`:

| Constant | Byte | Meaning |
|---|---|---|
| `Texy::CONTENT_MARKUP` | `\x17` | ordinary inline HTML markup (formatting tags, links) |
| `Texy::CONTENT_REPLACED` | `\x16` | replaced content – images and other replaced elements |
| `Texy::CONTENT_TEXTUAL` | `\x15` | protected text – code, notexy; displayed literally, not interpreted |
| `Texy::CONTENT_BLOCK` | `\x14` | block elements; the lowest level in the hierarchy |

The constant `Patterns::MARK` is defined as the range `\x14-\x1F`, covering all marker types plus the counter bytes. Patterns use it to exclude masked parts from matching. Because the counter bytes (`\x18`–`\x1F`) sort *above* all four type markers, a whole placeholder of a given type matches the character class starting at its marker: `[\x17-\x1F]+` matches a complete `CONTENT_MARKUP` placeholder, `[\x16-\x1F]+` additionally matches `CONTENT_REPLACED` ones, and `[\x14-\x1F]+` matches any placeholder. This is what makes the level hierarchy usable in regexes.

The hierarchy has practical consequences: a pattern that wants to see only plain text excludes the full range `[^\x14-\x1F]` (e.g. the image URL pattern – a URL must not contain any masked content). A pattern that accepts lower levels but rejects higher ones uses a narrower range, e.g. `[^\x17-\x1F]` rejects only `CONTENT_MARKUP` while accepting blocks, textual content, and replaced content.

### What gets which content type

| Content | Type |
|---|---|
| HTML tags written in the input | by element: replaced elements (`img`, `br`, `input`...) → `CONTENT_REPLACED`, known inline elements → `CONTENT_MARKUP`, everything else → `CONTENT_BLOCK` (decided by `HtmlElement::getContentType()`; `HtmlModule` uses it when protecting tags) |
| HTML comments | `CONTENT_MARKUP` |
| `''notexy''` and `` `inline code` `` | `CONTENT_TEXTUAL` (escaped, displayed literally) |
| the label of a resolved `[ref]` reference | `CONTENT_TEXTUAL` |
| `/-- … \--` fenced blocks (code, html, text...) | `CONTENT_BLOCK` |
| generated inline elements (phrases, links) | `CONTENT_MARKUP` (via `toString()` / `getContentType()`) |
| generated `<br>` from hard line breaks | `CONTENT_REPLACED` |

### How content types drive behavior

The type markers are not just masking – several parts of Texy read them to make decisions:

- **Paragraph detection** (`ParagraphModule::solve()`, `src/Texy/Modules/ParagraphModule.php`). After parsing a paragraph's inline content, the module inspects which marks the result contains, in this order: contains `CONTENT_BLOCK` → no `<p>` is emitted, the element becomes transparent (the block stands on its own; note the modifier is ignored in this case); contains `CONTENT_TEXTUAL` or any real text → `<p>` stays; contains only `CONTENT_REPLACED` → the element configured in `$texy->nontextParagraph` (default `div`) is used instead of `<p>` (e.g. a paragraph holding a single image); contains only markup marks or whitespace → transparent element (unless a modifier forces the paragraph).
- **Autolinks after markup.** The `link/url` and `link/email` patterns allow `\x17` as the preceding character in their lookbehind, so a URL is recognized even immediately after inline markup, not only after whitespace (`src/Texy/Modules/LinkModule.php`).
- **Long words.** `LongWordsModule` defines a "word" as a run of characters excluding whitespace and `\x14`, `\x15`, `\x16` – but *including* `\x17`. Inline markup placeholders therefore count as part of the word, so a word split by `<strong>` tags is still hyphenated as one word, while block/textual/replaced content terminates a word.
- **Typography.** `TypographyModule`'s rules are written to be mark-aware (`src/Texy/Modules/TypographyModule.php`): they skip over `[\x17-\x1F]*` when pairing numbers with units or one-letter prepositions with the following word, they remove spurious spaces between adjacent markup placeholders ("intermarkup space"), and the "no lone short word at the end of a paragraph" rule (`(?<=.{50})\s++(?=…\S{1,6}…$)` → non-breaking space) tolerates surrounding marks. Since block content (`CONTENT_BLOCK`) never reaches post-line handlers at all (the string is split on the `\x14` marks first), typography naturally never touches code blocks.

When returning a raw HTML string (not an `HtmlElement`) from a handler, you must protect it yourself:

```php
return $texy->protect($html, Texy\Texy::CONTENT_BLOCK);
```

Unprotected strings are HTML-escaped in the output.

### Other internal control characters

Besides `\x14`–`\x1F`, Texy reserves a few more control bytes for internal tricks:

- `\x01`–`\x04` – frozen whitespace in attribute values (`Helpers::freezeSpaces()`; space, tab, `\r`, `\n`). `HtmlOutputModule` freezes whitespace the same way while reformatting, for the content of `$preserveSpaces` elements (`textarea`, `pre`, `script`, `code`, `samp`, `kbd`) and for HTML comments – otherwise line wrapping would break a long line inside an attribute or preformatted text.
- `\x13` – temporary mask for an escaped pipe in tables: `TableModule` replaces `\|` (and pipes inside `[…]` brackets, so wikilinks work in cells) with `\x13` before splitting a row on `|`, and restores it in each cell afterwards.
- `\r` – inside `ParagraphModule`, a hard line break marker (later replaced by a protected `<br>`).

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
2. The string is split by the `CONTENT_BLOCK` mark and every *textual* segment (even-indexed, i.e. outside protected blocks) is run through the registered **post-line handlers** in order: `typography` (`TypographyModule::postLine()` – quotes, dashes, non-breaking spaces, symbols) and `longwords` (`LongWordsModule::postLine()` – inserting `&shy;` soft hyphens). Each handler is skipped if disabled in `$allowed`.
3. `<`, `>`, `&` are HTML-escaped (protected content is immune – it is still masked at this point).
4. `unprotect()` replaces all protection marks with the stored HTML.
5. The `postProcess` event fires; `HtmlOutputModule` well-forms and reformats the HTML here (auto-closing tags, fixing invalid nesting per DTD, indentation, line wrapping).
6. Frozen attribute spaces are restored (`Helpers::unfreezeSpaces()`).

`Texy::processTypo()` is a shortcut that runs only steps of typographic correction (and long words, if enabled) on plain text, without any Texy parsing.
