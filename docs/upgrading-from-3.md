# Upgrading from Texy 3 to Texy 4

Texy 4 replaces the internal string pipeline with a two-phase AST architecture:
`parse()` turns the source into a document tree, `transform()` and its passes enrich it, and
pluggable renderers produce HTML or plain text from the same tree (see
[architecture.md](architecture.md)). For the basic use case the method has a new name, and the class
lives in a namespace:

```php
$texy = new Texy\Texy;
$html = $texy->toHtml($text);
```

The names of Texy 3 are gone: configuration code and code extending Texy 3 (handlers, patterns with the old
signature, `HtmlElement`, `Link` and `Image`) have to be moved to the new API. This guide is the map from the old
names to the new ones.

## Requirements

Texy 4 requires PHP 8.4–8.6 (v3 ran on PHP 8.0+). The Latte integration requires Latte 3.1.7.

## Behavioral changes

- **Tabs are no longer expanded to spaces.** A tab counts as one whitespace
  character; `$tabWidth` is removed. Documents that
  mixed tabs and spaces relying on 8-column tab stops may parse differently,
  and tabs inside code blocks survive verbatim.
- **Soft hyphens typed literally in the source are always removed**, as the
  default of `$removeSoftHyphens` did in Texy 3, so the option is gone. An
  intended soft hyphen is written as the entity `&shy;`, which is kept.
- **Input normalization is a static, configuration-free contract:**
  `Texy::normalize()` unifies line endings, removes the leading BOM, control
  characters and trailing whitespace. It is idempotent and runs as the first
  step of every entry point, so it is also suitable for cleaning text before
  storage. The `preprocess()` step is gone.
- **Invalid UTF-8 input throws** `Texy\InvalidArgumentException` immediately
  instead of failing later inside a random regular expression.
- **Headings, TOC and metadata live in the document**, not in module state:
  read them via `$document->getHeadings()` on the AST returned by
  `parse()` and completed by `transform()`. They no longer expire on the next `toHtml()` call.
- **`safeMode()` disables figures and image definitions too**, not just the
  inline image syntax. A figure used to produce `<img>` even in safe mode, and
  so did a lone `[* image.jpg *]` on a line. To allow images in untrusted input,
  call `safeMode($texy, images: true)`.
- **The default HTML changed in a few places.** A code block is
  `<pre class="language-php"><code>` instead of `<pre class="php">`. A figure is
  `<figure>` with `<figcaption>` and no class instead of `<div class="figure">` with `<p>`;
  set `figureTag` and `figureClass` to keep the old form. Images get no base URL unless
  `imageBaseUrl` is set, v3 prepended `images/`.
- **A link written as a bare reference name**, `[name]`, is off by default, as it is easily
  mistaken for text in brackets; `$allowedSyntax['link/reference'] = true` turns it on.
  The predefined references `texy`, `google` and `wikipedia` are gone, add your own with
  `$texy->config->addLinkDefinition()`.
- **The Latte integration no longer converts tabs** in the content of `{texy}` to spaces.

## Configuration properties

Where the Texy 3 properties live now:

| Texy 3 | Texy 4 |
|---|---|
| `$texy->allowedTags` / `allowedClasses` / `allowedStyles` | `$texy->htmlPolicy->...` |
| `$texy->alignClasses` | `$texy->htmlOutput->textAlignClasses`, `verticalAlignClasses` |
| `$texy->obfuscateEmail` | `$texy->htmlOutput->obfuscateEmail` |
| `$texy->nontextParagraph` | `$texy->htmlOutput->imageParagraphTag`, a tag name only |
| `$texy->mergeLines` | `$texy->config->mergeLines` |
| `$texy->longWordsModule->wordLimit` | `$texy->config->hyphenationWordLimit` |
| `$texy->figureModule->tagName`, `class` | `$texy->htmlOutput->figureTag`, `figureClass` |
| `$texy->figureModule->leftClass`, `rightClass`, `$texy->imageModule->leftClass`, `rightClass` | `$texy->htmlOutput->floatClasses['left']`, `['right']`, one class for images and figures |
| `$texy->horizLineModule->classes` | `$texy->htmlOutput->horizontalRuleClasses` |
| `$texy->htmlModule->passComment` | `$texy->htmlOutput->passHtmlComments` |
| `$texy->htmlOutputModule->indent` etc. | `$texy->htmlOutput->indent` etc. |
| `$texy->imageModule->root`, `fileRoot` | `$texy->htmlOutput->imageBaseUrl`, `imageBasePath` |
| `$texy->linkModule->root`, `forceNoFollow`, `shorten` | `$texy->htmlOutput->linkBaseUrl`, `linkNoFollow`, `shortenUrls` |
| `$texy->phraseModule->tags` | `$texy->htmlOutput->phraseTags` |
| `$texy->emoticonModule->class` | `$texy->htmlOutput->emoticonClass` |
| `$texy->urlSchemeFilters`, `Texy::checkURL()` | `$texy->urlPolicy` |

Removed without replacement: `$summary`, `$tabWidth`,
`figureModule->widthDelta`/`requireCaption`,
`tableModule->oddClass`/`evenClass`, `scriptModule->separator`,
`linkModule->imageClass`, `imageModule->linkedRoot`/`defaultAlt`,
`emoticonModule->root`/`fileRoot` (emoticons render as Unicode characters).

Constants were renamed to PascalCase: `Texy::VERSION` is now `Version`,
`HeadingModule::DYNAMIC`/`FIXED` are the enum `Texy\HeadingBalancing`, and the same applies to
`Patterns`. `Texy::ALL` and `NONE` are deprecated, write `true` and `false`. `TexyConfig::$locales` is an
instance property now, so a locale can be added per instance.

## Methods

| Texy 3 | Texy 4 |
|---|---|
| `processTypo($text)` | `applyTypography($text)` |
| `process($text)`, `process($text, true)`, `processLine($text)` | `toHtml($text)`, `toInlineHtml($text)` |
| `parseLine($text)` | `parseInline($text)` |
| `addHandler()` | `addPass()` for the document, `htmlOutput->addRenderer()` for the output, see [custom-handlers.md](custom-handlers.md) |
| `registerLinePattern()`, `registerBlockPattern()` | `addInlineSyntax()`, `addBlockSyntax()`, the name comes first, see [custom-syntax.md](custom-syntax.md) |
| `protect()`, `unprotect()` | return `Texy\Output\Html\Element` or `Raw` from a renderer |
| `checkURL()` | `$texy->urlPolicy->isLinkAllowed()`, `isImageAllowed()` |
| `stringToHtml()`, `stringToText()` | `toHtml()`, `toText()` |
| `getDOM()` | `parse()`, which returns the document |
| `linkModule->addReference($name, $link)`, `imageModule->addReference($name, $image)` | `$texy->config->addLinkDefinition($name, $url)`, `addImageDefinition($name, $url, $width, $height, $alt)` |
| `Helpers::normalize()` | `Texy::normalize()` |

Removed without replacement: `registerPostLine()` (typography runs as a pass),
`invokeHandlers()`, `invokeAroundHandlers()`, `getLinePatterns()`,
`getBlockPatterns()`, `getAllowedProps()`, the `solve()` methods of modules, `linkModule->factoryLink()`,
`Modifier::decorate()`.

Classes of the v3 extension model are gone, use the nodes of `Texy\Nodes` instead: `Texy\HtmlElement`
(now `Texy\Output\Html\Element`), `Texy\HandlerInvocation`, `Texy\Link` (`Nodes\LinkNode`),
`Texy\Image` (`Nodes\ImageNode`), `Texy\BlockParser` and `Texy\LineParser` (`Texy\Parsing\ParseContext`
is what a syntax handler gets).

Two signature notes: v3 `toText()` took no argument and read the last processed
document; v4 `toText(string $text)` converts the given source (use
`renderText($document)` for a parsed document). Static `getDTD()` is gone; the
HTML vocabulary lives in `Texy\Output\Html\Schema`.

The conversion facade follows one grammar: `toHtml()` and `toText()` are
one-shot string conversions; `renderHtml()` and `renderText()` render a document
parsed earlier with `parse()` and completed by `transform()`. Markdown output moved to the separate
[texy/markdown](https://github.com/dg/texy-markdown) package, which also parses
Markdown into this same AST.

## Extension API

This is the hard cut. The v3 event system (element handlers such as
`addHandler('image', ...)`, `beforeParse`/`afterParse` events, `protect()` with
`Content*` constants) is replaced by four registration points with one grammar:

| Task | Texy 4 |
|---|---|
| new inline syntax | `$texy->addInlineSyntax($name, $pattern, $parser)` |
| new block syntax | `$texy->addBlockSyntax($name, $pattern, $parser)` |
| transforming the parsed document | `$texy->addPass($callback)` + `$node->find()` / `$node->traverse()` |
| changing HTML output of a node | `$texy->htmlOutput->addRenderer($callback)` |
| `{{name: ...}}` directives | `$texy->config->addDirective()` / `setDirectiveFallback()` |

See [custom-handlers.md](custom-handlers.md) and
[custom-syntax.md](custom-syntax.md). Ready-made HTML is returned from a
renderer as `Texy\Output\Html\Element` or `Texy\Output\Html\Raw`; the
protection-mark mechanism does not exist anymore.

Custom modules are gone: modules are internal, extend Texy with `addInlineSyntax()`, `addBlockSyntax()`,
`addPass()` and `addRenderer()`.

Registering a syntax under a built-in name replaces that syntax, and a syntax of
the application wins over the built-in ones where the order of registration decides.

## Syntax changes

- Markdown-style fenced code blocks (```` ``` ````) parse natively, including
  the language info string and modifiers.
- An unknown `/--type` block is read as a code block in that language
  (`/--php` works without a handler).
- The `|##` table caption syntax is back.
- Image syntax with `|` or `||` inside brackets (`[* small.jpg | big.jpg *]`)
  is deprecated, as are emoticon image files and modifiers/labels in link
  definitions - all still work and warn.

## `allowedSyntax[]` keys

The string values of `Syntax::*` constants - the keys of `$texy->allowedSyntax` -
are deliberately frozen at their v3 spellings (`'longwords'`, `'script'`,
`'horizline'`, ...), so v3 configuration arrays keep working unchanged. Use
the `Syntax::*` constants in new code; the constant names are the readable
API, the values are a stable wire format.
