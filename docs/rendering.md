# Rendering – the Output Layer

Everything under `src/Texy/Output/` turns a finished AST into a string. The layer is split between a **shared dispatch base** and **format-specific renderers**; the HTML side additionally owns well-forming and formatting, configured through a single options object.

Security policies are exposed on the `Texy` object: `Texy\UrlPolicy` (URL scheme filtering) and `Output\Html\Policy` as `$texy->htmlPolicy` (tag/class/style whitelists and passthrough tag validation), because the sanitize pass they drive changes the AST and therefore protects **every** output format.

```
Output/
├── NodeRenderer            shared dispatch: node class → handler, chainable
├── Html/
│   ├── Config              all HTML output options ($texy->htmlOutput)
│   ├── Element, Raw        the rendered tree the renderers build
│   ├── Renderer            per-render node renderers, entry point of the render phase
│   ├── ElementDecorator    Modifier → attributes/classes/styles, filtered by Policy
│   ├── ImageDimensions     file-system probe for image sizes (the only I/O)
│   ├── Policy              HTML security policy ($texy->htmlPolicy)
│   ├── Schema              declarative HTML vocabulary (content model, categories)
│   └── WellFormer          call-driven well-forming + indentation + line wrapping
├── Markdown/
│   ├── Renderer            GFM output; needs no Texy instance
│   └── Helpers
└── Text/
    └── Renderer            plain-text rendition (Texy::toText())
```

## NodeRenderer – dispatch and extensibility

`NodeRenderer` keeps a map `class-string<Node> => handler`. Renderers seed it with their default handlers in the constructor; `registerHandler()` lets users override rendering per node class. The handler chain works last-registered-first: a custom handler receives `($node, $renderer, $previous)` and either returns a result or returns `null` / calls `$previous($node, $renderer)` to delegate (see [custom-handlers.md](custom-handlers.md)). For HTML, custom handlers are registered on the config (`$texy->htmlOutput->registerHandler()`) and applied to every renderer built from it.

## HTML Config and Renderer

`Html\Config` (`$texy->htmlOutput`) is the **home of all presentation configuration** – properties that only change the HTML output's look: `$imageRoot`, `$linkRoot`, `$linkNoFollow`, `$obfuscateEmail`, `$shortenUrls`, `$nontextParagraph`, `$phraseTags`, `$alignClasses`, `$emoticonClass`, figure/HR classes, the formatting options (`$indent`, `$baseIndent`, `$lineWrap`, `$preserveSpaces`) and the registered custom handlers. It does nothing itself. (Modules keep deprecated `__get`/`__set` bridges for the old property locations.)

`Html\Renderer` is created for every render – `Texy::process()` builds one over the config and the parsed document – and its `renderNode()` is a **pure function of the AST**: repeated calls give identical output and never mutate nodes or configuration. Renderers that need to strip parts of a node's modifier (e.g. moving an image's alignment onto the figure wrapper) clone the node/modifier first. Per-document state (`{{texy: nofollow}}` from `DocumentNode::$meta`, the `typographed` flag) is fixed at the Renderer's construction. The only file-system touch, image dimension detection (`$imageFileRoot`), is isolated in `ImageDimensions` with a per-render cache.

Renderers return an **`Element` | `Raw` | `string`** tree:

- **`Element`** – a tag with `$attrs` and `$children`. `name === null` means a transparent wrapper (renders only children). Void-ness is derived from `Schema::voidElements()`.
- **`string`** – plain display text; escaped later by the well-forming engine.
- **`Raw`** – a piece of ready-made HTML that bypasses text escaping and is tokenized as-is (generated `<br>`, obfuscated e-mail, escaped code content).

`Element::formatAttrs()` escapes attribute values and *freezes* their whitespace (`\x01`–`\x04`), so line wrapping can break between attributes but never inside a value; `WellFormer::finish()` unfreezes at the very end.

Two helpers matter for security and structure:

- **`ElementDecorator`** applies a `Modifier` to an `Element`: attributes filtered by `$htmlPolicy->allowedTags`, classes/ID by `$htmlPolicy->allowedClasses`, styles by `$htmlPolicy->allowedStyles`, alignment via `$alignClasses` or inline styles. All modifier-driven decoration funnels through here (`Renderer::decorateElement()`).
- **`Policy`** implements the passthrough policy: the `$allowedTags` whitelist, per-tag validation (`<a>` needs `href`/`name`/`id` and a safe scheme, `<img>` needs a safe `src` – dangerous URLs are dropped entirely), and reconstruction of rejected tags as visible text. It is used both by the transform-phase `HtmlSanitizePass` (the authoritative decision) and by the renderer as defense in depth.

## HTML passthrough transform passes

Raw HTML in the input is parsed as individual `HtmlTagNode`s / `HtmlCommentNode`s. Two passes in the transform phase (invoked from `HtmlModule::processPassthrough()`) give them structure and truth:

1. **`HtmlPairingPass`** – stack-based pairing of open/close tags into `HtmlElementNode` subtrees, per content container, tolerant of crossing: what cannot be paired stays as standalone `HtmlTagNode`. The original closing tag is preserved for faithful reconstruction.
2. **`HtmlSanitizePass`** – evaluates `Policy::isTagAcceptable()` over the paired tree; **rejected tags become `TextNode`s** containing the reconstructed tag source. After this pass the AST tells the truth: escaped tags are visible text (typography applies to them; Markdown escapes them), allowed tags are markup. This is also why safe mode automatically applies to every output format.

## Well-forming and formatting

Elements built from Texy syntax are correct by construction; invalid structure comes only from HTML passthrough (unpaired tags, illegal nesting). Fixing it is a render-time concern:

- **`WellFormer::feed()`** consumes the rendered `Element`/`Raw`/string tree directly (tags, display text, raw HTML islands); `raw()` tokenizes handler-supplied HTML strings by the same tag grammar, so they take part in well-forming too.
- **`WellFormer`** is the stack automaton that enforces pairing and the content model while producing the output string: auto-closes optional-end and crossed inline tags, re-opens inline tags crossed by a close, suppresses tags not allowed in context (their children survive and validate against the parent's model), drops stray closing tags, treats unknown tags (custom elements) as transparent and always allowed. It also does the formatting: whitespace shrinking outside `preserveSpaces` contexts, block indentation, and line wrapping. Its configuration is read from the `Config` object it is constructed with.

The content model lives in **`Schema`**, the declarative per-element vocabulary: explicit child lists for tables/lists/select/media, transparent elements (`a`, `ins`, `del`, `figure`…), text-only (`script`, `style`, `textarea`), phrasing-only contexts (`p`, headings, inline elements), flow content elsewhere. Deep prohibitions block `<a>`-in-`<a>`, `<button>`, `<form>` nesting at any depth. `Schema::inlineElements()` doubles as the phrasing catalogue (value `1` = replaced element), used by paragraph analysis and the typography pass alike.

## Markdown and Text Renderers

`Markdown\Renderer` renders GFM from the same AST and depends only on an optional `UrlPolicy` – no `Texy` instance. Known lossy spots fall back to HTML (`<abbr>` for annotations, `<dl>` for definition lists, tables with cell spans). Because sanitization already happened in the transform phase, safe mode applies to Markdown output too.

`Text\Renderer` (behind `Texy::toText()`) renders a plain-text rendition: markup dropped, visible text and block structure kept, typography artifacts (non-breaking spaces, soft hyphens) normalized back.
