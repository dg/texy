# Architecture and Principles

Texy converts text written in its own markup language to HTML. Unlike simple converters that process text linearly with a series of replacements, Texy parses the input into an **AST** (abstract syntax tree) and generates output from it.

Processing runs in four phases:

```
normalize → parse (modules) → transform (passes) → render (Output\*)
```

1. **Normalize** – input normalization: BOM removal, line endings, control-character stripping.
2. **Parse** – recognition of syntaxes using regular expressions; modules build the tree of `Texy\Nodes\*` objects. The result is the *syntax tree*, see [Two states of the tree](#two-states-of-the-tree).
3. **Transform** – AST-to-AST passes: reference resolution, heading balancing, HTML passthrough pairing and sanitization, typography. The transform phase **may mutate the tree**; after it, the document is semantically complete.
4. **Render** – an output generator converts the AST to a string. The render phase **must not mutate the tree or configuration**.

Two invariants govern the whole design:

- **Render is a pure function `AST → string`.** Rendering the same document twice gives identical output and leaves the tree untouched (enforced by `tests/Texy/renderer-purity.phpt`). Renderers that need to adjust a node's modifier work on clones.
- **The AST is format-neutral and semantically complete.** After the transform phase the tree contains everything any generator needs, without reaching back into the parser or modules. The placement test for configuration: *"does it change the document's content regardless of output format?"* → it belongs to a module / transform pass; *"does it only change the look of one output format?"* → it belongs to the generator. This is why e.g. `EmoticonModule::$icons` (syntax → character mapping) lives on the module, while `$emoticonClass` (CSS class) lives on the HTML generator. Neutrality holds for extensions too: a node class only one generator knows degrades to its content in the others (`NodeRenderer::renderUnknown()`), so a custom syntax never makes the document renderable in a single format only.

All classes live in the `Texy` namespace; modules in `Texy\Modules`, AST nodes in `Texy\Nodes`, output generators in `Texy\Output`.

## Key components

**The `Texy` class** (`src/Texy.php`, `src/Texy/Texy.php`) is the orchestrator and configuration facade. It owns the modules and the output configuration (`$texy->htmlOutput`), and drives the phases in `parse()`, `transform()` and `process()`.

**The `Engine`** (`src/Texy/Parsing/Engine.php`) is a minimal parsing core: it stores registered line/block patterns and creates parsers filtered by the `$allowed` array, and it keeps the passes with their stage and runs them. It contains no security or lifecycle logic – that stays in `Texy`.

**[Modules](modules.md)** encapsulate one area of the markup language each: they register patterns, turn matches into AST nodes, and register transform passes.

**[Parsers](parsing.md)** – `BlockParser` for multi-line block constructs, `InlineParser` for inline syntaxes. Both produce `Nodes\ContentNode` containers; recursion goes through `ParseContext`.

**The AST** – node classes in `src/Texy/Nodes/`, all extending `Texy\Node`. Two abstract families: `BlockNode` (paragraphs, headings, lists, tables…) and `InlineNode` (text, phrases, links, images…). Child lists live in `ContentNode` containers. `Texy\NodeTraverser` provides visitor-style traversal with enter/leave callbacks and node replacement/removal; `Texy\NodeHelpers` wraps it in `find()`, `findFirst()` and `clone()` for the common read-only cases.

**[Output generators](rendering.md)** – `Output\Html\Renderer` and `Output\Text\Renderer`, both extending `Output\NodeRenderer` (a per-node-class dispatch with chainable custom handlers). The HTML renderer is created per render by the `Output\Html\Config` configuration facade; the text renderer needs no `Texy` instance at all – the test of the AST's semantic completeness.

## Processing flow

`Texy::process($text)` is `parse()`, `transform()` and `renderHtml()` in a row; `parse()` itself begins with normalization. In detail:

1. **Normalize** (static `Texy::normalize()`, applied by `parse()` idempotently): removes the leading BOM, normalizes line endings, strips control characters (this is why control bytes can never reach patterns from input), right-trims lines and drops blank edge lines. Soft hyphens are a legitimate character and pass through. Tabs are preserved; the syntax treats a tab as plain whitespace (indentation is compared literally or per character, never in visual columns).

2. **Module initialization.** Each module's `beforeParse()` runs: modules register their line/block patterns here (on every parse – some patterns depend on runtime config such as `ListModule::$bullets`).

3. **Parse.** The `Engine` creates parsers with patterns filtered by `$allowed` (consulted once here; changing `$allowed` mid-process has no effect). `BlockParser` walks the text and calls syntax handlers, which return `BlockNode`s; text between block matches goes to the *gap handler* – `ParagraphModule::parseText()`, which splits it into paragraphs and uses `InlineParser` for their content. Passes registered with `Stage::Syntax` run next; the built-in one, `LinkReferenceModule::recognizeReferences()`, moves the name of a link target written as `[ref]` or `[*img*]` into `LinkNode::$ref`, leaving `$url` to the transform. The result is a `Nodes\DocumentNode`, the syntax tree `parse()` returns.

4. **Transform.** `transform($document)` runs the passes of `Stage::Transform` and then of `Stage::Finalize`, each stage in registration (module-construction) order:
   - `DirectiveModule::processDirectives()` – consumes `{{texy: …}}` directives into `DocumentNode::$meta` (e.g. `$meta['nofollow']`) and dispatches other directives to `DirectiveModule::addDirective()` handlers; running first, a handler may still change configuration the later passes depend on,
   - `HtmlModule::processPassthrough()` – pairs passthrough HTML tags into element subtrees and evaluates the tag whitelist (see [rendering.md](rendering.md#html-passthrough-transform-passes)),
   - `ImageModule` / `LinkReferenceModule` `resolveReferences()` – resolves `[ref]` and `[*ref*]` references against collected definitions,
   - `EmoticonModule::resolveEmoticons()` – resolves emoticon syntax to characters (`EmoticonNode::$resolved`),
   - `Passes\HeadingPass` – heading level balancing, `{toc: …}` hoisting and ID generation.

   The **typography pass** (below) is the built-in `Stage::Finalize` pass; it marks the document `$meta['typographed']`. `transform()` marks the document `$meta['transformed']` and does nothing on a document marked so.

   The order above is a contract, not an accident: `HtmlModule` must run before typography (rejected tags become visible text), `ImageModule` must resolve before `LinkReferenceModule` (image references used as link targets), and `HeadingPass` generates IDs before consumer handlers read the headings. Consumer passes are registered after module construction, so they always run **after** the built-in passes of their stage.

5. **Render.** a fresh `Output\Html\Renderer` (configured by `$texy->htmlOutput`) produces the final HTML string (see [rendering.md](rendering.md)). Any renderer can render the same document; `parse()` and `transform()` can equally be called alone and the AST rendered later or elsewhere – that is what `Texy::renderHtml()` does.

`process($text, singleLine: true)` is the single-line (inline-only) variant; `applyTypography()` applies only typography/hyphenation to plain text without parsing.

## Two states of the tree

The same node classes describe the document in two states with a sharp boundary between them:

- The **syntax tree**, returned by `parse()`, records how the source is written: every node carries the range of its whole notation, and nothing of the meaning is applied yet. References are unresolved, heading levels are as written, HTML tags are flat and unchecked, text is not typographed. Tools that write into the source work with this state.
- The **document**, after `transform()`, describes what the text means. Passes resolve, restructure and typograph it in place; a range now tells where a node comes from, not what is written there. Renderers work with this state.

A consumer that needs both states clones the syntax tree (`NodeHelpers::clone()`) before transforming it.

## Typography over the AST

Typography (locale-aware quotes, dashes, non-breaking spaces) and long-word hyphenation are **AST transformations**, not string post-processing, so every output format gets them. `Texy\Passes\TextRunPass` implements them:

- For every block-level inline container (paragraph, heading, table cell…) it builds a *text image*: `TextNode` contents joined with marker characters standing in for markup boundaries (`\x17`), replaced content like images or `<br>` (`\x16`), and opaque protected text like inline code or URLs (`\x15`, repeated to mirror the visible length so length-sensitive rules judge it fairly).
- The regex transformers (`TypographyModule::postLine()`, `HyphenationModule::postLine()`) run over the image. **The patterns treat the marker bytes as a transparent alphabet and never create or destroy them** – this invariant is what allows the result to be split back unambiguously into the original text nodes.
- Block-level markup inside the container splits the image into independently processed segments.
- `Modifier::$title` values are typographed as isolated strings (quotes must not pair across a title and the body text).

Whether the pass runs is controlled by `$allowed['typography']` and `$allowed['longwords']`.

## Source positions

Nodes and modifiers carry a `Range` (`$offset`, `$length` in bytes of the **normalized** source). What it means depends on the [state of the tree](#two-states-of-the-tree):

- **In the syntax tree a range is the notation.** Every node made by the parser covers all it was written with, the lines a handler consumed included; a child lies inside its parent and siblings do not overlap (enforced over all test sources by `tests/Texy/range-invariants.phpt`). `null` has only a node without a source of its own (containers, the padding cell of a short table row) and a modifier assembled from non-contiguous parts (a cell inheriting the column modifier). Only a handler or a `Stage::Syntax` pass may set or extend a range.
- **In the document a range is the origin.** A Transform pass never moves or recomputes a range, even when it changes the node's text or structure; a node it creates carries the range of the notation it comes from (an `HtmlElementNode` the one of its opening tag) or none. What the built-in passes change: `DirectiveModule` consumes `{{texy: …}}` into `DocumentNode::$meta` and replaces handled directives, `HtmlModule` pairs tags into `HtmlElementNode` and turns rejected tags into `TextNode`, `ImageModule` and `LinkReferenceModule` fill `$url` of references, `EmoticonModule` fills `EmoticonNode::$resolved`, `HeadingPass` sets `$level`, `$id` and `$titleOverride`, the typography rewrites `TextNode::$text` and modifier titles.
- **The range is the source, never the text.** `TextNode::$text` is display text from the parse on (`&amp;` is `TextNode('&')` with the range of the entity), only code-like nodes keep literal source. The notation is read through the range.
- **A node records the parts of its notation a tool would otherwise take apart again:** a part gets its own `Range` when it can be rewritten on its own, a variant is stored as written. `LinkNode::$syntax` (the name of the syntax, e.g. `Syntax::WikiLink`), `$textRange`, `$urlRange`; `ImageNode::$urlRange`; `HeadingNode::$marker`, `$textRange`; `ListItemNode::$bullet`, `$bulletRange`; `CodeBlockNode::$codeMap`, an `OffsetMap` from `$code` to the source. `OffsetMap::applyTo()` maps every `Range` and `OffsetMap` property of a node, so parts stay right inside lists, quotes, cells and `/--div`. A node of a custom syntax records no parts; a tool replaces it whole.
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

**Transform pass** – a callable registered with `Texy::addPass()`, receiving the `DocumentNode` in `transform()`. This is where the AST may be freely mutated, typically using `NodeHelpers` or, for structural changes, `NodeTraverser`. All passes run, in registration order within their stage, and return values are ignored. A pass registered with `Stage::Syntax` runs in `parse()` instead and may only finish recognizing the source.

**Node renderer** – a callable registered on an output generator with `NodeRenderer::addRenderer()` that renders one node class, optionally delegating to the previous renderer in the chain. This is the render-phase extension point (see [custom-handlers.md](custom-handlers.md)).

Passes registered by modules always run before any the consumer adds to the same stage, so a consumer cannot get in front of them. Where the order matters, call `parse()`, work on the AST, and then `transform()`.
