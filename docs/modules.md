# The Module System

Modules are the basic organizational unit of the parse and transform phases. Each module encapsulates one area of the markup language: it registers the syntaxes, turns matches into AST nodes, and registers the transform passes its area needs. Modules do **not** render – all output generation lives in `Texy\Output` (see [rendering.md](rendering.md)).

## Responsibilities of a module

1. **Registering syntaxes.** Line and block patterns are registered in `beforeParse()` (called on every `process()`, so patterns may depend on runtime config such as `ListModule::$bullets`) via `Texy::registerLinePattern()` / `registerBlockPattern()`.
2. **Building AST nodes.** Syntax handlers extract data from regex groups, parse nested content through the `ParseContext`, and return `Texy\Nodes\*` objects.
3. **Registering transform passes.** Work that needs the whole document – reference resolution, heading balancing, HTML pairing/sanitization – is registered in the constructor as an `afterParse` handler.
4. **Semantic configuration and state.** Public properties that change the *document's meaning* stay on the module (`HeadingModule::$top`, `EmoticonModule::$icons`, `TypographyModule::$locale`); properties that only change the HTML output's look live on `$texy->htmlOutput` (modules keep deprecated `__get`/`__set` bridges for their old locations). Module state such as collected reference definitions is private to the module; per-parse *results* belong in the AST, not on the module (see `HeadingNode::$tocTitle`).

The set of modules is created in `Texy::loadModules()`; a `Texy` subclass may override it.

## Anatomy of a typical module

```php
final class FooModule extends Texy\Module
{
    public string $someOption = 'default';   // semantic configuration

    public function __construct(
        private Texy\Texy $texy,
    ) {
        $texy->allowed['foo/advanced'] = false;              // default off
        $texy->addHandler('afterParse', $this->resolve(...)); // transform pass
    }

    public function beforeParse(string &$text): void
    {
        $this->texy->registerLinePattern(
            $this->parseFoo(...),   // syntax handler
            '~...~U',               // pattern
            'foo',                  // syntax name
        );
    }

    /** @param array<?string> $matches  @param array<?int> $offsets */
    public function parseFoo(Texy\ParseContext $context, array $matches, array $offsets): ?Texy\Nodes\InlineNode
    {
        // extract data, optionally $context->parseInline(...) for nested content,
        // return a node or null to refuse the match
    }
}
```

## Module overview

All modules live in `src/Texy/Modules/`; syntax names are catalogued on `Texy\Syntax`.

### Inline (line) modules

| Module | Purpose |
|---|---|
| **DirectiveModule** | `{{command: args}}` directives → `DirectiveNode`; consumes `{{texy: …}}` into `DocumentNode::$meta` in the transform phase. |
| **HtmlModule** | HTML tags and comments written in the input → `HtmlTagNode` / `HtmlCommentNode`; registers the pairing + sanitization transform passes ([rendering.md](rendering.md#html-passthrough-transform-passes)). |
| **ImageModule** | Images `[* url *]` → `ImageNode` (wrapped in `LinkNode` when clickable); collects `[*ref*]:` definitions and resolves references in the transform phase. |
| **PhraseModule** | All inline formatting → `PhraseNode` (type = syntax name); acronyms → `AnnotationNode`, `''notexy''` → `RawTextNode`; the alternative link syntaxes (wikilink, markdown, quicklink) → `LinkNode`. |
| **LinkReferenceModule** | `[ref]: url` definitions → `LinkDefinitionNode`; resolves `[ref]` targets of `LinkNode`s in the transform phase; exposed as `$texy->linkModule`. |
| **AutolinkModule** | Autodetected URLs and e-mails → `UrlNode` / `EmailNode`. |
| **EmoticonModule** | Emoticons `:-)` → `EmoticonNode`; resolved to Unicode characters in the transform phase (`$resolved`). Disabled by default. |

### Block modules

| Module | Purpose |
|---|---|
| **ParagraphModule** | Not pattern-based: the block parser's *gap handler*. Splits inter-block text into `ParagraphNode`s, handles hard line breaks and the paragraph modifier (see [parsing.md](parsing.md#paragraphmodule-the-gap-handler)). |
| **BlockModule** | Fenced blocks `/-- type … \--` → `CodeBlockNode` (code/html/text/pre…), `SectionNode` (div), `CommentNode`; nested Texy for `block/texy`. |
| **FigureModule** | Image with caption → `FigureNode` holding the image node and caption content. |
| **HorizontalRuleModule** | `---` / `***` → `HorizontalRuleNode`. |
| **BlockQuoteModule** | `>` quotations → `BlockQuoteNode`, including nested content and citation link. |
| **TableModule** | Tables → `TableNode` / `TableRowNode` / `TableCellNode` with head detection, colspan/rowspan. |
| **HeadingModule** | Underlined and surrounded headings → `HeadingNode`; registers `Passes\HeadingPass`, which balances levels (`DYNAMIC`/`FIXED`), fills `HeadingNode::$tocTitle` and generates IDs. |
| **ListModule** | Bulleted, numbered and definition lists → `ListNode` / `DefinitionListNode` with `ListItemNode`s. |

### Text-transformation modules

| Module | Purpose |
|---|---|
| **TypographyModule** | Locale-aware quotes, dashes, ellipsis, non-breaking spaces… Its `postLine()` regexes are run by `TextRunPass` over the AST text image ([architecture.md](architecture.md#typography-over-the-ast)). |
| **HyphenationModule** | Inserts soft hyphens into words longer than `$wordLimit`; second transformer of the same pass. |

Output formatting is not a module: its configuration lives on `$texy->htmlOutput->formatter` (`Output\Html\Formatter`).

## Interactions between modules

Modules communicate through the AST, not through each other:

- **Nodes are the interface.** A clickable image is a `LinkNode` wrapping an `ImageNode`; a figure holds its image node; `PhraseModule` produces `LinkNode`s directly for its link syntaxes. There are no shared mutable value objects.
- **References resolve in the transform phase.** Definitions collected during parsing (`ImageDefinitionNode`, `LinkDefinitionNode`) stay in the tree; the modules' `afterParse` passes walk the document with `NodeTraverser` and fill in the referencing nodes. User-supplied definitions (`addDefinition()`) persist across `process()` calls.
- Relations are one-directional (`PhraseModule` knows link nodes exist; link modules know nothing of phrases), keeping modules replaceable.
