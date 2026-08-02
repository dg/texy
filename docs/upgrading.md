# Upgrading from Texy 3 to Texy 4

Texy 4 replaces the internal string pipeline with a two-phase AST architecture:
`parse()` turns the source into a document tree, transform passes enrich it, and
pluggable renderers produce HTML or plain text from the same tree (see
[architecture.md](architecture.md)). For the basic use case nothing changes:

```php
$texy = new Texy;
$html = $texy->process($text);
```

The compatibility policy is one sentence: **v3 configuration code runs with
deprecation notices, v3 extension code is a hard cut with an exception that
points at the successor.**

## Requirements

Texy 4 requires PHP 8.2–8.5 (v3 ran on PHP 8.0+).

## Behavioral changes

- **Tabs are no longer expanded to spaces.** A tab counts as one whitespace
  character; `$tabWidth` is removed and warns when accessed. Documents that
  mixed tabs and spaces relying on 8-column tab stops may parse differently,
  and tabs inside code blocks survive verbatim.
- **Soft hyphens are preserved in text** and stripped only from URL targets;
  the `$removeSoftHyphens` option is removed and warns when accessed.
- **Input normalization is a static, configuration-free contract:**
  `Texy::normalize()` unifies line endings, removes the leading BOM, control
  characters and trailing whitespace. It is idempotent and runs as the first
  step of every entry point, so it is also suitable for cleaning text before
  storage. The `preprocess()` step is gone.
- **Invalid UTF-8 input throws** `Texy\InvalidArgumentException` immediately
  instead of failing later inside a random regular expression.
- **Headings, TOC and metadata live in the document**, not in module state:
  read them via `HeadingNode::collectFrom($document)` on the AST returned by
  `parse()`. They no longer expire on the next `process()` call.

## Configuration properties

Old locations keep working and emit `E_USER_DEPRECATED` pointing at the new
home. The mapping (see `Texy\Compat\Legacy` for the authoritative table):

| Texy 3 | Texy 4 |
|---|---|
| `$texy->allowedTags` / `allowedClasses` / `allowedStyles` | `$texy->htmlPolicy->...` |
| `$texy->alignClasses`, `obfuscateEmail`, `nontextParagraph` | `$texy->htmlOutput->...` |
| `$texy->mergeLines` | `$texy->paragraphModule->mergeLines` |
| `$texy->longWordsModule` | `$texy->hyphenationModule` |
| `$texy->figureModule->class` etc. | `$texy->htmlOutput->figureClass` etc. |
| `$texy->horizLineModule->classes` | `$texy->htmlOutput->horizontalRuleClasses` |
| `$texy->htmlModule->passComment` | `$texy->htmlOutput->passHtmlComments` |
| `$texy->htmlOutputModule->indent` etc. | `$texy->htmlOutput->indent` etc. |
| `$texy->imageModule->root` etc. | `$texy->htmlOutput->imageRoot` etc. |
| `$texy->linkModule->root`, `forceNoFollow`, `shorten` | `$texy->htmlOutput->linkRoot`, `linkNoFollow`, `shortenUrls` |
| `$texy->phraseModule->tags` | `$texy->htmlOutput->phraseTags` |
| `$texy->emoticonModule->class` | `$texy->htmlOutput->emoticonClass` |
| `$texy->urlSchemeFilters`, `Texy::checkURL()` | `$texy->urlPolicy` |

Removed without replacement (accessing them warns): `$summary`, `$tabWidth`,
`$removeSoftHyphens`, `figureModule->widthDelta`/`requireCaption`,
`tableModule->oddClass`/`evenClass`, `scriptModule->separator`,
`linkModule->imageClass`, `imageModule->linkedRoot`/`defaultAlt`,
`emoticonModule->root`/`fileRoot` (emoticons render as Unicode characters).

Constants were renamed to PascalCase: `Texy::VERSION`/`ALL`/`NONE` are now
`Version`/`All`/`None`, `HeadingModule::DYNAMIC`/`FIXED` are `Dynamic`/`Fixed`
(the old names still work via `#[\Deprecated]`), and the same pattern applies
across `Patterns` and module constants. `TypographyModule::$locales` is an
instance property now, so a locale can be added per instance.

## Methods

Emulated with a deprecation notice:

| Texy 3 | Texy 4 |
|---|---|
| `processTypo($text)` | `applyTypography($text)` |
| `processLine($text)` | `process($text, singleLine: true)` |

Removed; calling them throws a `LogicException` naming the successor:
`addHandler()`, `registerLinePattern()`, `registerBlockPattern()`,
`registerPostLine()`, `protect()`, `unprotect()`, `checkURL()`,
`stringToHtml()`, `stringToText()`, `getDOM()`, `invokeHandlers()`,
`invokeAroundHandlers()`, `getLinePatterns()`, `getBlockPatterns()`.

Two signature notes: v3 `toText()` took no argument and read the last processed
document; v4 `toText(string $text)` converts the given source (use
`renderText($document)` for a parsed document). Static `getDTD()` is gone; the
HTML vocabulary lives in `Texy\Output\Html\Schema`. `addModule()` returns
`void` instead of `$this`.

The conversion facade follows one grammar: `process()` and `toText()` are
one-shot string conversions; `renderHtml()` and `renderText()` render a document
parsed earlier with `parse()`. Markdown output moved to the separate
[texy/markdown](https://github.com/dg/texy-markdown) package, which also parses
Markdown into this same AST.

## Extension API

This is the hard cut. The v3 event system (element handlers such as
`addHandler('image', ...)`, `beforeParse`/`afterParse` events, `protect()` with
`Content*` constants) is replaced by four registration points with one grammar:

| Task | Texy 4 |
|---|---|
| new inline syntax | `$texy->addLineSyntax($name, $pattern, $parser)` |
| new block syntax | `$texy->addBlockSyntax($name, $pattern, $parser)` |
| transforming the parsed document | `$texy->addPass($callback)` + `NodeHelpers` / `NodeTraverser` |
| changing HTML output of a node | `$texy->htmlOutput->addRenderer($callback)` |
| `{{name: ...}}` directives | `$texy->directiveModule->addDirective()` / `setFallback()` |

See [custom-handlers.md](custom-handlers.md) and
[custom-syntax.md](custom-syntax.md). Ready-made HTML is returned from a
renderer as `Texy\Output\Html\Element` or `Texy\Output\Html\Raw`; the
protection-mark mechanism does not exist anymore.

Custom modules: the `Module::beforeParse(string &$text)` hook is now
`beforeParse(): void`. No built-in module ever mutated the text; the hook is
for per-parse pattern registration and state refresh.

Registering a syntax under a built-in name replaces that syntax for the
lifetime of the instance - module re-registration never overwrites a consumer
registration.

## Syntax changes

- Markdown-style fenced code blocks (```` ``` ````) parse natively, including
  the language info string and modifiers.
- An unknown `/--type` block is read as a code block in that language
  (`/--php` works without a handler).
- The `|##` table caption syntax is back.
- Image syntax with `|` or `||` inside brackets (`[* small.jpg | big.jpg *]`)
  is deprecated, as are emoticon image files and modifiers/labels in link
  definitions - all still work and warn.

## `allowed[]` keys

The string values of `Syntax::*` constants - the keys of `$texy->allowed` -
are deliberately frozen at their v3 spellings (`'longwords'`, `'script'`,
`'horizline'`, ...), so v3 configuration arrays keep working unchanged. Use
the `Syntax::*` constants in new code; the constant names are the readable
API, the values are a stable wire format.
