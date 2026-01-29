# Texy Syntax Reference

Kompletní přehled všech Texy syntaxí, jejich AST reprezentace a HTML výstupu.

## Blokové syntaxe

| Konstanta | Modul | Node Class | Ukázka | HTML |
|-----------|-------|------------|--------|------|
| `Syntax::Blocks` | BlockModule | — | `/--xxx` | (master pattern) |
| `Syntax::BlockDefault` | BlockModule | `CodeBlockNode` | `/-- text` | `<pre>` |
| `Syntax::BlockPre` | BlockModule | `CodeBlockNode` | `/--pre` | `<pre>` |
| `Syntax::BlockCode` | BlockModule | `CodeBlockNode` | `/--code php` | `<pre><code>` |
| `Syntax::BlockHtml` | BlockModule | `CodeBlockNode` | `/--html` | (raw HTML) |
| `Syntax::BlockText` | BlockModule | `CodeBlockNode` | `/--text` | (raw text) |
| `Syntax::BlockTexySource` | BlockModule | `CodeBlockNode` | `/--texysource` | `<pre><code>` |
| `Syntax::BlockComment` | BlockModule | `CommentNode` | `/--comment` | (nic) |
| `Syntax::BlockDiv` | BlockModule | `SectionNode` | `/--div` | `<div>` |
| `Syntax::Blockquote` | BlockQuoteModule | `BlockQuoteNode` | `> citace` | `<blockquote>` |
| `Syntax::Figure` | FigureModule | `FigureNode` | `[*img*] *** popis` | `<figure>` |
| `Syntax::HeadingUnderlined` | HeadingModule | `HeadingNode` | `Nadpis`<br>`######` | `<h1>`–`<h6>` |
| `Syntax::HeadingSurrounded` | HeadingModule | `HeadingNode` | `### Nadpis` | `<h1>`–`<h6>` |
| `Syntax::HorizontalRule` | HorizontalRuleModule | `HorizontalRuleNode` | `---` nebo `***` | `<hr>` |
| `Syntax::ImageDefinition` | ImageModule | `ImageDefinitionNode` | `[*logo*]: img.png` | (definice) |
| `Syntax::LinkDefinition` | LinkModule | `LinkDefinitionNode` | `[nette]: https://...` | (definice) |
| `Syntax::List` | ListModule | `ListNode` | `- položka` | `<ul>`, `<ol>` |
| `Syntax::DefinitionList` | ListModule | `DefinitionListNode` | `pojem:`<br>`- definice` | `<dl>` |
| `Syntax::Table` | TableModule | `TableNode` | `| a | b |` | `<table>` |

## Inline syntaxe

| Konstanta | Modul | Node Class | Ukázka | HTML |
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
| `Syntax::AbbreviationQuotes` | PhraseModule | `PhraseNode` | `"NATO"((zkratka))` | `<abbr>` |
| `Syntax::Abbreviation` | PhraseModule | `PhraseNode` | `NATO((zkratka))` | `<abbr>` |
| `Syntax::Code` | PhraseModule | `PhraseNode` | `` `code` `` | `<code>` |
| `Syntax::Quote` | PhraseModule | `PhraseNode` | `>>citace<<` | `<q>` |
| `Syntax::Raw` | PhraseModule | `RawTextNode` | `''raw text''` | (escaped) |
| `Syntax::QuickLink` | PhraseModule | `LinkNode` | `slovo:[url]` | `<a>` |
| `Syntax::WikiLink` | PhraseModule | `LinkNode` | `[text|url]` | `<a>` |
| `Syntax::MarkdownLink` | PhraseModule | `LinkNode` | `[text](url)` | `<a>` |
| `Syntax::EscapedAsterisk` | PhraseModule | `TextNode` | `\*` | `*` |
| `Syntax::Image` | ImageModule | `ImageNode` | `[*img.png*]` | `<img>` |
| `Syntax::AutolinkUrl` | AutolinkModule | `UrlNode` | `https://example.com` | `<a>` |
| `Syntax::AutolinkEmail` | AutolinkModule | `EmailNode` | `user@example.com` | `<a>` |
| `Syntax::Emoticon` | EmoticonModule | `EmoticonNode` | `:-)` | 🙂 / `<span>` |
| `Syntax::HtmlTag` | HtmlModule | `HtmlTagNode` | `<br>` | (libovolný) |
| `Syntax::HtmlComment` | HtmlModule | `HtmlCommentNode` | `<!-- text -->` | `<!-- -->` |
| `Syntax::Directive` | DirectiveModule | `DirectiveNode` | `{{texy}}` | (nic) |

## Post-processing

| Konstanta | Modul | Popis |
|-----------|-------|-------|
| `Syntax::Typography` | TypographyModule | Typografické korekce (uvozovky, pomlčky, trojtečky) |
| `Syntax::Hyphenation` | LongWordsModule | Dělení dlouhých slov měkkými spojovníky |
