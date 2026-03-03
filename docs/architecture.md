# Architecture and Principles

Texy converts text written in its own markup language to HTML. Unlike simple converters that process text linearly with a series of replacements, Texy parses the input into an **AST** (abstract syntax tree) and generates output from it.

Processing runs in four phases:

```
preprocess → parse (modules) → transform (passes) → render (Output\*)
```

1. **Preprocess** – input normalization: line endings, control-character stripping, tab expansion, soft-hyphen removal.
2. **Parse** – recognition of syntaxes using regular expressions; modules build the tree of `Texy\Nodes\*` objects. The result is the *syntax tree*, see [Two states of the tree](#two-states-of-the-tree).
3. **Transform** – AST-to-AST passes: reference resolution, heading balancing, HTML passthrough pairing and sanitization, typography. The transform phase **may mutate the tree**; after it, the document is semantically complete.
4. **Render** – an output generator converts the AST to a string. The render phase **must not mutate the tree or configuration**.

Two invariants govern the whole design:

- **Render is a pure function `AST → string`.** Rendering the same document twice gives identical output and leaves the tree untouched (enforced by `tests/Texy/renderer-purity.phpt`). The output depends on the AST, the output configuration and the policies; the one exception is `$imageBasePath`, with which the HTML renderer reads the dimensions of images from the files on disk, so a renderer that lives long may see other dimensions after a file changed. Renderers that need to adjust a node's modifier work on clones.
- **The AST is format-neutral and semantically complete.** After the transform phase the tree contains everything any generator needs, without reaching back into the parser or modules. The placement test for configuration: *"does it change the document's content regardless of output format?"* → it belongs to `$texy->config` and a transform pass; *"does it only change the look of one output format?"* → it belongs to the generator. This is why e.g. `TexyConfig::$emoticons` (syntax → character mapping) lives on `$texy->config`, while `$emoticonClass` (CSS class) lives on the HTML generator. Neutrality holds for extensions too: a node class only one generator knows degrades to its content in the others (`NodeRenderer::renderUnknown()`), so a custom syntax never makes the document renderable in a single format only.

All classes live in the `Texy` namespace; modules in `Texy\Modules`, AST nodes in `Texy\Nodes`, output generators in `Texy\Output`.

## Key components

**The `Texy` class** (`src/Texy.php`) is the orchestrator and configuration facade. It holds the configuration (`$texy->config`, `$texy->htmlOutput`) and the policies as read-only references, creates the internal modules and lists their passes explicitly, and drives the phases in `parse()`, `transform()` and `toHtml()`.

**The `Engine`** (`src/Parsing/Engine.php`) is a minimal parsing core shared with [texy/markdown](https://github.com/dg/texy-markdown): it stores registered inline and block patterns, those of the language apart from those of the application, creates parsers filtered by the `$allowedSyntax` array, and owns the passes with their stage and the state of the transformation of a document. It implements `Parsing\Registry`, the contract a language registers on. It contains no security logic.

**[Modules](modules.md)** are internal: each encapsulates one area of the markup language and registers its patterns on `Parsing\Registry`, turning matches into AST nodes. What a module does over the whole document is a method the facade puts in its list of passes. **`Passes\DocumentPasses`** registers the passes common to both languages in one place (the HTML policy, headings, typography), so their order cannot differ.

**[Parsers](parsing.md)** – `BlockParser` for multi-line block constructs, `InlineParser` for inline syntaxes. Both produce `Nodes\ContentNode` containers; recursion goes through `ParseContext`.

**The AST** – node classes in `src/Nodes/`, all extending `Texy\Node`. Two abstract families: `BlockNode` (paragraphs, headings, lists, tables…) and `InlineNode` (text, phrases, links, images…). Child lists live in `ContentNode` containers. `Texy\NodeTraverser` provides visitor-style traversal with enter/leave callbacks and node replacement/removal; `Node::find()`, `findFirst()` and `traverse()` wrap it for the common cases, and a clone of a node copies its whole subtree.

**[Output generators](rendering.md)** – `Output\Html\Renderer` and `Output\Text\Renderer`, both extending `Output\NodeRenderer` (a per-node-class dispatch with chainable custom handlers). The HTML renderer is created per render by the `Output\Html\Config` configuration facade; the text renderer needs no `Texy` instance at all – the test of the AST's semantic completeness.

## Processing flow

`Texy::toHtml($text)` is `preprocess()`, `parse()`, `transform()` and `renderHtml()` in a row. In detail:

1. **Preprocess** (`Texy::preprocess()`): removes soft hyphens (if `$removeSoftHyphens`), normalizes line endings and strips control characters (`Helpers::normalize()` – this is why control bytes can never reach patterns from input), expands tabs per `$tabWidth`.

2. **Registration.** Modules register their patterns in the constructor of `Texy` and again before every parse, as some patterns depend on the configuration (`TexyConfig::$listBullets`); a name keeps its position and its state. `$allowedSyntax` is checked then: a key nobody declared throws.

3. **Parse.** Values of the configuration are validated. The `Engine` creates parsers with patterns filtered by `$allowedSyntax` (consulted once here; changing `$allowedSyntax` mid-process has no effect). `BlockParser` walks the text and calls syntax handlers, which return `BlockNode`s; text between block matches goes to the *gap handler* – `ParagraphModule::parseText()`, which splits it into paragraphs and uses `InlineParser` for their content. Passes registered with `Stage::Syntax` run next; the built-in one, `LinkReferenceModule::recognizeReferences()`, moves the name of a link target written as `[ref]` or `[*img*]` into `LinkNode::$ref`, leaving `$url` to the transform. The result is a `Nodes\DocumentNode`, the syntax tree `parse()` returns.

4. **Transform.** `transform($document)` runs the passes of `Stage::Transform` and then of `Stage::Finalize`, each stage in the order of registration, which the facade lists explicitly:
   - `DirectiveModule::processDirectives()` – consumes `{{texy: …}}` directives into `DocumentNode::$meta` (e.g. `$meta['nofollow']`) and removes their nodes,
   - `ImageModule` / `LinkReferenceModule` `resolveReferences()` – resolves `[ref]` and `[*ref*]` references against collected definitions,
   - `HeadingModule::processHeadings()` – level balancing of `HeadingBalancing::Dynamic` and `{toc: …}` hoisting,
   - and the common passes of `DocumentPasses`: `HtmlPairingPass` and `HtmlSanitizePass` (pairing and the tag whitelist, see [rendering.md](rendering.md#html-passthrough-transform-passes)), `HeadingPass` (shift by `headingTop`, IDs).

   The **typography pass** (below) is the common `Stage::Finalize` pass. A document goes through a transformation once, whichever instance or language asks for it: the state is held on the document, a second `transform()` throws `LogicException`, and a document whose transformation failed has to be parsed again.

   The order above is a contract, not an accident: the HTML policy must run before typography (rejected tags become literal text that typography leaves alone), `ImageModule` must resolve before `LinkReferenceModule` (image references used as link targets), and `HeadingPass` generates IDs before consumer handlers read the headings. Consumer passes are registered later, so they always run **after** the built-in passes of their stage: new headings and plain text belong to `Stage::Syntax` or between `parse()` and `transform()`, a pass of a later stage gets no ID, policy or typography for what it inserts.

5. **Render.** a fresh `Output\Html\Renderer` (configured by `$texy->htmlOutput`) produces the final HTML string (see [rendering.md](rendering.md)). Any renderer can render the same document; `parse()` and `transform()` can equally be called alone and the AST rendered later or elsewhere – that is what `Texy::renderHtml()` does.

`toInlineHtml($text)` is the single-line (inline-only) variant, `parseInline()` its parsing; `processTypo()` applies only typography/hyphenation to plain text without parsing.

## Two states of the tree

The same node classes describe the document in two states with a sharp boundary between them:

- The **syntax tree**, returned by `parse()`, records how the source is written: every node carries the range of its whole notation, and nothing of the meaning is applied yet. References are unresolved, heading levels are as written, HTML tags are flat and unchecked, text is not typographed. Tools that write into the source work with this state.
- The **document**, after `transform()`, describes what the text means. Passes resolve, restructure and typograph it in place; a range now tells where a node comes from, not what is written there. Renderers work with this state.

A consumer that needs both states clones the syntax tree (`clone $document`, a clone copies the whole subtree) before transforming it.

## Typography over the AST

Typography (locale-aware quotes, dashes, non-breaking spaces) and long-word hyphenation are **AST transformations**, not string post-processing, so every output format gets them. `Texy\Passes\TextRunPass` implements them:

- For every block-level inline container (paragraph, heading, table cell…) it builds a *text image*: `TextNode` contents joined with marker characters standing in for markup boundaries (`\x17`), replaced content like images or `<br>` (`\x16`), and opaque protected text like inline code or URLs (`\x15`, repeated to mirror the visible length so length-sensitive rules judge it fairly).
- The transformers (`Typography\Typographer::correct()`, `Typography\CzechHyphenator::hyphenate()`) run over the image, and a marker byte occurring in the text itself travels through them as a stand-in character. **The patterns treat the marker bytes as a transparent alphabet and never create or destroy them** – this invariant is what allows the result to be split back unambiguously into the original text nodes.
- Block-level markup inside the container splits the image into independently processed segments.
- `Modifier::$title` values are typographed as isolated strings (quotes must not pair across a title and the body text).

Whether the pass runs is controlled by `$texy->config->typography` and `$texy->config->hyphenation`.

## Source positions

Nodes and modifiers carry a `Range` (`$offset`, `$length` in bytes of the **preprocessed** source). What it means depends on the [state of the tree](#two-states-of-the-tree):

- **In the syntax tree a range is the notation.** Every node made by the parser covers all it was written with, the lines a handler consumed included; a child lies inside its parent and siblings do not overlap (enforced over all test sources by `tests/Texy/range-invariants.phpt`). `null` has only a node without a source of its own (containers, the padding cell of a short table row) and a modifier assembled from non-contiguous parts (a cell inheriting the column modifier). Only a handler or a `Stage::Syntax` pass may set or extend a range.
- **In the document a range is the origin.** A Transform pass never moves or recomputes a range, even when it changes the node's text or structure; a node it creates carries the range of the notation it comes from (an `HtmlElementNode` the one of its opening tag) or none. What the built-in passes change: `DirectiveModule` consumes `{{texy: …}}` into `DocumentNode::$meta`, the HTML passes pair tags into `HtmlElementNode` and turns rejected tags into `RawTextNode`, `ImageModule` and `LinkReferenceModule` fill `$url` of references, `HeadingModule` and `HeadingPass` set `$level`, `$id` and `$titleOverride`, the typography rewrites `TextNode::$text` and modifier titles.
- **The range is the source, never the text.** `TextNode::$text` is display text from the parse on (`&amp;` is `TextNode('&')` with the range of the entity), only code-like nodes keep literal source. The notation is read through the range.
- **A node records the parts of its notation a tool would otherwise take apart again:** a part gets its own `Range` when it can be rewritten on its own, a variant is stored as written. `LinkNode::$syntax` (the name of the syntax, e.g. `Syntax::WikiLink`), `$textRange`, `$urlRange`; `ImageNode::$urlRange`; `HeadingNode::$marker`, `$textRange`; `ListItemNode::$bullet`, `$bulletRange`; `CodeBlockNode::$codeMap`, an `OffsetMap` from `$code` to the source. `OffsetMap::applyTo()` maps every `Range` and `OffsetMap` property of a node, so parts stay right inside lists, quotes, cells and `/--div`. A node of a custom syntax records no parts; a tool replaces it whole.
- Heading IDs are generated in the transform phase **before** typography, so they are stable regardless of `$config->typography`. The displayed title (`HeadingNode::getTitle()`) is derived from the heading's content instead of copied, so it *is* typographed.

## Terminology

**Syntax** – a named syntactic construct, e.g. `phrase/strong` or `image`. Names are catalogued as constants on `Texy\Syntax` and used as keys in `Texy::$allowedSyntax`. Simple syntaxes have one-word names; related groups use a slash hierarchy (`phrase/*`, `block/*`).

**Pattern** – the regular expression that recognizes the syntax, registered via `Texy::addInlineSyntax()` / `addBlockSyntax()` together with the syntax name and its parser. Registration defaults `$allowedSyntax[$name]` to `true` unless a module set it otherwise (e.g. `emoticon`, `phrase/ins` default to `false`).

**Syntax handler** – the closure the parser calls on a match: `function(ParseContext $context, array $matches, array $offsets, string $name): ?Node`. It returns an AST node, or `null` to refuse the match (the parser then gives other patterns a chance). Handlers parse nested content recursively through the `ParseContext`.

**Transform pass** – a callable registered with `Texy::addPass()`, receiving the `DocumentNode` in `transform()`. This is where the AST may be freely mutated, typically using `$document->find()` or, for structural changes, `$document->traverse()`. All passes run, in registration order within their stage, and return values are ignored. A pass registered with `Stage::Syntax` runs in `parse()` instead and may only finish recognizing the source.

**Node renderer** – a callable registered on an output generator with `NodeRenderer::addRenderer()` that renders one node class, optionally delegating to the previous renderer in the chain. This is the render-phase extension point (see [custom-handlers.md](custom-handlers.md)).

Passes registered by modules always run before any the consumer adds to the same stage, so a consumer cannot get in front of them. Where the order matters, call `parse()`, work on the AST, and then `transform()`.
