# Rendering – the Output Layer

Everything under `src/Texy/Output/` turns a finished AST into a string. The layer is split between a **shared dispatch base** and **format-specific generators**; the HTML side additionally owns well-forming and formatting.

```
Output/
├── NodeRenderer            shared dispatch: node class → handler, chainable
├── UrlPolicy               URL scheme filtering + www. normalization (shared security)
├── Html/
│   ├── Generator           coordinator + per-node renderers + presentation config
│   ├── Element, Raw        the rendered tree the renderers build
│   ├── ElementDecorator    Modifier → attributes/classes/styles, filtered by whitelists
│   ├── Sanitizer           HTML passthrough security policy
│   ├── Writer              walks the rendered tree into the well-forming engine
│   ├── WellFormer          call-driven well-forming + indentation + line wrapping
│   └── Formatter           formatting config facade + legacy format(string) API
└── Markdown/
    ├── Generator           GFM output; needs no Texy instance
    └── Helpers
```

## NodeRenderer – dispatch and extensibility

`NodeRenderer` keeps a map `class-string<Node> => handler`. Generators seed it with their default renderers in the constructor; `registerHandler()` lets users override rendering per node class. The handler chain works last-registered-first: a custom handler receives `($node, $generator, $previous)` and either returns a result or returns `null` / calls `$previous($node, $generator)` to delegate (see [custom-handlers.md](custom-handlers.md)).

## HTML Generator

`Html\Renderer::render(DocumentNode)` is a **pure function of the AST**: repeated calls give identical output and never mutate nodes or configuration. Renderers that need to strip parts of a node's modifier (e.g. moving an image's alignment onto the figure wrapper) clone the node/modifier first. Per-document state (`{{texy: nofollow}}` from `DocumentNode::$meta`, the `typographed` flag) is fixed at the Renderer's construction - a fresh instance is created for every render.

The Generator is also the home of all **presentation configuration** – properties that only change the HTML output's look: `$imageRoot`, `$linkRoot`, `$linkNoFollow`, `$obfuscateEmail`, `$shortenUrls`, `$nontextParagraph`, `$phraseTags`, `$alignClasses`, `$emoticonClass`, figure/HR classes, and the `$allowedTags` whitelist. (Modules keep deprecated `__get`/`__set` bridges for the old property locations.)

Renderers return an **`Element` | `Raw` | `string`** tree:

- **`Element`** – a tag with `$attrs` and `$children`. `name === null` means a transparent wrapper (renders only children). Void-ness is derived from `Element::$emptyElements`.
- **`string`** – plain display text; escaped later by the well-forming engine.
- **`Raw`** – a piece of ready-made HTML that bypasses text escaping and is tokenized as-is (generated `<br>`, obfuscated e-mail, escaped code content). The typed successor of protection marks.

`Element::formatAttrs()` escapes attribute values and *freezes* their whitespace (`\x01`–`\x04`), so line wrapping can break between attributes but never inside a value; `WellFormer::finish()` unfreezes at the very end.

Two Generator helpers matter for security and structure:

- **`ElementDecorator`** applies a `Modifier` to an `Element`: attributes filtered by `$allowedTags`, classes/ID by `$texy->allowedClasses`, styles by `$texy->allowedStyles`, alignment via `$alignClasses` or inline styles. All modifier-driven decoration funnels through here (`Generator::decorateElement()`).
- **`Sanitizer`** implements the passthrough policy: the `$allowedTags` whitelist, per-tag validation (`<a>` needs `href`/`name`/`id` and a safe scheme, `<img>` needs a safe `src` – dangerous URLs are dropped entirely), and reconstruction of rejected tags as visible text. It is used both by the transform-phase `HtmlSanitizePass` (the authoritative decision) and by the renderer as defense in depth.

## HTML passthrough transform passes

Raw HTML in the input is parsed as individual `HtmlTagNode`s / `HtmlCommentNode`s. Two passes in the transform phase (invoked from `HtmlModule::processPassthrough()`) give them structure and truth:

1. **`HtmlPairingPass`** – stack-based pairing of open/close tags into `HtmlElementNode` subtrees, per content container, tolerant of crossing: what cannot be paired stays as standalone `HtmlTagNode`. The original closing tag is preserved for faithful reconstruction.
2. **`HtmlSanitizePass`** – evaluates `Sanitizer::isTagAcceptable()` over the paired tree; **rejected tags become `TextNode`s** containing the reconstructed tag source. After this pass the AST tells the truth: escaped tags are visible text (typography applies to them; Markdown escapes them), allowed tags are markup. This is also why safe mode automatically applies to every output format.

## Well-forming and formatting

Elements built from Texy syntax are correct by construction; invalid structure comes only from HTML passthrough (unpaired tags, illegal nesting). Fixing it is a render-time concern:

- **`Writer::finalize()`** walks the rendered `Element`/`Raw`/string tree and feeds it as `startTag()`/`endTag()`/`text()`/`raw()` calls into a fresh **`WellFormer`**.
- **`WellFormer`** is the stack automaton that enforces pairing and the content model while producing the output string: auto-closes optional-end and crossed inline tags, re-opens inline tags crossed by a close, suppresses tags not allowed in context (their children survive and validate against the parent's model), drops stray closing tags, treats unknown tags (custom elements) as transparent and always allowed. It also does the formatting: whitespace shrinking outside `preserveSpaces` contexts, block indentation, and line wrapping. `Raw` islands are tokenized by the same tag grammar, so handler-supplied HTML strings take part in well-forming.
- **`Formatter`** carries the formatting configuration (`$indent`, `$baseIndent`, `$lineWrap`, `$preserveSpaces`) – exposed as `$texy->htmlOutput->formatter` – plus the legacy public `format(string): string`, which tokenizes a ready HTML string through the same engine.

The content model lives in **`Schema`**, the declarative per-element vocabulary: explicit child lists for tables/lists, transparent elements (`a`, `ins`, `del`, `figure`…), text-only (`script`, `style`, `textarea`), phrasing-only contexts (`p`, headings, inline elements), flow content elsewhere. Deep prohibitions block `<a>`-in-`<a>`, `<button>`, `<form>` nesting at any depth. `Schema::inlineElements()` doubles as the phrasing catalogue (value `1` = replaced element), used by paragraph analysis and the typography pass alike.

## Markdown Generator

`Markdown\Generator` renders GFM from the same AST and depends only on an optional `UrlPolicy` – no `Texy` instance. Known lossy spots fall back to HTML (`<abbr>` for annotations, `<dl>` for definition lists). Because sanitization already happened in the transform phase, safe mode applies to Markdown output too.

## Deprecated: protection marks

`Generator::protect(string $html, string $contentType): string` exists only as a **backward-compatibility bridge for custom render handlers** that return a raw HTML string instead of an `Element`/`Raw`. It stores the string and returns a control-byte placeholder (`\x14`–`\x1F`) that the `Writer` decodes back into a raw island during the tree walk. New code should return `Element` or `Raw` objects; the mark mechanism (and the `Content*` constants) will eventually be removed.
