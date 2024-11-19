# Configuration

Texy is configured through **public properties** of the main `Texy\Texy` class and of its **modules**:

```php
$texy = new Texy\Texy;
$texy->allowedTags = Texy\Texy::NONE;   // main class
$texy->imageModule->root = '/images/';  // module
```

All defaults below are taken from the source code (`src/Texy/Texy.php`, `src/Texy/Modules/*.php`).

## The Texy class

### $allowed

`array<string, bool>` controlling which syntaxes are active. Entries are created automatically when patterns are registered (default `true` unless the module sets otherwise). The complete list of syntax IDs, defaults, and owning modules is in [syntax.md](syntax.md#syntax-overview).

```php
$texy->allowed['image'] = false;         // disable images
$texy->allowed['phrase/ins'] = true;     // enable ++inserted++ text
$texy->allowed['emoticon'] = true;       // enable emoticons
```

The array is consulted once at the start of `process()`; changing it during processing has no effect.

### $allowedTags

Controls which HTML tags may appear in the output (and be written in the input). Default: an array whitelist built by `Texy::initAllowedTags()` – **the common HTML tags (inline, void, and block) each with all their attributes**.

```php
$texy->allowedTags = Texy\Texy::ALL;    // any tag whatsoever
$texy->allowedTags = Texy\Texy::NONE;   // no HTML tags at all
$texy->allowedTags = [
    'strong' => [],                     // <strong> without attributes
    'a' => ['href', 'title'],           // <a> with these attributes only
    'img' => Texy\Texy::ALL,            // <img> with any attributes
];
```

### $allowedClasses

Controls CSS classes and IDs usable in [modifiers](modifiers.md). Default `Texy::ALL`.

```php
$texy->allowedClasses = Texy\Texy::NONE;               // no classes/IDs
$texy->allowedClasses = ['highlight', '#main'];        // whitelist; IDs prefixed with #
```

### $allowedStyles

Controls inline CSS properties usable in modifiers. Default `Texy::ALL`.

```php
$texy->allowedStyles = Texy\Texy::NONE;
$texy->allowedStyles = ['color', 'background-color'];
```

### $alignClasses

Maps alignment modifiers to CSS classes instead of inline styles. Default: all seven keys (`left`, `right`, `center`, `justify`, `top`, `middle`, `bottom`) set to `null`, meaning inline `text-align`/`vertical-align` styles are used.

```php
$texy->alignClasses['left'] = 'text-left';   // .< now produces class="text-left"
```

### $urlSchemeFilters

Regexps checking URL schemes, keyed by `Texy::FILTER_ANCHOR` (links) and `Texy::FILTER_IMAGE` (images). By default the property is not initialized and no filtering occurs. A URL with a scheme passes only if it matches the filter; scheme-less URLs always pass (`Texy::checkURL()`).

```php
$texy->urlSchemeFilters[Texy\Texy::FILTER_ANCHOR] = '#https?:|ftp:|mailto:#A';
$texy->urlSchemeFilters[Texy\Texy::FILTER_IMAGE] = '#https?:#A';
```

### Other properties

```php
$texy->mergeLines = true;          // join consecutive lines into one paragraph
$texy->tabWidth = 8;               // tab → spaces conversion width
$texy->obfuscateEmail = true;      // obfuscate e-mail addresses against bots
$texy->removeSoftHyphens = true;   // strip U+00AD from input
$texy->nontextParagraph = 'div';   // element for paragraphs without text (e.g. image-only); string or HtmlElement
```

### Read-only results

After `process()`:

```php
$texy->headingModule->title;   // first heading text (for <title>)
$texy->headingModule->TOC;     // table of contents: [{el, level, type, title}]
$texy->summary['links'];       // list of used link URLs
$texy->summary['images'];      // list of used image URLs
$texy->getDOM();               // the parsed HtmlElement tree
$texy->toText();               // plain-text rendition
```

## Modules

Properties marked *(deprecated)* carry `@deprecated` in the code.

### HeadingModule

```php
$texy->headingModule->top = 1;                // level of the top heading (1..6)
$texy->headingModule->generateID = false;     // autogenerate id attributes
$texy->headingModule->idPrefix = 'toc-';      // prefix for generated IDs
$texy->headingModule->moreMeansHigher = true; // surrounded: more #'s = higher heading
$texy->headingModule->balancing = Texy\Modules\HeadingModule::DYNAMIC; // or ::FIXED
$texy->headingModule->levels = ['#' => 0, '*' => 1, '=' => 2, '-' => 3]; // used with FIXED
```

With `DYNAMIC` balancing the most important heading found becomes `$top`; with `FIXED` each underline character maps to a fixed level via `$levels` (level = `$levels[$char] + $top`).

### PhraseModule

```php
$texy->phraseModule->linksAllowed = true;   // allow ":url" links attached to phrases
$texy->phraseModule->tags['phrase/strong'] = 'b';   // change generated tag
```

Default `$tags` map: `phrase/strong` → `strong`, `phrase/em`, `phrase/em-alt`, `phrase/em-alt2` → `em`, `phrase/ins` → `ins`, `phrase/del` → `del`, `phrase/sup`, `phrase/sup-alt` → `sup`, `phrase/sub`, `phrase/sub-alt` → `sub`, `phrase/span`, `phrase/span-alt`, `phrase/quicklink` → `a`, `phrase/acronym`, `phrase/acronym-alt` → `abbr`, `phrase/code` → `code`, `phrase/quote` → `q`.

### LinkModule

```php
$texy->linkModule->root = null;           // prefix for relative link URLs
$texy->linkModule->forceNoFollow = false; // add rel="nofollow" to external links
$texy->linkModule->shorten = true;        // shorten displayed URLs (autolinks)
$texy->linkModule->imageClass = null;     // (deprecated) class for image links
```

References:

```php
$texy->linkModule->addDefinition('example', 'https://example.com');
$texy->linkModule->getReference('example');   // ?Link
```

### ImageModule

```php
$texy->imageModule->root = 'images/';       // URL prefix for images
$texy->imageModule->fileRoot = null;        // filesystem path for size autodetection
$texy->imageModule->leftClass = null;       // class for left-floating images
$texy->imageModule->rightClass = null;      // class for right-floating images
$texy->imageModule->linkedRoot = 'images/'; // (deprecated) URL prefix for linked big images (`::` shortcut)
$texy->imageModule->defaultAlt = '';        // (deprecated) default alt text
```

References work like in LinkModule via `addDefinition()` / `getReference()`.

### FigureModule

```php
$texy->figureModule->tagName = 'div';      // 'figure' produces <figure>/<figcaption>
$texy->figureModule->class = 'figure';     // CSS class of the wrapper
$texy->figureModule->leftClass = null;     // class for left-floated figures
$texy->figureModule->rightClass = null;
$texy->figureModule->widthDelta = 10;      // (deprecated) width padding added to wrapper; false = off
$texy->figureModule->requireCaption = true;// (deprecated) pattern requires " *** caption"
```

When `leftClass`/`rightClass` are `null`, alignment falls back to `$texy->alignClasses['left'|'right']` or an inline style.

### ListModule

```php
$texy->listModule->bullets;   // definitions of bullet/numbering styles
```

Default keys: `*`, `-`, `+` (unordered), `1.`, `1)`, `I.`, `I)`, `a)`, `A)` (ordered; roman and alpha variants set `list-style-type`). Each value is `[first-item regexp, ordered?, list-style-type, (optional) next-item regexp]`.

### TableModule

```php
$texy->tableModule->oddClass = null;    // (deprecated) class for odd rows
$texy->tableModule->evenClass = null;   // (deprecated) class for even rows
```

### HorizLineModule

```php
$texy->horizLineModule->classes = ['-' => null, '*' => null];  // CSS class per rule type
```

### TypographyModule

```php
$texy->typographyModule->locale = 'cs';   // 'cs', 'en', 'fr', 'de', 'pl'
```

The locale determines quote characters (see `TypographyModule::$locales`): cs/de „…“ ‚…‘, en “…” ‘…’, fr «…» ‹…›, pl „…” ‚…’. Unknown locales fall back to `en`.

### LongWordsModule

```php
$texy->longWordsModule->wordLimit = 20;   // minimal length of a word to hyphenate
```

### EmoticonModule

Disabled by default (`$allowed['emoticon'] = false`).

```php
$texy->emoticonModule->class = null;     // CSS class of the wrapping element
$texy->emoticonModule->icons = [         // emoticon => replacement
    ':-)' => '🙂', ':-(' => '☹', ';-)' => '😉', ':-D' => '😁',
    '8-O' => '😮', '8-)' => '😄', ':-?' => '😕', ':-x' => '😶',
    ':-P' => '😛', ':-|' => '😐',
];
```

Replacements are rendered as text (wrapped in a `<span>` when `$class` is set). Image-file emoticons are no longer supported – a replacement containing `.` triggers a deprecation notice.

### HtmlModule

```php
$texy->htmlModule->passComment = true;   // keep HTML comments in output
```

### HtmlOutputModule

```php
$texy->htmlOutputModule->indent = true;      // indent output
$texy->htmlOutputModule->baseIndent = 0;     // base indentation level
$texy->htmlOutputModule->lineWrap = 80;      // maximum line width
$texy->htmlOutputModule->preserveSpaces = ['textarea', 'pre', 'script', 'code', 'samp', 'kbd'];
```

### ScriptModule

```php
$texy->scriptModule->separator = ',';   // argument separator in {{command: a, b}}
```

## Configurator presets

`Texy\Configurator` (`src/Texy/Configurator.php`) is a static class with prepared configurations.

### safeMode()

For processing **untrusted content** (comments, forums):

```php
Texy\Configurator::safeMode($texy);
```

Exactly what it does:

- `$allowedClasses = Texy::NONE` – no classes or IDs,
- `$allowedStyles = Texy::NONE` – no inline styles,
- `$allowedTags = Configurator::$safeTags` – only `a[href,title]`, `abbr[title]`, `b`, `br`, `cite`, `code`, `em`, `i`, `strong`, `sub`, `sup`, `q`, `small`,
- URL scheme filters: links `#https?:|ftp:|mailto:#A`, images `#https?:#A`,
- `$allowed['image'] = false` – no images (note: the `figure` syntax is *not* disabled by safeMode; combine with `disableImages()` if needed),
- `$allowed['link/definition'] = false` – no reference definitions,
- `$allowed['html/comment'] = false` – no HTML comments,
- `$texy->linkModule->forceNoFollow = true` – `rel="nofollow"` on external links.

### disableLinks()

Disables `link/reference`, `link/email`, `link/url`, `link/definition`, sets `phraseModule->linksAllowed = false`, and removes `a` from `$allowedTags`.

### disableImages()

Disables `image`, `figure`, `image/definition` and removes `img`, `object`, `embed`, `applet` from `$allowedTags`.

## Security

Texy's protections against common attacks are activated by safe mode – enable it whenever you process untrusted input.

**XSS.** In safe mode Texy validates HTML (removing disallowed tags and attributes, including event handlers like `onclick`), filters URLs to safe schemes (blocking `javascript:`, `data:` URLs), and escapes text content properly:

```texy
<script>alert('XSS')</script>          → removed
<img src=x onerror="alert('XSS')">    → removed
"click":javascript:alert('XSS')       → link not created
```

**URL validation** applies to all links and images via `$urlSchemeFilters` (see above). Only absolute URLs with a scheme are checked; relative URLs always pass.

**Tag filtering** via `$allowedTags` applies both to HTML written in the input and to the generated output; disallowed tags are dropped while their textual content is kept.

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
