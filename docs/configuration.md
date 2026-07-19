# Configuration

Texy is configured through **public properties** in three places, following the semantic/presentation split ([architecture.md](architecture.md)):

```php
$texy = new Texy\Texy;
$texy->allowed['emoticon'] = true;             // Texy: syntax + security
$texy->typographyModule->locale = 'en';        // module: semantic configuration
$texy->htmlOutput->imageRoot = '/images/';  // generator: presentation
$texy->htmlOutput->lineWrap = 120;       // output formatting
```

Reading or writing the pre-4.0 property locations (e.g. `$texy->imageModule->root`) still works through deprecated `__get`/`__set` bridges that trigger `E_USER_DEPRECATED`.

## The Texy class

### $allowed

`array<string, bool>` controlling which syntaxes are active. Entries are created automatically when patterns are registered (default `true` unless the module sets otherwise – e.g. `emoticon`, `phrase/ins`, `phrase/del`, `phrase/sup`, `phrase/sub` default to `false`). Syntax IDs are catalogued as constants on `Texy\Syntax`; the complete list is in [syntax.md](syntax.md).

```php
$texy->allowed['image'] = false;         // disable images
$texy->allowed['phrase/ins'] = true;     // enable ++inserted++ text
$texy->allowed['typography'] = false;    // disable the typography pass
```

The array is consulted once at the start of `process()`; changing it during processing has no effect.

### $htmlPolicy->allowedClasses, $htmlPolicy->allowedStyles

Whitelists for CSS classes/IDs and inline style properties usable in [modifiers](modifiers.md). Default `Texy::All`.

```php
$texy->htmlPolicy->allowedClasses = Texy\Texy::None;          // no classes/IDs
$texy->htmlPolicy->allowedClasses = ['highlight', '#main'];   // whitelist; IDs prefixed with #
$texy->htmlPolicy->allowedStyles = ['color'];
```

### $urlPolicy

URL scheme security policy (`Texy\UrlPolicy`): a regex of allowed link schemes and one for image schemes. A null pattern means no restriction. Only absolute URLs with a scheme are checked; scheme-less (relative) URLs always pass.

```php
$texy->urlPolicy->linkPattern = '~https?:|ftp:|mailto:~A';
$texy->urlPolicy->imagePattern = '~https?:~A';
```

### Other properties

```php
$texy->mergeLines = true;          // join consecutive lines into one paragraph
$texy->tabWidth = 8;               // tab → spaces conversion width
$texy->removeSoftHyphens = true;   // strip U+00AD from input (deprecated)
```

### Results

```php
$html = $texy->process($text);     // parse + render HTML
$ast = $texy->parse($text);        // the Nodes\DocumentNode tree
$plain = $texy->toText($text);     // plain-text rendition
Texy\Nodes\HeadingNode::collectFrom($ast);          // headings in document order
Texy\Nodes\HeadingNode::collectFrom($ast)[0]?->tocTitle; // document title (for <title>)
```

## The HTML generator ($texy->htmlOutput)

`Output\Html\Config` holds everything that shapes only the HTML output:

### $allowedTags

Controls which HTML tags may pass through from the input. Default: an array whitelist of the common HTML tags (inline, void, block), each with all attributes.

```php
$texy->htmlPolicy->allowedTags = Texy\Texy::All;    // any tag whatsoever
$texy->htmlPolicy->allowedTags = Texy\Texy::None;   // no HTML tags at all
$texy->htmlPolicy->allowedTags = [
    'strong' => [],                     // <strong> without attributes
    'a' => ['href', 'title'],           // <a> with these attributes only
    'img' => Texy\Texy::All,            // <img> with any attributes
];
```

The whitelist applies both to tags written in the input (disallowed ones become visible text) and to attributes from modifiers.

### Other generator properties

```php
$g = $texy->htmlOutput;
$g->alignClasses['left'] = 'text-left'; // alignment modifiers → classes instead of inline styles
$g->obfuscateEmail = true;              // obfuscate e-mail addresses against bots
$g->nontextParagraph = 'div';           // wrapper for image-only paragraphs; string or Element
$g->shortenUrls = true;                 // shorten displayed autolink URLs
$g->emoticonClass = null;               // CSS class wrapping emoticons (<span>)
$g->passHtmlComments = true;            // keep HTML comments in output
$g->linkRoot = null;                    // prefix for relative link URLs
$g->linkNoFollow = false;               // rel="nofollow" on absolute links
$g->imageRoot = 'images/';              // URL prefix for images
$g->imageFileRoot = null;               // filesystem path for size autodetection
$g->imageLeftClass = null;              // class for left-floating images
$g->imageRightClass = null;
$g->figureTagName = 'div';              // 'figure' produces <figure>/<figcaption>
$g->figureClass = 'figure';             // CSS class of the figure wrapper
$g->figureLeftClass = null;             // classes for floated figures
$g->figureRightClass = null;            //   (fall back to alignClasses or inline style)
$g->horizontalRuleClasses = ['-' => null, '*' => null];  // CSS class per HR type
$g->phraseTags['phrase/strong'] = 'b';  // change the tag a phrase syntax renders to
```

## Output formatting ($texy->htmlOutput)

Formatting options live on the generator facade:

```php
$texy->htmlOutput->indent = true;      // indent output
$texy->htmlOutput->baseIndent = 0;     // base indentation level
$texy->htmlOutput->lineWrap = 80;      // maximum line width (0 = no wrapping)
$texy->htmlOutput->preserveSpaces = ['textarea', 'pre', 'script', 'code', 'samp', 'kbd'];
```

## Modules (semantic configuration)

### HeadingModule

```php
$texy->headingModule->top = 1;                // level of the top heading (1..6)
$texy->headingModule->generateID = false;     // autogenerate id attributes
$texy->headingModule->idPrefix = 'toc-';      // prefix for generated IDs
$texy->headingModule->moreMeansHigher = true; // surrounded: more #'s = higher heading
$texy->headingModule->balancing = Texy\Modules\HeadingModule::Dynamic; // or ::Fixed
$texy->headingModule->levels = ['#' => 0, '*' => 1, '=' => 2, '-' => 3]; // used with Fixed
```

With `Dynamic` balancing the most important heading found becomes `$top`; with `Fixed` each underline character maps to a fixed level via `$levels` (level = `$levels[$char] + $top`).

The results are facts about the document, so they are read from the AST, not from the module:

```php
$document = $texy->parse($text);
$headings = Texy\Nodes\HeadingNode::collectFrom($document); // document order, texysource excluded
$title = $headings[0]?->tocTitle;                           // document title
foreach ($headings as $heading) {
	echo $heading->level, $heading->tocTitle, $heading->modifier?->id;
}
```

`HeadingModule::$title` and `$TOC` still work but are deprecated: they hold only the last parse's results, while the AST carries them for as long as you hold the document.

### PhraseModule

```php
$texy->phraseModule->linksAllowed = true;   // allow ":url" links attached to phrases
```

### LinkReferenceModule / ImageModule references

```php
$texy->linkModule->addDefinition('example', 'https://example.com');
$texy->imageModule->addDefinition('logo', 'logo.png', 100, 50, 'Logo');
```

User definitions persist across `process()` calls; definitions in the document override them.

### TypographyModule

```php
$texy->typographyModule->locale = 'cs';   // 'cs', 'en', 'fr', 'de', 'pl'
```

The locale determines quote characters (`TypographyModule::$locales`): cs/de „…“ ‚…‘, en “…” ‘…’, fr «…» ‹…›, pl „…” ‚…’. Unknown locales fall back to `en`.

### HyphenationModule

```php
$texy->longWordsModule->wordLimit = 20;   // minimal length of a word to hyphenate
```

### EmoticonModule

Disabled by default (`$allowed['emoticon'] = false`).

```php
$texy->emoticonModule->icons = [          // emoticon => replacement character
    ':-)' => '🙂', ':-(' => '☹', ';-)' => '😉', ':-D' => '😁',
    '8-O' => '😮', '8-)' => '😄', ':-?' => '😕', ':-x' => '😶',
    ':-P' => '😛', ':-|' => '😐',
];
```

Replacements are rendered as text (wrapped in a `<span>` when `$htmlOutput->emoticonClass` is set). Image-file emoticons are no longer supported – a replacement containing `.` triggers a deprecation notice.

### ListModule

```php
$texy->listModule->bullets;   // definitions of bullet/numbering styles
```

Default keys: `*`, `-`, `+` (unordered), `1.`, `1)`, `I.`, `I)`, `a)`, `A)` (ordered; roman and alpha variants set `list-style-type`).

## Configurator presets

`Texy\Configurator` (`src/Texy/Configurator.php`) is a static class with prepared configurations.

### safeMode()

For processing **untrusted content** (comments, forums):

```php
Texy\Configurator::safeMode($texy);
```

Exactly what it does:

- `$htmlPolicy->allowedClasses = Texy::None` – no classes or IDs,
- `$htmlPolicy->allowedStyles = Texy::None` – no inline styles,
- `$htmlPolicy->allowedTags = Configurator::$safeTags` – only `a[href,title]`, `abbr[title]`, `b`, `br`, `cite`, `code`, `em`, `i`, `strong`, `sub`, `sup`, `q`, `small`,
- URL scheme filters: links `~https?:|ftp:|mailto:~A`, images `~https?:~A`,
- `$allowed['image'] = false` – no images (note: the `figure` syntax is *not* disabled by safeMode; combine with `disableImages()` if needed),
- `$allowed['link/definition'] = false` – no reference definitions,
- `$allowed['html/comment'] = false` – no HTML comments,
- `$htmlOutput->linkNoFollow = true` – `rel="nofollow"` on external links.

Because sanitization runs in the transform phase, safe mode protects every output format, including Markdown.

### disableLinks()

Disables `link/email`, `link/url`, `link/definition`, sets `phraseModule->linksAllowed = false`, and removes `a` from `allowedTags`.

### disableImages()

Disables `image`, `figure`, `image/definition` and removes `img`, `object`, `embed`, `applet` from `allowedTags`.

## Security

Texy's protections against common attacks are activated by safe mode – enable it whenever you process untrusted input.

**XSS.** In safe mode Texy validates HTML (removing disallowed tags and attributes, including event handlers like `onclick`), filters URLs to safe schemes (blocking `javascript:` and `data:` URLs – dangerous `<a href>`/`<img src>` drop the whole tag), and escapes text content properly:

```texy
<script>alert('XSS')</script>          → shown as text
<img src=x onerror="alert('XSS')">    → shown as text
"click":javascript:alert('XSS')       → link not created
```

**URL validation** applies to all links and images via `$urlPolicy` (see above). Only absolute URLs with a scheme are checked; relative URLs always pass.

Recommended practice for user content:

```php
function processComment(string $userInput): string
{
    $texy = new Texy\Texy;
    Texy\Configurator::safeMode($texy);
    $texy->allowed['link/url'] = false;   // optionally: no autolinks
    $texy->allowed['html/tag'] = false;   // optionally: no HTML at all
    return $texy->process($userInput);
}
```
