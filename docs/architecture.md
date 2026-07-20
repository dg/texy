# Architecture and Principles

Texy converts text written in its own markup language to HTML (or Markdown). Unlike simple converters that process text linearly with a series of replacements, Texy parses the input into an **AST** (abstract syntax tree) and generates output from it.

Processing runs in four phases:

```
preprocess → parse (modules) → transform (afterParse passes) → render (Output\*)
```

1. **Preprocess** – input normalization: line endings, control-character stripping, tab expansion, soft-hyphen removal.
2. **Parse** – recognition of syntaxes using regular expressions; modules build the tree of `Texy\Nodes\*` objects.
3. **Transform** – AST-to-AST passes: reference resolution, heading balancing, HTML passthrough pairing and sanitization, typography. The transform phase **may mutate the tree**; after it, the document is semantically complete.
4. **Render** – an output generator converts the AST to a string. The render phase **must not mutate the tree or configuration**.

Two invariants govern the whole design:

- **Render is a pure function `AST → string`.** Rendering the same document twice gives identical output and leaves the tree untouched (enforced by `tests/Texy/renderer-purity.phpt`). Renderers that need to adjust a node's modifier work on clones.
- **The AST is format-neutral and semantically complete.** After the transform phase the tree contains everything any generator needs, without reaching back into the parser or modules. The placement test for configuration: *"does it change the document's content regardless of output format?"* → it belongs to a module / transform pass; *"does it only change the look of one output format?"* → it belongs to the generator. This is why e.g. `EmoticonModule::$icons` (syntax → character mapping) lives on the module, while `$emoticonClass` (CSS class) lives on the HTML generator.

All classes live in the `Texy` namespace; modules in `Texy\Modules`, AST nodes in `Texy\Nodes`, output generators in `Texy\Output`.

## Key components

**The `Texy` class** (`src/Texy.php`, `src/Texy/Texy.php`) is the orchestrator and configuration facade. It owns the modules, the event handlers, and the output generator (`$texy->htmlOutput`), and drives the phases in `process()` / `parse()`.

**The `Engine`** (`src/Texy/Engine.php`) is a minimal parsing core: it stores registered line/block patterns and creates parsers filtered by the `$allowed` array. It contains no security or lifecycle logic – that stays in `Texy`.

**[Modules](modules.md)** encapsulate one area of the markup language each: they register patterns, turn matches into AST nodes, and register transform passes.

**[Parsers](parsing.md)** – `BlockParser` for multi-line block constructs, `InlineParser` for inline syntaxes. Both produce `Nodes\ContentNode` containers; recursion goes through `ParseContext`.

**The AST** – node classes in `src/Texy/Nodes/`, all extending `Texy\Node`. Two abstract families: `BlockNode` (paragraphs, headings, lists, tables…) and `InlineNode` (text, phrases, links, images…). Child lists live in `ContentNode` containers. `Texy\NodeTraverser` provides visitor-style traversal with enter/leave callbacks and node replacement/removal.

**[Output generators](rendering.md)** – `Output\Html\Config` and `Output\Markdown\Generator`, both extending `Output\NodeRenderer` (a per-node-class dispatch with chainable custom handlers). The Markdown generator needs no `Texy` instance at all – the test of the AST's semantic completeness.

## Processing flow

`Texy::process($text)` is `preprocess()` + `parse()` + `Output\Html\Renderer::render()`. In detail:

1. **Preprocess** (`Texy::preprocess()`): removes soft hyphens (if `$removeSoftHyphens`), normalizes line endings and strips control characters (`Helpers::normalize()` – this is why control bytes can never reach patterns from input), expands tabs per `$tabWidth`.

2. **Module initialization.** Each module's `beforeParse(&$text)` runs: modules register their line/block patterns here (on every parse – some patterns depend on runtime config such as `ListModule::$bullets`) and may preprocess the text.

3. **Parse.** The `Engine` creates parsers with patterns filtered by `$allowed` (consulted once here; changing `$allowed` mid-process has no effect). `BlockParser` walks the text and calls syntax handlers, which return `BlockNode`s; text between block matches goes to the *gap handler* – `ParagraphModule::parseText()`, which splits it into paragraphs and uses `InlineParser` for their content. The result is a `Nodes\DocumentNode`.

4. **Transform.** The `afterParse` event fires with the document; registered passes run in registration (module-construction) order:
   - `DirectiveModule::processDirectives()` – consumes `{{texy: …}}` directives into `DocumentNode::$meta` (e.g. `$meta['nofollow']`) and removes their nodes,
   - `HtmlModule::processPassthrough()` – pairs passthrough HTML tags into element subtrees and evaluates the tag whitelist (see [rendering.md](rendering.md#html-passthrough-transform-passes)),
   - `ImageModule` / `LinkReferenceModule` `resolveReferences()` – resolves `[ref]` and `[*ref*]` references against collected definitions,
   - `EmoticonModule::resolveEmoticons()` – resolves emoticon syntax to characters (`EmoticonNode::$resolved`),
   - `Passes\HeadingPass` – heading level balancing, `$tocTitle` extraction and ID generation.

   Then `Texy` runs the **typography pass** (below) and marks the document `$meta['typographed']`.

5. **Render.** a fresh `Output\Html\Renderer` (configured by `$texy->htmlOutput`) produces the final HTML string (see [rendering.md](rendering.md)). Any renderer can render the same document; `parse()` can equally be called alone and the AST rendered later or elsewhere.

`processLine()` / `parseLine()` are the single-line (inline-only) variants; `processTypo()` applies only typography/hyphenation to plain text without parsing.

## Typography over the AST

Typography (locale-aware quotes, dashes, non-breaking spaces) and long-word hyphenation are **AST transformations**, not string post-processing, so every output format gets them. `Texy\TextRunPass` implements them:

- For every block-level inline container (paragraph, heading, table cell…) it builds a *text image*: `TextNode` contents joined with marker characters standing in for markup boundaries (`\x17`), replaced content like images or `<br>` (`\x16`), and opaque protected text like inline code or URLs (`\x15`, repeated to mirror the visible length so length-sensitive rules judge it fairly).
- The regex transformers (`TypographyModule::postLine()`, `HyphenationModule::postLine()`) run over the image. **The patterns treat the marker bytes as a transparent alphabet and never create or destroy them** – this invariant is what allows the result to be split back unambiguously into the original text nodes.
- Block-level markup inside the container splits the image into independently processed segments.
- `Modifier::$title` values are typographed as isolated strings (quotes must not pair across a title and the body text).

Whether the pass runs is controlled by `$allowed['typography']` and `$allowed['longwords']`.

## Terminology

**Syntax** – a named syntactic construct, e.g. `phrase/strong` or `image`. Names are catalogued as constants on `Texy\Syntax` and used as keys in `Texy::$allowed`. Simple syntaxes have one-word names; related groups use a slash hierarchy (`phrase/*`, `block/*`).

**Pattern** – the regular expression that recognizes the syntax, registered via `Texy::registerLinePattern()` / `registerBlockPattern()` together with the syntax handler and name. Registration defaults `$allowed[$name]` to `true` unless a module set it otherwise (e.g. `emoticon`, `phrase/ins` default to `false`).

**Syntax handler** – the closure the parser calls on a match: `function(ParseContext $context, array $matches, array $offsets, string $name): ?Node`. It returns an AST node, or `null` to refuse the match (the parser then gives other patterns a chance). Handlers parse nested content recursively through the `ParseContext`.

**Transform pass** – a function registered for the `afterParse` event (`Texy::addHandler('afterParse', …)`), receiving the `DocumentNode`. This is where the AST may be freely mutated, typically using `NodeTraverser`.

**Renderer handler** – a closure registered on an output generator (`NodeRenderer::registerHandler()`) that renders a node class, optionally delegating to the previous handler in the chain. This is the render-phase extension point (see [custom-handlers.md](custom-handlers.md)).

The only built-in event is `afterParse`. `Texy::addHandler()` / `invokeHandlers()` is a plain notification mechanism: all handlers run in registration order, return values are ignored.
