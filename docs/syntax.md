# Syntax Reference

Complete reference of the Texy markup language, **including syntaxes that are disabled by default**. The full list of syntax IDs, their default state, and how to switch them on or off is in the [Syntax overview](#syntax-overview) at the end.

## Paragraphs and line breaks

*(ParagraphModule)*

One or more consecutive lines form a paragraph; an empty line starts a new one. Texy joins the lines of a paragraph together ("line merging"), so soft-wrapped source text renders as one flowing block:

```texy
This is the first paragraph. It may span several lines
and Texy joins them into one continuous text.

After the blank line, a brand new paragraph starts.
```

Disable merging so that every line becomes its own paragraph:

```php
$texy->mergeLines = false;
```

A **forced line break** without a new paragraph: start the next line with a single space (poems, addresses). The break renders as `<br>`.

The paragraph wrapper is chosen by analyzing the parsed content ([parsing.md](parsing.md#paragraphmodule-the-gap-handler)): normal text gets `<p>`; a paragraph consisting only of replaced content (e.g. a single image) is wrapped in `$texy->htmlOutput->nontextParagraph` (default `div`); content containing a block-level element gets no wrapper at all.

Paragraphs accept [modifiers](modifiers.md) on a standalone line before the paragraph or at the end of its last line:

```texy
.[perex]
Introductory paragraph with the "perex" CSS class.

This paragraph is centered. .<>
```

## Inline formatting

*(PhraseModule – syntax IDs `phrase/*`)*

| Markup | Output | Syntax ID | Default |
|---|---|---|---|
| `**bold**` | `<strong>` | `phrase/strong` | on |
| `//italic//` | `<em>` | `phrase/em` | on |
| `*italic*` | `<em>` | `phrase/em-alt`, `phrase/em-alt2` | on |
| `***bold italic***` | `<strong><em>` | `phrase/strong+em` | on |
| `` `code` `` | `<code>` | `phrase/code` | on |
| `x^2`, `O_2` | `<sup>`, `<sub>` | `phrase/sup-alt`, `phrase/sub-alt` | on |
| `x^^2^^`, `O__2__` | `<sup>`, `<sub>` | `phrase/sup`, `phrase/sub` | *off* |
| `++inserted++` | `<ins>` | `phrase/ins` | *off* |
| `--deleted--` | `<del>` | `phrase/del` | *off* |
| `>>quotation<<` | `<q>` | `phrase/quote` | on |
| `"span .{color:blue}"` | `<span>` (or `<a>` with a link) | `phrase/span` | on |
| `~span .{color:blue}~` | `<span>` (or `<a>` with a link) | `phrase/span-alt` | on |
| `"et al."((and others))` | `<abbr title="…">` | `phrase/acronym` | on |
| `NBA((National Basketball Association))` | `<abbr title="…">` | `phrase/acronym-alt` | on |
| `''literal **text**''` | escaped text, no processing | `phrase/notexy` | on |
| `\*` | literal `*` | `phrase/escaped-asterix` | on |

Rules:

- No spaces are allowed immediately inside the delimiters: `** not bold **` does not match.
- The generated tag for each phrase is configurable through `$texy->htmlOutput->phraseTags` (e.g. map `phrase/strong` to `b`).
- Any phrase accepts a [modifier](modifiers.md) just before the closing delimiter: `**strong and green .{color:green}**`.
- Most phrases can become links by appending `:URL` (see below); this requires `$texy->phraseModule->linksAllowed = true` (default).

## Links

*(LinkReferenceModule + PhraseModule)*

The primary syntax attaches a URL to a phrase with a colon:

```texy
Visit "Nette Framework":https://nette.org or "write to us":info@example.com.
"Article":[https://example.com/news?id=1&x=y]   // brackets delimit tricky URLs
```

*Syntax IDs `phrase/span` (quotes) etc. + LinkReferenceModule for URL handling.*

Alternative link syntaxes *(PhraseModule)*:

```texy
[Link text](https://example.com)      // Markdown style     – phrase/markdown
[Link text | https://example.com]     // MediaWiki style    – phrase/wikilink
word:[url-or-reference]               // single-word link   – phrase/quicklink
```

**Reference links** *(definitions `link/definition`, bare references `link/reference`, LinkReferenceModule)* keep long URLs out of the text:

```texy
See the "documentation":[doc] and use [Nette].

[doc]: https://texy.nette.org
[Nette]: https://nette.org
```

References can also be added programmatically with `$texy->linkModule->addDefinition()`. The bare `[ref]` form is **opt-in** (`$texy->allowed[Syntax::LinkReference] = true`): it produces a link whose target is resolved against the definitions, and the written reference name stays available in `LinkNode::$ref`, so a consumer transform pass can resolve wiki-style targets itself.

**Automatic links** *(`link/url`, `link/email`, AutolinkModule)*: bare URLs starting with `http://`, `https://`, `www.`, `ftp://` and bare e-mail addresses become links automatically. Addresses starting with `www.` get the `http://` scheme prepended and e-mails get `mailto:`. Displayed URLs are shortened when `$texy->htmlOutput->shortenUrls` is on; e-mails are obfuscated against bots when `$texy->htmlOutput->obfuscateEmail` is on. A URL rejected by `$texy->urlPolicy` produces no link.

A `.[nofollow]` class on a link converts to `rel="nofollow"`.

## Images

*(`image`, definitions `image/definition` – ImageModule)*

```texy
[* image.jpg *]
[* image.jpg <] text floats around a left-aligned image
[* image.jpg >] right-aligned
[* photo.jpg .(alt text description)[css-class] *]
```

Dimensions – detected automatically for local files when `$texy->htmlOutput->imageFileRoot` is set, or specified manually:

```texy
[* img.jpg 150x100 *]    // exact size
[* img.jpg 150 *]        // width, height keeps ratio
[* img.jpg ?x100 *]      // height, width keeps ratio
```

Clickable images – append `:URL`; the `::` shortcut links the image to its own URL (the full-size file itself):

```texy
[* thumb.jpg *]:big.jpg
[* logo.png *]:https://nette.org
[* thumb.jpg *]::
```

References:

```texy
Our logo [* company-logo *] symbolizes our vision.

[* company-logo *]: /images/logo.svg 200x50 .(Company logo)
```

## Figures (image with caption)

*(`figure` – ImageModule)*

An image followed by ` *** ` and caption text on the same block:

```texy
[* photo.jpg *] *** The caption. It may contain **formatting**.
```

Renders `<div class="figure">` by default; set `$texy->htmlOutput->figureTagName = 'figure'` for semantic `<figure>`/`<figcaption>`. The caption is optional – an image alone on its own block line also becomes a figure (without a `<figcaption>`).

## Headings

*(`heading/underlined`, `heading/surrounded` – HeadingModule)*

**Underlined** – the line below the text consists of at least three `#`, `*`, `=`, or `-` characters (importance in this order, highest first):

```texy
The most important heading
##########################

Second-level heading
********************
```

**Surrounded** – the text is prefixed (and optionally suffixed) by 2–7 `#` or `=` characters; by default more characters mean a *higher* heading (`$moreMeansHigher`, applies to `Dynamic` balancing):

```texy
==== Top-level heading

=== Lower level
```

Final `<h1>`–`<h6>` levels are computed in the transform phase according to `$texy->headingModule->balancing` (`Dynamic` auto-levels relative to `$top`; `Fixed` maps characters via `$levels`). Optional automatic `id` attributes: `$generateID`, `$idPrefix`; the modifier `.{toc: Custom title}` on a heading overrides the heading's `$tocTitle` and hence the generated ID slug (it is transport syntax and never reaches the output). Headings are read from the document itself: `Texy\Nodes\HeadingNode::collectFrom($document)` returns them in document order, each carrying `$level`, `$tocTitle` and `$modifier?->id`; the document title is `collectFrom($document)[0]?->tocTitle`.

## Lists

*(`list`, `list/definition` – ListModule)*

**Bulleted** – lines starting with `-`, `*`, or `+` followed by a space:

```texy
- Milk
* Eggs
+ Butter
```

**Numbered** – `1.`, `1)`, `a)`, `A)`, `I.`, `I)` styles; actual numbers do not matter, Texy renumbers automatically. Roman/alpha styles set `list-style-type`.

**Nesting** – indent the nested item by at least two spaces or a tab:

```texy
1) First chapter
   a) Subchapter
      - point
2) Second chapter
```

**Definition lists** *(`list/definition`)*:

```texy
HTML:
  - Markup language for web pages.
  - Stands for HyperText Markup Language.
```

Modifiers: before the list for the whole `<ul>`/`<ol>`, at the end of an item line for that item.

## Tables

*(`table` – TableModule)*

Rows start with `|`, cells are separated by `|`:

```texy
| John | Smith | Prague
| Eva  | Nova  | Brno
```

- **Header row(s):** separate the head with a dashed line `|---…`.
- **Row headers:** start the cell with `|*` to produce `<th>`.
- **Colspan:** end the cell with `||` (empty following cell merges left).
- **Rowspan:** a cell containing `^` merges with the cell above.
- **Literal pipe:** write `\|` to put a `|` character inside a cell; pipes inside `[…]` (wikilinks) need no escaping.
- **Modifiers:** before the table (whole table, uses `ModifierHVAlign` – vertical alignment allowed), at the end of a row (row), at the start of the first cell of a column (whole column), inside a cell (cell).

```texy
.[data-table]
| Name       | .> Price | In stock
|------------|----------|---------
| Product A  | $12      | yes
```

## Block quotations

*(`blockquote` – BlockQuoteModule)*

Lines starting with `>`; blank `>` lines separate paragraphs inside the quote; `> >` nests:

```texy
> First paragraph of the quotation.
>
> > Nested quotation.
>
> Back in the outer quotation.
```

Continuation lines may also use `>:` instead of `> ` as the prefix.

## Horizontal rules

*(`horizline` – HorizontalRuleModule)*

Three or more `-` or `*` on a separate line (a blank line must precede, otherwise it would be an underlined heading):

```texy
***
```

Each character type may get its own CSS class via `$texy->htmlOutput->horizontalRuleClasses`.

## Fenced blocks

*(`blocks` + `block/*` subtypes – BlockModule)*

A block starts with `/--type` and ends with `\--`. Blocks of type `div` may nest. The optional parameter after the type (e.g. language) is stored on the node.

```texy
/--code php
echo 'Hello';
\--

/--div .[important]
Content processed as Texy, wrapped in <div class="important">.
\--

/--html
<em>processed as HTML, Texy markup ignored</em>
\--

/--text
Displayed literally, both Texy and HTML markup escaped.
\--
```

| Subtype | Output |
|---|---|
| `block/code` | `<pre><code class="language-xxx">` with escaped content; the class mask is `$htmlOutput->codeLanguageClass`, and the language is stored on `CodeBlockNode` for custom renderer handlers (syntax highlighting) |
| `block/pre` | `<pre>`; content is escaped, but HTML tags written inside are recognized and validated |
| `block/html` | content parsed as HTML (tags validated against `$allowedTags`), Texy markup off, no wrapper element |
| `block/text` | content escaped literally, line breaks become `<br>` |
| `block/texy` | content parsed as regular Texy – restores full processing, e.g. inside a `div` block; handled before the `$allowed` check, so it cannot be disabled |
| `block/texysource` | content processed by Texy and the *resulting HTML source* displayed in a code block (parameter `line` switches to inline parsing) |
| `block/comment` | content discarded entirely |
| `block/div` | `<div>`, content parsed as Texy blocks; may nest |
| `block/default` | plain `/--` without a type; escaped `<pre>`, the parameter (if any) becomes a language CSS class |

Each subtype (except `block/texy`) is individually switchable in `$allowed`; the whole feature via `blocks`.

**Markdown-style fenced code blocks** *(`block/fenced`)* are also supported: three or more backticks, optionally followed by a language (the first word of the info string), closed by a fence of at least the same length. Content is verbatim; a longer fence can embed shorter backtick runs. This closes the round-trip with the Markdown output generator.

````texy
```php
echo 'Hello';
```
````

## Direct HTML

*(`html/tag`, `html/comment` – HtmlModule)*

HTML tags and comments may be mixed directly into the text. Tags are validated against `$texy->htmlOutput->allowedTags`; disallowed tags become visible text, invalid nesting is fixed by the output well-forming. HTML comments are kept when `$texy->htmlOutput->passHtmlComments` is on.

```texy
This is **Texy bold** and this is <strong>HTML bold</strong>.
```

## Scripts / macros

*(`script` – DirectiveModule)*

```texy
{{command: arg1, arg2}}
```

`{{texy: …}}` directives are consumed into document options (`DocumentNode::$meta`); any other directive renders as its literal text unless a custom renderer handler for `DirectiveNode` processes it (see [custom-handlers.md](custom-handlers.md)).

## Emoticons

*(`emoticon` – EmoticonModule; **disabled by default**)*

```php
$texy->allowed['emoticon'] = true;
```

Recognized emoticons and replacements come from `$texy->emoticonModule->icons` (default: `:-)` 🙂, `:-(` ☹, `;-)` 😉, `:-D` 😁, `8-O` 😮, `8-)` 😄, `:-?` 😕, `:-x` 😶, `:-P` 😛, `:-|` 😐). Replacements are rendered as text (wrapped in a `<span>` when `$texy->htmlOutput->emoticonClass` is set); image-file emoticons are no longer supported.

## Typography

*(`typography` – TypographyModule; post-processing, no markup of its own)*

Applied automatically to all running text (never inside code or other protected content):

- straight quotes → locale-specific typographic quotes (`$texy->typographyModule->locale`),
- `...` → …, `--` → – (en dash), `---` → — (em dash), `10-15` → 10–15,
- `(c)` © , `(r)` ® , `(tm)` ™ , `+-` ± , `10 x 5` → 10 × 5,
- `->` → , `<-` ← , `<->` ↔ (surrounded by spaces),
- non-breaking spaces after one-letter prepositions, inside phone numbers, between numbers and units.

## Long-word hyphenation

*(`longwords` – HyphenationModule; post-processing)*

Inserts invisible soft hyphens (`&shy;`) into words longer than `$texy->longWordsModule->wordLimit` (default 20) so browsers can break them. Uses Czech-oriented syllable heuristics. Enabled by default; disable with `$texy->allowed['longwords'] = false`.

## Modifiers

Not a syntax on its own but usable with almost every construct above:

```texy
.(title)[class #id]{style: value; attr: value}<>^
```

See [modifiers.md](modifiers.md) for the full description and the configuration reference (user manual) for the `$htmlPolicy->allowedClasses` / `$htmlPolicy->allowedStyles` restrictions.

---

## Syntax overview

Every syntax lists its ID (the key in `$texy->allowed`), its default state, and the module that implements it. Defaults were verified against the module constructors in `src/Texy/Modules/`.

### Inline (line) syntaxes

| Syntax ID | Default | Markup | Module |
|---|---|---|---|
| `script` | ✅ on | `{{command: args}}` | DirectiveModule |
| `html/tag` | ✅ on | `<strong>`, `<div class=…>` | HtmlModule |
| `html/comment` | ✅ on | `<!-- comment -->` | HtmlModule |
| `image` | ✅ on | `[* image.jpg *]` | ImageModule |
| `phrase/strong+em` | ✅ on | `***text***` | PhraseModule |
| `phrase/strong` | ✅ on | `**text**` | PhraseModule |
| `phrase/em` | ✅ on | `//text//` | PhraseModule |
| `phrase/em-alt` | ✅ on | `*text*` | PhraseModule |
| `phrase/em-alt2` | ✅ on | `*text*` (at punctuation boundaries) | PhraseModule |
| `phrase/ins` | ❌ off | `++inserted++` | PhraseModule |
| `phrase/del` | ❌ off | `--deleted--` | PhraseModule |
| `phrase/sup` | ❌ off | `^^superscript^^` | PhraseModule |
| `phrase/sup-alt` | ✅ on | `x^2` | PhraseModule |
| `phrase/sub` | ❌ off | `__subscript__` | PhraseModule |
| `phrase/sub-alt` | ✅ on | `O_2` | PhraseModule |
| `phrase/span` | ✅ on | `"text .{style}"` or `"text":url` | PhraseModule |
| `phrase/span-alt` | ✅ on | `~text .{style}~` | PhraseModule |
| `phrase/quote` | ✅ on | `>>quoted<<` | PhraseModule |
| `phrase/acronym` | ✅ on | `"et al."((and others))` | PhraseModule |
| `phrase/acronym-alt` | ✅ on | `NBA((National Basketball Association))` | PhraseModule |
| `phrase/notexy` | ✅ on | `''no **markup** here''` | PhraseModule |
| `phrase/code` | ✅ on | `` `inline code` `` | PhraseModule |
| `phrase/quicklink` | ✅ on | `word:[url]` | PhraseModule |
| `phrase/wikilink` | ✅ on | `[text | url]` | PhraseModule |
| `phrase/markdown` | ✅ on | `[text](url)` | PhraseModule |
| `phrase/escaped-asterix` | ✅ on | `\*` (literal asterisk) | PhraseModule |
| `link/reference` | ❌ off | bare `[ref]` | LinkReferenceModule |
| `link/url` | ✅ on | autodetected `https://…`, `www.…`, `ftp://…` | AutolinkModule |
| `link/email` | ✅ on | autodetected e-mail address | AutolinkModule |
| `emoticon` | ❌ off | `:-)`, `:-D`, `;-)` … | EmoticonModule |

### Block syntaxes

| Syntax ID | Default | Markup | Module |
|---|---|---|---|
| `blocks` | ✅ on | `/-- type … \--` fenced blocks | BlockModule |
| `block/code` | ✅ on | subtype `/--code lang` | BlockModule |
| `block/html` | ✅ on | subtype `/--html` | BlockModule |
| `block/text` | ✅ on | subtype `/--text` | BlockModule |
| `block/texysource` | ✅ on | subtype `/--texysource` | BlockModule |
| `block/comment` | ✅ on | subtype `/--comment` | BlockModule |
| `block/div` | ✅ on | subtype `/--div` | BlockModule |
| `block/fenced` | ✅ on | ```` ```language ```` | BlockModule |
| `block/pre` | ✅ on | subtype `/--pre` | BlockModule |
| `block/default` | ✅ on | plain `/--` block | BlockModule |
| `figure` | ✅ on | `[* img.jpg *] *** caption` | ImageModule |
| `horizline` | ✅ on | `---` or `***` on its own line | HorizontalRuleModule |
| `blockquote` | ✅ on | `> quoted text` | BlockQuoteModule |
| `table` | ✅ on | `| cell | cell |` | TableModule |
| `heading/underlined` | ✅ on | heading underlined by `###`/`***`/`===`/`---` | HeadingModule |
| `heading/surrounded` | ✅ on | `### Heading` | HeadingModule |
| `list` | ✅ on | `- item`, `1) item` … | ListModule |
| `list/definition` | ✅ on | `Term:` + indented `- description` | ListModule |

### Pre-processing and post-processing syntaxes

| Syntax ID | Default | What it does | Module |
|---|---|---|---|
| `link/definition` | ✅ on | `[name]: url` reference definitions, resolved in the transform phase (a label or modifier in the definition is deprecated) | LinkReferenceModule |
| `image/definition` | ✅ on | `[*name*]: url .(alt)` image reference definitions, resolved in the transform phase | ImageModule |
| `typography` | ✅ on | typographic corrections (quotes, dashes, nbsp, symbols) applied by the AST typography pass | TypographyModule |
| `longwords` | ✅ on | insertion of soft hyphens into long words, same pass | HyphenationModule |

Paragraphs themselves have no syntax ID – text between recognized blocks is always handled by **ParagraphModule** (configurable via `$texy->mergeLines`).

Enable or disable any syntax by its ID:

```php
$texy->allowed['phrase/ins'] = true;
$texy->allowed['image'] = false;
```
