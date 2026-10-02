# Rendering – the Output Layer

Everything under `src/Texy/Output/` turns a finished AST into a string. The layer is split between a **shared dispatch base** and **format-specific renderers**; the HTML side additionally owns well-forming and formatting, configured through a single options object.

Security policies are exposed on the `Texy` object: `Texy\UrlPolicy` (URL scheme filtering) and `Texy\HtmlPolicy` as `$texy->htmlPolicy` (tag/class/style whitelists and passthrough tag validation), because the sanitize pass they drive changes the AST and therefore protects **every** output format.

```
Output/
├── NodeRenderer            shared dispatch: node class → handler, chainable
├── Html/
│   ├── Config              all HTML output options ($texy->htmlOutput)
│   ├── Element, Raw        the rendered tree the renderers build
│   ├── Renderer            node renderers, entry point of the render phase
│   ├── ElementDecorator    Modifier → attributes/classes/styles, filtered by HtmlPolicy
│   ├── ImageDimensions     file-system probe for image sizes (the only I/O)
│   ├── Schema              declarative HTML vocabulary (content model, categories)
│   └── WellFormer          call-driven well-forming + indentation + line wrapping
├── Text/
│   └── Renderer            plain-text rendition (Texy::toText())
└── Texy/
    └── Renderer            the syntax tree printed back as Texy source (Texy::renderTexy())
```

## NodeRenderer – dispatch and extensibility

`NodeRenderer` keeps a map `class-string<Node> => renderer`. Generators seed it with their built-in renderers in the constructor; `addRenderer()` lets users override rendering per node class. A class with no renderer of its own falls back to one registered for an ancestor, so a subclass of a built-in node renders without extra work. The chain works last-registered-first: a custom renderer receives `($node, $renderer, $previous)` and either returns a result or returns `null` / calls `$previous($node, $renderer)` to delegate (see [custom-handlers.md](custom-handlers.md)). For HTML, custom renderers are registered on the config (`$texy->htmlOutput->addRenderer()`) and applied to every renderer built from it.

## HTML Config and Renderer

`Html\Config` (`$texy->htmlOutput`) is the **home of all presentation configuration** – properties that only change the HTML output's look: `$imageRoot`, `$linkRoot`, `$linkNoFollow`, `$obfuscateEmail`, `$shortenUrls`, `$nontextParagraph`, `$phraseTags`, `$alignClasses`, `$emoticonClass`, figure/HR classes, the formatting options (`$indent`, `$baseIndent`, `$lineWrap`, `$preserveSpaces`) and the registered custom handlers. It does nothing itself. (Modules keep deprecated `__get`/`__set` bridges for the old property locations.)

`Html\Renderer` needs no live `Texy` instance: its constructor takes the `Config`, the two policies and an optional typography closure; it has no path to the parser, since the markup of `block/html`, `block/pre` and `block/texysource` is parsed into `CodeBlockNode::$content` in the parse phase. `Texy::createHtmlRenderer()` is the composition point that wires all of this from the instance's configuration, and `renderHtml()` is just `createHtmlRenderer()->render($document)`. Rendering is a **pure function of the AST**: repeated calls give identical output and never mutate nodes or configuration; renderers that need to strip parts of a node's modifier (e.g. moving an image's alignment onto the figure wrapper) clone the node/modifier first. Per-document facts (`{{texy: nofollow}}` and the `typographed` flag in `DocumentNode::$meta`) are read from the document at `render()` time, so one renderer instance serves any number of documents – it is a snapshot of *configuration* (the class/style whitelists are captured on first use per instance), not of any document. The only file-system touch, image dimension detection (`$imageFileRoot`), is isolated in `ImageDimensions` with a per-instance cache.

Renderers return an **`Element` | `Raw` | `string`** tree:

- **`Element`** – a tag with `$attrs` and `$children`. `name === null` means a transparent wrapper (renders only children). Void-ness is derived from `Schema::voidElements()`.
- **`string`** – plain display text; escaped later by the well-forming engine.
- **`Raw`** – a piece of ready-made HTML that bypasses text escaping and is tokenized as-is (generated `<br>`, obfuscated e-mail, escaped code content).

`Element::formatAttrs()` escapes attribute values and *freezes* their whitespace (`\x01`–`\x04`), so line wrapping can break between attributes but never inside a value; `WellFormer::finish()` unfreezes at the very end.

Two helpers matter for security and structure:

- **`ElementDecorator`** applies a `Modifier` to an `Element`: attributes filtered by `$htmlPolicy->allowedTags`, classes/ID by `$htmlPolicy->allowedClasses`, styles by `$htmlPolicy->allowedStyles`, alignment via `$alignClasses` or inline styles. All modifier-driven decoration funnels through here (`Renderer::decorateElement()`).
- **`HtmlPolicy`** implements the passthrough policy: the `$allowedTags` whitelist, per-tag validation (`<a>` needs `href`/`name`/`id` and a safe scheme, `<img>` needs a safe `src` – dangerous URLs are dropped entirely), and reconstruction of rejected tags as visible text. It is used both by the transform-phase `HtmlSanitizePass` (the authoritative decision) and by the renderer as defense in depth.

## HTML passthrough transform passes

Raw HTML in the input is parsed as individual `HtmlTagNode`s / `HtmlCommentNode`s. Two passes in the transform phase (invoked from `HtmlModule::processPassthrough()`) give them structure and truth:

1. **`HtmlPairingPass`** – stack-based pairing of open/close tags into `HtmlElementNode` subtrees, per content container, tolerant of crossing: what cannot be paired stays as standalone `HtmlTagNode`. The original closing tag is preserved for faithful reconstruction.
2. **`HtmlSanitizePass`** – evaluates `HtmlPolicy::isTagAcceptable()` over the paired tree; **rejected tags become `TextNode`s** containing the reconstructed tag source. After this pass the AST tells the truth: escaped tags are visible text (typography applies to them; other formats escape them), allowed tags are markup. This is also why safe mode automatically applies to every output format.

## Well-forming and formatting

Elements built from Texy syntax are correct by construction; invalid structure comes only from HTML passthrough (unpaired tags, illegal nesting). Fixing it is a render-time concern:

- **`WellFormer::feed()`** consumes the rendered `Element`/`Raw`/string tree directly (tags, display text, raw HTML islands); `raw()` tokenizes handler-supplied HTML strings by the same tag grammar, so they take part in well-forming too.
- **`WellFormer`** is the stack automaton that enforces pairing and the content model while producing the output string: auto-closes optional-end and crossed inline tags, re-opens inline tags crossed by a close, suppresses tags not allowed in context (their children survive and validate against the parent's model), drops stray closing tags, treats unknown tags (custom elements) as transparent and always allowed. It also does the formatting: whitespace shrinking outside `preserveSpaces` contexts, block indentation, and line wrapping. Its configuration is read from the `Config` object it is constructed with.

The content model lives in **`Schema`**, the declarative per-element vocabulary: explicit child lists for tables/lists/select/media, transparent elements (`a`, `ins`, `del`, `figure`…), text-only (`script`, `style`, `textarea`), phrasing-only contexts (`p`, headings, inline elements), flow content elsewhere. Deep prohibitions block `<a>`-in-`<a>`, `<button>`, `<form>` nesting at any depth. `Schema::inlineElements()` doubles as the phrasing catalogue (value `1` = replaced element), used by paragraph analysis and the typography pass alike.

## The Text Renderer

`Text\Renderer` renders a plain-text rendition: markup dropped, visible text and block structure kept, typography artifacts (non-breaking spaces, soft hyphens) normalized back. It needs no `Texy` instance at all – the test of the AST's semantic completeness. Because sanitization and typography already happened in the transform phase, safe mode and typographic corrections apply to its output as well.

The facade follows one grammar for HTML and plain text: `to*(string)` is the one-shot conversion, `render*(DocumentNode)` renders a document that `parse()` and `transform()` produced – `renderHtml()`/`process()`, `renderText()`/`toText()`. `renderTexy()` has no one-shot counterpart, since its input would already be Texy source.

## The Texy Renderer

`Texy\Renderer` prints a **syntax tree** back as Texy source (`Texy::renderTexy()`, or `renderNode()` for a single node). Its contract is the round trip: parsing the printed source gives the same tree up to positions, and printing that tree gives the same source again (`tests/Texy/render-texy.phpt`). The input is what `parse()` returns; a document after `transform()` has resolved references, typography in its text and sanitized HTML, so printing it does not reproduce the notation.

- A node is printed in the variant of its notation it records (`LinkNode::$syntax`, `ListItemNode::$bullet`, `HeadingNode::$marker`, the type of a phrase), anything else in one canonical form: a modifier as `.(title)[class #id]{style}<>`, item content indented by two spaces, a surrounded heading closed by its marker, a table separator of its own, a code block as `/--code` or as a fence where `/--` would change the code.
- **Text is printed as it was written.** A one-character `TextNode` whose range is longer than its text was an entity or `\*`, and is escaped again; a node without a range, built by a consumer, gets `&` and `<` escaped. Where text in its new place would read as syntax (a paragraph starting the way a block does, a dot opening a table cell, a colon that would make a definition list), its first character is written as an entity, which parses back into the same text.
- The notation dictates some placements: a paragraph's modifier goes to the end of its first line, where the parser looks for it; the first line of a list item cannot be followed by a hard break, so a space of the text before the break becomes a newline; a list is separated by two blank lines from a neighbour that would read as one more of its items.
- A node of an extension has no printed form: `renderUnknown()` throws and `addRenderer()` gives it one.

What cannot round-trip: a table cell continued over more lines than the rows it spans, a line starting with `/--` or `\--` inside an `html` or `text` block, a modifier title spanning a hard break, an empty `/--div`, the deprecated `[* | url *]` image syntax, and lines of a paragraph of raw HTML that read as a block once the paragraph starts at the beginning of a line.

## Rendering a document parsed elsewhere

The AST is not tied to the Texy parser. A document another parser produced – [texy/markdown](https://github.com/dg/texy-markdown) builds one from CommonMark – renders through these same generators, and a node type they do not know degrades to its content (`NodeRenderer::renderUnknown()`) or gets a handler of its own through `addRenderer()`.

One step is not the renderer's, though: typography is a transform over the AST, which `transform()` runs as its last step. A document built elsewhere gets it from `Texy::applyAstTypography($document)`, called before rendering.

Such a document also prints as Texy source, which converts it to Texy. The printer needs a printed form for the other parser's own nodes, and the variants of notation the nodes record (heading markers, bullets, link syntaxes) have to be dropped first, because they are the other language's and the printer would write them as Texy's. Text has to be escaped by the target's rules too: the printer escapes only what was escaped in a Texy source. `Markdown::toTexy()` in texy/markdown does all three.
