# Texy Syntax Reference

All Texy syntaxes with the AST node they produce and the HTML they render to.

## Block syntaxes

| Constant | Module | Node class | Example | HTML |
|-----------|-------|------------|--------|------|
| `Syntax::Blocks` | BlockModule | — | `/--xxx` | (master pattern) |
| `Syntax::BlockDefault` | BlockModule | `PreformattedNode` | `/-- text` | `<pre>` |
| `Syntax::BlockPre` | BlockModule | `PreformattedNode` | `/--pre` | `<pre>` |
| `Syntax::BlockCode` | BlockModule | `CodeBlockNode` | `/--code php` | `<pre><code>` |
| `Syntax::BlockHtml` | BlockModule | `HtmlBlockNode` | `/--html` | (raw HTML) |
| `Syntax::BlockText` | BlockModule | `TextBlockNode` | `/--text` | (escaped text) |
| `Syntax::BlockTexySource` | BlockModule | `TexySourceNode` | `/--texysource` | `<pre><code>` |
| `Syntax::BlockComment` | BlockModule | `CommentNode` | `/--comment` | (nothing) |
| `Syntax::BlockDiv` | BlockModule | `DivNode` | `/--div` | `<div>` |
| `Syntax::Blockquote` | BlockQuoteModule | `BlockQuoteNode` | `> quote` | `<blockquote>` |
| `Syntax::Figure` | ImageModule | `FigureNode` | `[*img*] *** caption` | `<div class="figure">` |
| `Syntax::HeadingUnderlined` | HeadingModule | `HeadingNode` | `Heading`<br>`#######` | `<h1>`–`<h6>` |
| `Syntax::HeadingSurrounded` | HeadingModule | `HeadingNode` | `### Heading` | `<h1>`–`<h6>` |
| `Syntax::HorizontalRule` | HorizontalRuleModule | `HorizontalRuleNode` | `---` or `***` | `<hr>` |
| `Syntax::ImageDefinition` | ImageModule | `ImageDefinitionNode` | `[*logo*]: img.png` | (definition) |
| `Syntax::LinkDefinition` | LinkReferenceModule | `LinkDefinitionNode` | `[nette]: https://...` | (definition) |
| `Syntax::List` | ListModule | `ListNode` | `- item` | `<ul>`, `<ol>` |
| `Syntax::DefinitionList` | ListModule | `DefinitionListNode` | `term:`<br>`- definition` | `<dl>` |
| `Syntax::Table` | TableModule | `TableNode` | `| a | b |` | `<table>` |

## Inline syntaxes

| Constant | Module | Node class | Example | HTML |
|-----------|-------|------------|--------|------|
| `Syntax::StrongEmphasis` | PhraseModule | `PhraseNode` | `***text***` | `<strong><em>` |
| `Syntax::Strong` | PhraseModule | `PhraseNode` | `**text**` | `<strong>` |
| `Syntax::Emphasis` | PhraseModule | `PhraseNode` | `//text//` | `<em>` |
| `Syntax::EmphasisSingleAsterisk` | PhraseModule | `PhraseNode` | `*text*` | `<em>` |
| `Syntax::EmphasisSingleAsterisk2` | PhraseModule | `PhraseNode` | `*text*` | `<em>` |
| `Syntax::Inserted` | PhraseModule | `PhraseNode` | `++text++` | `<ins>` |
| `Syntax::Deleted` | PhraseModule | `PhraseNode` | `--text--` | `<del>` |
| `Syntax::Superscript` | PhraseModule | `PhraseNode` | `^^text^^` | `<sup>` |
| `Syntax::SuperscriptShort` | PhraseModule | `PhraseNode` | `m^2` | `<sup>` |
| `Syntax::Subscript` | PhraseModule | `PhraseNode` | `__text__` | `<sub>` |
| `Syntax::SubscriptShort` | PhraseModule | `PhraseNode` | `H_2O` | `<sub>` |
| `Syntax::SpanQuotes` | PhraseModule | `PhraseNode` | `"text"` | `<span>` |
| `Syntax::SpanTilde` | PhraseModule | `PhraseNode` | `~text~` | `<span>` |
| `Syntax::AbbreviationQuotes` | PhraseModule | `PhraseNode` | `"NATO"((abbreviation))` | `<abbr>` |
| `Syntax::Abbreviation` | PhraseModule | `PhraseNode` | `NATO((abbreviation))` | `<abbr>` |
| `Syntax::Code` | PhraseModule | `InlineCodeNode` | `` `code` `` | `<code>` |
| `Syntax::Quote` | PhraseModule | `PhraseNode` | `>>quote<<` | `<q>` |
| `Syntax::Raw` | PhraseModule | `RawTextNode` | `''raw text''` | (escaped) |
| `Syntax::QuickLink` | PhraseModule | `LinkNode` | `word:[url]` | `<a>` |
| `Syntax::WikiLink` | PhraseModule | `LinkNode` | `[text|url]` | `<a>` |
| `Syntax::MarkdownLink` | PhraseModule | `LinkNode` | `[text](url)` | `<a>` |
| `Syntax::EscapedAsterisk` | PhraseModule | `TextNode` | `\*` | `*` |
| `Syntax::Image` | ImageModule | `ImageNode` | `[*img.png*]` | `<img>` |
| `Syntax::AutolinkUrl` | AutolinkModule | `UrlNode` | `https://example.com` | `<a>` |
| `Syntax::AutolinkEmail` | AutolinkModule | `EmailNode` | `user@example.com` | `<a>` |
| `Syntax::Emoticon` | EmoticonModule | `EmoticonNode` | `:-)` | 🙂 / `<span>` |
| `Syntax::HtmlTag` | HtmlModule | `HtmlTagNode` | `<br>` | (any) |
| `Syntax::HtmlComment` | HtmlModule | `HtmlCommentNode` | `<!-- text -->` | `<!-- -->` |
| `Syntax::Directive` | DirectiveModule | `DirectiveNode` | `{{texy}}` | (nothing) |

Typography and hyphenation are not syntaxes: they are switched by `$texy->config->typography`
and `$texy->config->hyphenation`.
