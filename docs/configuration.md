# Configuration

Texy is configured through **public properties** in three places, following the semantic/presentation split ([architecture.md](architecture.md)):

```php
$texy = new Texy\Texy;
$texy->allowedSyntax['emoticon'] = true;             // Texy: syntax + security
$texy->config->locale = 'en';        // module: semantic configuration
$texy->htmlOutput->imageBaseUrl = '/images/';  // generator: presentation
$texy->htmlOutput->lineWrap = 120;       // output formatting
```

A mistyped option is left to PHP and to static analysis, as elsewhere: writing to an undeclared property is deprecated and reading it warns.

## The Texy class

### $allowedSyntax

`array<string, bool>` controlling which syntaxes are active. Entries are created automatically when patterns are registered (default `true` unless the module sets otherwise – e.g. `emoticon`, `phrase/ins`, `phrase/del`, `phrase/sup`, `phrase/sub` default to `false`). Syntax IDs are catalogued as constants on `Texy\Syntax`; the complete list is in [syntax.md](syntax.md).

```php
$texy->allowedSyntax['image'] = false;         // disable images
$texy->allowedSyntax['phrase/ins'] = true;     // enable ++inserted++ text
$texy->config->typography = false;    // disable the typography pass
```

The array is consulted once at the start of `toHtml()`; changing it during processing has no effect.

### $htmlPolicy->allowedTags

Controls which HTML tags may pass through from the input. Default: an array whitelist of the common HTML tags (inline, void, block), each with all attributes.

```php
$texy->htmlPolicy->allowedTags = true;    // any tag whatsoever
$texy->htmlPolicy->allowedTags = false;   // no HTML tags at all
$texy->htmlPolicy->allowedTags = [
    'strong' => [],                     // <strong> without attributes
    'a' => ['href', 'title'],           // <a> with these attributes only
    'img' => true,                      // <img> with any attributes
];
```

The whitelist applies both to tags written in the input (disallowed ones become visible text) and to attributes from modifiers. The key `'*'` lists attributes acceptable on any element, unioned with the per-element list.

Attributes are not validated against a content model: Texy keeps whatever `$allowedTags`
permits and leaves validity to the author – HTML5 has no such fixed model, and `data-*`,
`aria-*` and framework attributes would all have to be enumerated. To restrict attributes,
list them:

```php
$texy->htmlPolicy->allowedTags['th'] = ['colspan', 'rowspan'];
```

This is about validity, not safety: safe mode escapes a disallowed tag whole, exactly as it did in Texy 3.

### $htmlPolicy->allowedClasses, $htmlPolicy->allowedStyles

Whitelists for CSS classes/IDs and inline style properties usable in [modifiers](modifiers.md). Default `true`.

```php
$texy->htmlPolicy->allowedClasses = false;          // no classes/IDs
$texy->htmlPolicy->allowedClasses = ['highlight', '#main'];   // whitelist; IDs prefixed with #
$texy->htmlPolicy->allowedStyles = ['color'];
```

### $urlPolicy

URL scheme security policy (`Texy\UrlPolicy`): a regex of allowed link schemes and one for image schemes. A null pattern means no restriction. Only absolute URLs with a scheme are checked; scheme-less (relative) URLs always pass. A rejected link keeps its text. A rejected image stays as source text in Texy syntax; an image node from elsewhere (a custom syntax, another parser) renders as its alternative text. The policy is applied in the transform phase, so renderer handlers and other output formats see the document after it.

```php
$texy->urlPolicy->linkPattern = '~https?:|ftp:|mailto:~A';
$texy->urlPolicy->imagePattern = '~https?:~A';
```

### Other properties

```php
$texy->config->mergeLines = true;  // join consecutive lines into one paragraph
$texy->tabWidth = 8;               // tab → spaces conversion width
```

### Results

```php
$html = $texy->toHtml($text);     // parse + transform + render HTML
$ast = $texy->parse($text);        // the syntax tree, a Nodes\DocumentNode
$texy->transform($ast);            // turns it into the document, in place
$html = $texy->renderHtml($ast);   // render a document transformed earlier
$plain = $texy->toText($text);     // plain-text rendition
$ast->getHeadings();          // headings in document order
$ast->getHeadings()[0]?->getTitle(); // document title (for <title>)
```

## The HTML generator ($texy->htmlOutput)

`Output\Html\Config` holds everything that shapes only the HTML output:

### Generator properties

```php
$g = $texy->htmlOutput;
$g->textAlignClasses['left'] = 'text-left';  // .< and friends → a class instead of style="text-align"
$g->verticalAlignClasses['top'] = 'top';     // .^ and friends → a class instead of style="vertical-align"
$g->floatClasses['left'] = 'aside-left';     // a floated image or figure → a class instead of style="float"
$g->obfuscateEmail = true;              // obfuscate e-mail addresses against bots
$g->imageParagraphTag = 'div';          // element in place of <p> for a paragraph of images only
$g->shortenUrls = true;                 // shorten displayed autolink URLs
$g->emoticonClass = null;               // CSS class wrapping emoticons (<span>)
$g->passHtmlComments = true;            // keep HTML comments in output
$g->linkBaseUrl = null;                 // prepended to relative link URLs
$g->linkNoFollow = false;               // rel="nofollow" on absolute links
$g->imageBaseUrl = null;                // prepended to relative image URLs
$g->imageBasePath = null;              // directory of the image files, sizes are read from them
$g->figureTag = 'figure';               // <figure>/<figcaption>; another element puts the caption in <p>
$g->figureClass = null;                 // class of every figure, a floated one gets its float class too
$g->horizontalRuleClasses = ['-' => null, '*' => null];  // CSS class per HR type
$g->phraseTags['strong'] = 'b';  // the tag of a phrase by what it means (PhraseType values), 'code' for inline code
```

## Output formatting ($texy->htmlOutput)

Formatting options live on the generator facade:

```php
$texy->htmlOutput->indent = true;      // indent output
$texy->htmlOutput->baseIndent = 0;     // base indentation level
$texy->htmlOutput->lineWrap = 80;      // maximum line width (0 = no wrapping)
$texy->htmlOutput->preserveSpaces = ['textarea', 'pre', 'script', 'code', 'samp', 'kbd'];
```

## `$texy->config`: what the document means

The configuration objects are constant: `$texy->config = …` fails, their values can be changed. Passes read them when they run, so a value changed by a directive handler applies to that document. Values are validated at the start of parsing and transformation.

### Headings

```php
$texy->config->headingTop = 1;                // level of the top heading (1..6)
$texy->config->headingIds = false;     // autogenerate id attributes
$texy->config->headingIdPrefix = 'toc-';      // prefix for generated IDs
$texy->config->headingMoreMeansHigher = true; // surrounded: more #'s = higher heading
$texy->config->headingBalancing = Texy\HeadingBalancing::Dynamic; // or ::Fixed
$texy->config->headingLevels = ['#' => 1, '*' => 2, '=' => 3, '-' => 4]; // levels of underlined headings
```

`HeadingNode::$level` is the absolute level. With `Dynamic` balancing the order of heading styles in the document gives the levels from 1; with `Fixed` `$headingLevels` gives the level of an underline character and the number of `#` the one of a surrounded heading. Then every level is shifted by `$headingTop - 1`, and a level beyond 6 becomes `<h6>`. Markdown has no balancing: its levels are written and only the shift applies.

The results are facts about the document, so they are read from the AST, not from the module:

```php
$document = $texy->parse($text);
$texy->transform($document);                             // balances levels, generates IDs
$headings = $document->getHeadings(); // document order, texysource excluded
$title = $headings[0]?->getTitle();                      // document title
foreach ($headings as $heading) {
	echo $heading->level, $heading->getTitle(), $heading->id;
}
```

`HeadingNode::$id` is the effective anchor: an explicit `.[#id]` written by the author, or the generated one. A generated ID is written by the engine, so it survives safe mode; an author's `.[#id]` goes through the `allowedClasses` policy like any other modifier, and one the policy rejects gives way to a generated ID.

### Phrases

```php
$texy->config->phraseLinks = true;   // allow ":url" links attached to phrases
```

### References

```php
$texy->config->addLinkDefinition('example', 'https://example.com');
$texy->config->addImageDefinition('logo', 'logo.png', 100, 50, 'Logo');
```

User definitions persist across `toHtml()` calls; definitions in the document override them.

### Typography

```php
$texy->config->locale = 'cs';
```

The locale determines quote characters (`$texy->config->locales`), e.g. cs/de „…“ ‚…‘, en “…” ‘…’, fr «…» ‹…›, pl „…” ‚…’. Built in are cs, de, en, fr and pl; a locale that is not a key of `$locales` throws `InvalidArgumentException` at the start of processing. Adding a locale is a matter of extending the array:

```php
// opening and closing double quotes, then the single ones used inside them
$texy->config->locales['nl'] = ["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"];
```

### Hyphenation

```php
$texy->config->hyphenationWordLimit = 20;   // minimal length of a word to hyphenate
```

### Emoticons

Disabled by default (`$allowedSyntax['emoticon'] = false`).

```php
$texy->config->emoticons = [          // emoticon => replacement character
    ':-)' => '🙂', ':-(' => '☹', ';-)' => '😉', ':-D' => '😁',
    '8-O' => '😮', '8-)' => '😄', ':-?' => '😕', ':-x' => '😶',
    ':-P' => '😛', ':-|' => '😐',
];
```

Replacements are rendered as text (wrapped in a `<span>` when `$htmlOutput->emoticonClass` is set). A replacement containing `.`, an image file as Texy 3 took it, triggers a deprecation notice.

### Lists

```php
$texy->config->listBullets;   // definitions of bullet/numbering styles
```

Default keys: `*`, `-`, `+` (unordered), `1.`, `1)`, `I.`, `I)`, `a)`, `A)` (ordered; roman and alpha variants set `list-style-type`).

## Configurator presets

`Texy\Configurator` (`src/Configurator.php`) is a static class with prepared configurations.

### safeMode()

For processing **untrusted content** (comments, forums):

```php
Texy\Configurator::safeMode($texy);
```

Exactly what it does:

- `$htmlPolicy->allowedClasses = false` – no classes or IDs,
- `$htmlPolicy->allowedStyles = false` – no inline styles,
- `$htmlPolicy->allowedTags = Configurator::SafeTags + ['*' => ['title', 'lang', 'dir']]` – only `a[href,title]`, `abbr[title]`, `b`, `br`, `cite`, `code`, `em`, `i`, `strong`, `sub`, `sup`, `q`, `small`,
- URL scheme filters: links `~https?:|ftp:|mailto:~A`, images `~https?:~A`,
- `disableImages()` – no images, figures or image definitions; `safeMode($texy, images: true)` keeps images and figures, the definitions stay disabled,
- `$allowedSyntax['link/definition'] = false` – no reference definitions,
- `$allowedSyntax['html/comment'] = false` – no HTML comments,
- `$htmlOutput->linkNoFollow = true` – `rel="nofollow"` on external links.

Because sanitization runs in the transform phase, safe mode protects every output format.

### disableLinks()

Disables `link/email`, `link/url`, `link/definition`, sets `config->phraseLinks = false`, and removes `a` from `allowedTags`.

### disableImages()

Disables `image`, `figure`, `image/definition` and removes `img`, `object`, `embed`, `applet` from `allowedTags`.

## Security

Texy's protections against common attacks are activated by safe mode – enable it whenever you process untrusted input.

**XSS.** In safe mode Texy validates HTML (showing disallowed tags as text and removing disallowed attributes, including event handlers like `onclick`), filters URLs to safe schemes (blocking `javascript:` and `data:` URLs – an `<a href>`/`<img src>` with such a URL is shown as text whole), and escapes text content properly:

```texy
<script>alert('XSS')</script>          → shown as text
<img src=x onerror="alert('XSS')">    → shown as text
"click":javascript:alert('XSS')       → link not created
```

**URL validation** applies to all links and images via `$urlPolicy` (see above). Only absolute URLs with a scheme are checked; relative URLs always pass.

**Errors.** Whatever the text itself causes throws a `Texy\Exception`: invalid UTF-8 is an `InvalidInputException`, a document exhausting a PCRE limit a `RegexpException`. Code processing foreign text catches `Texy\Exception`; an error in the use of the library, such as an invalid configuration or a pattern of a syntax that does not compile, is a `\LogicException`.

Recommended practice for user content:

```php
function processComment(string $userInput): string
{
    $texy = new Texy\Texy;
    Texy\Configurator::safeMode($texy);
    $texy->allowedSyntax['link/url'] = false;   // optionally: no autolinks
    $texy->allowedSyntax['html/tag'] = false;   // optionally: no HTML at all
    return $texy->toHtml($userInput);
}
```
