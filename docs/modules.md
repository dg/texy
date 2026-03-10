# The Module System

Modules are the internal organization of a language in the parse and transform phases. Each module encapsulates one area of the markup: it declares the syntax names, registers the patterns and turns matches into AST nodes. Modules do **not** render – all output generation lives in `Texy\Output` (see [rendering.md](rendering.md)).

Modules are not an API of an application. A language consists of them, and they can be split, merged or rebuilt at will. An application extends Texy with `addInlineSyntax()`, `addBlockSyntax()`, `addPass()` and `$texy->htmlOutput->addRenderer()`, and configures it on `$texy->config`.

## The contract

A module extends `Texy\Module`, whose single method is `register(Parsing\Registry $registry)`; the same contract serves [texy/markdown](https://github.com/dg/texy-markdown). `Registry` has `addInline()`, `addBlock()`, `declare($name, $enabled)` for a name without a pattern of its own or with a default other than enabled, and `setGapHandler()`.

- `register()` runs in the constructor of the facade and again before every parse in Texy, because patterns depend on the configuration (`TexyConfig::$listBullets`); a name keeps its position, and the state declared first holds.
- A module gets in the constructor only what it needs: the configuration, a policy, another module. It holds no reference to the facade.
- A module registers no passes. What it does over the whole document is a public method and the facade lists it among the passes, whose order is thus one explicit list in `Texy::createModules()`.
- Configuration lives on `TexyConfig`, permanent registrations of the application too (`addImageDefinition()`, `addLinkDefinition()`). A definition read from a document stays local to its parse; per-parse *results* belong in the AST (see `HeadingNode::getTitle()`).

## Anatomy of a typical module

```php
final class FooModule extends Texy\Module
{
	public function __construct(
		private Texy\TexyConfig $config,
	) {
	}


	public function register(Texy\Parsing\Registry $registry): void
	{
		$registry->declare('foo/advanced', enabled: false);
		$registry->addInline('foo', '~...~U', $this->parseFoo(...));
	}


	/** @param array<?string> $matches  @param array<?int> $offsets */
	public function parseFoo(Texy\Parsing\ParseContext $context, array $matches, array $offsets): ?Texy\Nodes\InlineNode
	{
		// extract data, optionally $context->parseInline(...) for nested content,
		// return a node or null to refuse the match
	}
}
```

## Module overview

All modules live in `src/Modules/`; syntax names are catalogued on `Texy\Syntax`.

### Inline (line) modules

| Module | Purpose |
|---|---|
| **DirectiveModule** | `{{command: args}}` directives → `DirectiveNode`; consumes `{{texy: …}}` into `DocumentNode::$meta` in the transform phase. |
| **HtmlModule** | HTML tags and comments written in the input → `HtmlTagNode` / `HtmlCommentNode`; the pairing and the sanitization are common passes ([rendering.md](rendering.md#html-passthrough-transform-passes)). |
| **ImageModule** | Images `[* url *]` → `ImageNode` (wrapped in `LinkNode` when clickable); the block form with caption → `FigureNode`; collects `[*ref*]:` definitions and resolves references in the transform phase. |
| **PhraseModule** | All inline formatting → `PhraseNode` (type = syntax name), `` `code` `` → `InlineCodeNode`; acronyms → `AnnotationNode`, `''notexy''` → `RawTextNode`; the alternative link syntaxes (wikilink, markdown, quicklink) → `LinkNode`. |
| **LinkReferenceModule** | `[ref]: url` definitions → `LinkDefinitionNode`; resolves `[ref]` targets of `LinkNode`s in the transform phase. |
| **AutolinkModule** | Autodetected URLs and e-mails → `UrlNode` / `EmailNode`. |
| **EmoticonModule** | Emoticons `:-)` → `EmoticonNode`; resolved to Unicode characters by the table of the configuration already when parsed (`$resolved`). Disabled by default. |

### Block modules

| Module | Purpose |
|---|---|
| **ParagraphModule** | Not pattern-based: the block parser's *gap handler*. Splits inter-block text into `ParagraphNode`s, handles hard line breaks and the paragraph modifier (see [parsing.md](parsing.md#paragraphmodule-the-gap-handler)). |
| **BlockModule** | Fenced blocks `/-- type … \--` → a `VerbatimBlockNode` per type (`CodeBlockNode` for code, `PreformattedNode` for `/--pre` and a bare `/--`, `HtmlBlockNode`, `TextBlockNode`, `TexySourceNode`), `DivNode`, `CommentNode`; nested Texy for `block/texy`; an unknown type that looks like a language name becomes a code block in that language. |
| **HorizontalRuleModule** | `---` / `***` → `HorizontalRuleNode`. |
| **BlockQuoteModule** | `>` quotations → `BlockQuoteNode`, including nested content and citation link. |
| **TableModule** | Tables → `TableNode` / `TableRowNode` / `TableCellNode` with head detection, colspan/rowspan and an optional `\|##` caption. |
| **HeadingModule** | Underlined and surrounded headings → `HeadingNode` with the absolute level; balances levels of `HeadingBalancing::Dynamic` and hoists `{toc: …}` labels. The common `Passes\HeadingPass` shifts the levels and generates IDs. |
| **ListModule** | Bulleted, numbered and definition lists → `ListNode` / `DefinitionListNode` with `ListItemNode`s. |

Typography and hyphenation are not modules: `Typography\Typographer` and `CzechHyphenator` are algorithms over `Config`, run by `TextRunPass` ([architecture.md](architecture.md#typography-over-the-ast)). Output formatting is not a module either: its configuration lives on `$texy->htmlOutput` (`Output\Html\Config`).

## Interactions between modules

Modules communicate through the AST, not through each other:

- **Nodes are the interface.** A clickable image is a `LinkNode` wrapping an `ImageNode`; a figure holds its image node; `PhraseModule` produces `LinkNode`s directly for its link syntaxes. There are no shared mutable value objects.
- **References resolve in the transform phase.** Definitions collected during parsing (`ImageDefinitionNode`, `LinkDefinitionNode`) stay in the tree; the modules' transform passes walk the document with `$document->find()` and fill in the referencing nodes. Definitions of the application (`addLinkDefinition()`, `addImageDefinition()`) persist across `toHtml()` calls.
- Relations are one-directional (`PhraseModule` knows link nodes exist; link modules know nothing of phrases), keeping modules replaceable.
