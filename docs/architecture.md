# Architecture and Principles

Texy converts text written in its own markup language to HTML. Unlike simple converters that process text linearly with a series of replacements, Texy parses the input into an **AST** (abstract syntax tree) and generates output from it.

Processing runs in four phases:

```
normalize → parse (modules) → transform (afterParse passes) → render (Output\*)
```

1. **Normalize** – input normalization: BOM removal, line endings, control-character stripping.
2. **Parse** – recognition of syntaxes using regular expressions; modules build the tree of `Texy\Nodes\*` objects.
3. **Transform** – AST-to-AST passes: reference resolution, heading balancing, HTML passthrough pairing and sanitization, typography. The transform phase **may mutate the tree**; after it, the document is semantically complete.
4. **Render** – an output generator converts the AST to a string. The render phase **must not mutate the tree or configuration**.

Two invariants govern the whole design:

- **Render is a pure function `AST → string`.** Rendering the same document twice gives identical output and leaves the tree untouched (enforced by `tests/Texy/renderer-purity.phpt`). Renderers that need to adjust a node's modifier work on clones.
- **The AST is format-neutral and semantically complete.** After the transform phase the tree contains everything any generator needs, without reaching back into the parser or modules. The placement test for configuration: *"does it change the document's content regardless of output format?"* → it belongs to a module / transform pass; *"does it only change the look of one output format?"* → it belongs to the generator. This is why e.g. `EmoticonModule::$icons` (syntax → character mapping) lives on the module, while `$emoticonClass` (CSS class) lives on the HTML generator. Neutrality holds for extensions too: a node class only one generator knows degrades to its content in the others (`NodeRenderer::renderUnknown()`), so a custom syntax never makes the document renderable in a single format only.

All classes live in the `Texy` namespace; modules in `Texy\Modules`, AST nodes in `Texy\Nodes`, output generators in `Texy\Output`.

## Key components

**The `Texy` class** (`src/Texy.php`, `src/Texy/Texy.php`) is the orchestrator and configuration facade. It owns the modules, the event handlers, and the output generator (`$texy->htmlOutput`), and drives the phases in `process()` / `parse()`.

**The `Engine`** (`src/Texy/Engine.php`) is a minimal parsing core: it stores registered line/block patterns and creates parsers filtered by the `$allowed` array. It contains no security or lifecycle logic – that stays in `Texy`.

**[Modules](modules.md)** encapsulate one area of the markup language each: they register patterns, turn matches into AST nodes, and register transform passes.

**[Parsers](parsing.md)** – `BlockParser` for multi-line block constructs, `InlineParser` for inline syntaxes. Both produce `Nodes\ContentNode` containers; recursion goes through `ParseContext`.

**The AST** – node classes in `src/Texy/Nodes/`, all extending `Texy\Node`. Two abstract families: `BlockNode` (paragraphs, headings, lists, tables…) and `InlineNode` (text, phrases, links, images…). Child lists live in `ContentNode` containers. `Texy\NodeTraverser` provides visitor-style traversal with enter/leave callbacks and node replacement/removal.

**[Output generators](rendering.md)** – `Output\Html\Renderer` and `Output\Text\Renderer`, both extending `Output\NodeRenderer` (a per-node-class dispatch with chainable custom handlers). The HTML renderer is created per render by the `Output\Html\Config` configuration facade; the text renderer needs no `Texy` instance at all – the test of the AST's semantic completeness.

## Processing flow

`Texy::process($text)` is `renderHtml(parse($text))`; `parse()` itself begins with normalization. In detail:

1. **Normalize** (static `Texy::normalize()`, applied by `parse()` idempotently): removes the leading BOM, normalizes line endings, strips control characters (this is why control bytes can never reach patterns from input), right-trims lines and drops blank edge lines. Soft hyphens are a legitimate character and pass through. Tabs are preserved; the syntax treats a tab as plain whitespace (indentation is compared literally or per character, never in visual columns).

2. **Module initialization.** Each module's `beforeParse(&$text)` runs: modules register their line/block patterns here (on every parse – some patterns depend on runtime config such as `ListModule::$bullets`) and may preprocess the text.

3. **Parse.** The `Engine` creates parsers with patterns filtered by `$allowed` (consulted once here; changing `$allowed` mid-process has no effect). `BlockParser` walks the text and calls syntax handlers, which return `BlockNode`s; text between block matches goes to the *gap handler* – `ParagraphModule::parseText()`, which splits it into paragraphs and uses `InlineParser` for their content. The result is a `Nodes\DocumentNode`.

4. **Transform.** The `afterParse` event fires with the document; registered passes run in registration (module-construction) order:
   - `DirectiveModule::processDirectives()` – consumes `{{texy: …}}` directives into `DocumentNode::$meta` (e.g. `$meta['nofollow']`) and dispatches other directives to `DirectiveModule::addDirective()` handlers; running first, a handler may still change configuration the later passes depend on,
   - `HtmlModule::processPassthrough()` – pairs passthrough HTML tags into element subtrees and evaluates the tag whitelist (see [rendering.md](rendering.md#html-passthrough-transform-passes)),
   - `ImageModule` / `LinkReferenceModule` `resolveReferences()` – resolves `[ref]` and `[*ref*]` references against collected definitions,
   - `EmoticonModule::resolveEmoticons()` – resolves emoticon syntax to characters (`EmoticonNode::$resolved`),
   - `Passes\HeadingPass` – heading level balancing, `{toc: …}` hoisting and ID generation.

   Then `Texy` runs the **typography pass** (below) and marks the document `$meta['typographed']`.

   The order above is a contract, not an accident: `HtmlModule` must run before typography (rejected tags become visible text), `ImageModule` must resolve before `LinkDefinitionModule` (image references used as link targets), and `HeadingPass` generates IDs before consumer handlers read the headings. Consumer `afterParse` handlers are registered after module construction, so they always run **after** all built-in passes.

5. **Render.** a fresh `Output\Html\Renderer` (configured by `$texy->htmlOutput`) produces the final HTML string (see [rendering.md](rendering.md)). Any renderer can render the same document; `parse()` can equally be called alone and the AST rendered later or elsewhere – that is what `Texy::renderHtml()` does.

`process($text, singleLine: true)` is the single-line (inline-only) variant; `applyTypography()` applies only typography/hyphenation to plain text without parsing.

## Typography over the AST

Typography (locale-aware quotes, dashes, non-breaking spaces) and long-word hyphenation are **AST transformations**, not string post-processing, so every output format gets them. `Texy\TextRunPass` implements them:

- For every block-level inline container (paragraph, heading, table cell…) it builds a *text image*: `TextNode` contents joined with marker characters standing in for markup boundaries (`\x17`), replaced content like images or `<br>` (`\x16`), and opaque protected text like inline code or URLs (`\x15`, repeated to mirror the visible length so length-sensitive rules judge it fairly).
- The regex transformers (`TypographyModule::postLine()`, `HyphenationModule::postLine()`) run over the image. **The patterns treat the marker bytes as a transparent alphabet and never create or destroy them** – this invariant is what allows the result to be split back unambiguously into the original text nodes.
- Block-level markup inside the container splits the image into independently processed segments.
- `Modifier::$title` values are typographed as isolated strings (quotes must not pair across a title and the body text).

Whether the pass runs is controlled by `$allowed['typography']` and `$allowed['longwords']`.

## Source positions

Nodes and modifiers carry a `Range` (`$offset`, `$length` in bytes of the **normalized** source). The contract for analyzers:

- **The range is the only source of truth for a node's source span.** Never derive spans from content length: `TextNode::$content` holds decoded display text (entities resolved), so `strlen($content)` generally differs from `$range->length`.
- Ranges are exact after parsing and **best-effort after transforms** – a pass that merges or rewrites nodes is not required to keep them precise. Synthesized values (e.g. a modifier assembled from non-contiguous fragments) have a `null` range by design.
- Heading IDs are generated in the transform phase **before** typography, so they are stable regardless of `$allowed['typography']`. The displayed title (`HeadingNode::getTitle()`) is derived from the heading's content instead of copied, so it *is* typographed.

### Working with positions

There is no source map. Offsets refer to `Texy::normalize($text)` – the same normalization `parse()` applies internally (it is idempotent) – so a tool that reports positions or rewrites the source normalizes once and keeps working with that string:

```php
$src = Texy::normalize(file_get_contents($file));
$doc = $texy->parse($src);
// Range offsets are exact byte positions in $src
$edited = substr_replace($src, $replacement, $range->offset, $range->length);
file_put_contents($file, $edited);
```

Normalization is plain text hygiene – LF line endings, no control bytes or BOM, right-trimmed lines – so the recommended practice is to **store Texy files already normalized**. The file on disk is then byte-identical to the string the offsets refer to, and positions can be used against it directly.

**Windows / CRLF files.** Never map offsets onto the raw CRLF bytes of a file. Work on the normalized (LF) string in memory and convert line endings only when serializing:

```php
$src = Texy::normalize(file_get_contents($file));            // CRLF → LF, offsets valid in $src
// ... analyze or edit $src using Range offsets ...
file_put_contents($file, str_replace("\n", "\r\n", $src));   // keep CRLF on disk if required
```

Normalized text contains no `\r`, so the conversion is a lossless bijection: re-normalizing on the next load restores the byte-identical string and every position stays stable across the round trip. Line and column coordinates are the same in both forms; only byte offsets differ, by one per preceding line.

## Terminology

**Syntax** – a named syntactic construct, e.g. `phrase/strong` or `image`. Names are catalogued as constants on `Texy\Syntax` and used as keys in `Texy::$allowed`. Simple syntaxes have one-word names; related groups use a slash hierarchy (`phrase/*`, `block/*`).

**Pattern** – the regular expression that recognizes the syntax, registered via `Texy::addLineSyntax()` / `addBlockSyntax()` together with the syntax name and its parser. Registration defaults `$allowed[$name]` to `true` unless a module set it otherwise (e.g. `emoticon`, `phrase/ins` default to `false`).

**Syntax handler** – the closure the parser calls on a match: `function(ParseContext $context, array $matches, array $offsets, string $name): ?Node`. It returns an AST node, or `null` to refuse the match (the parser then gives other patterns a chance). Handlers parse nested content recursively through the `ParseContext`.

**Transform pass** – a callable registered with `Texy::addPass()`, receiving the `DocumentNode` after parsing. This is where the AST may be freely mutated, typically using `NodeTraverser`. All passes run, in registration order, and return values are ignored.

**Node renderer** – a callable registered on an output generator with `NodeRenderer::addRenderer()` that renders one node class, optionally delegating to the previous renderer in the chain. This is the render-phase extension point (see [custom-handlers.md](custom-handlers.md)).

Passes registered by modules always run before any the consumer adds, so a consumer cannot get in front of them. Where the order matters, call `parse()`, work on the AST, and then `renderHtml()`.
