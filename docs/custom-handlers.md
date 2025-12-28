# Changing the Behavior of Existing Constructs

This chapter covers modifying how **existing** constructs are processed – images, links, code blocks, formatting. To add a **brand-new** markup construct, see [custom-syntax.md](custom-syntax.md).

Typical use cases: make the image syntax `[* youtube:dQw4w9WgXcQ *]` render an embedded player, run code blocks through a syntax highlighter, validate link targets. The tool for this is the **element handler** – a function Texy invokes when processing a given kind of item. All signatures below were verified against the `invokeAroundHandlers()` / `invokeHandlers()` call sites in `src/`.

## Element handlers

Register with `Texy::addHandler($elementName, $callback)`:

```php
$texy->addHandler('image', function(
    Texy\HandlerInvocation $invocation,
    Texy\Image $image,
    ?Texy\Link $link,
) {
    // your logic
});
```

The callback always receives a `Texy\HandlerInvocation` first, followed by element-specific parameters. When Texy processes the element, it builds a `HandlerInvocation` wrapping all handlers registered for that name. **Your handler runs first** (execution order is from the last registered to the first – the module's default handler, registered in its constructor, runs last) and can:

- **delegate** – `return $invocation->proceed();`
- **modify input** – call `proceed()` with modified parameters; they replace the parameters for the rest of the chain (do not pass the invocation itself, it is prepended automatically),
- **modify output** – post-process the value returned by `proceed()`,
- **replace processing entirely** – return its own result without calling `proceed()`,
- **refuse** – return `null` (the construct is left unprocessed).

Valid return values are `Texy\HtmlElement`, `string`, or `null`; anything else throws `UnexpectedValueException`. Calling `proceed()` when no handler remains in the chain throws `RuntimeException` (the module's default handler never calls `proceed()`, so this happens only if you invoke an element that has no default handler). Useful accessors on the invocation: `getTexy()`, `getParser()`.

```php
$texy->addHandler('image', function($invocation, Texy\Image $image, ?Texy\Link $link) {
    $image->modifier->title = 'Modified title';        // 1. adjust input
    $element = $invocation->proceed($image, $link);    // 2. run the chain
    $element->attrs['loading'] = 'lazy';               // 3. adjust output
    return $element;
});
```

## Element reference

### image

Images `[* url *]`. `$link` is set when the image is clickable (`[* img *]:url`).

```php
function(HandlerInvocation $i, Texy\Image $image, ?Texy\Link $link): HtmlElement|string|null
```

### figure

Image with caption `[* img *] *** caption`.

```php
function(HandlerInvocation $i, Texy\Image $image, ?Texy\Link $link, string $content, Texy\Modifier $mod): HtmlElement|string|null
```

### linkReference

Reference links `[ref]`. `$content` is the already-parsed HTML content of the link.

```php
function(HandlerInvocation $i, Texy\Link $link, string $content): HtmlElement|string|null
```

### linkURL / linkEmail

Autodetected URLs and e-mail addresses.

```php
function(HandlerInvocation $i, Texy\Link $link): HtmlElement|string|null
```

### newReference

Fired when `[ref]` uses an undefined reference name. The handler may create a link dynamically or return `null` to reject.

```php
function(HandlerInvocation $i, string $name): HtmlElement|string|null
```

### phrase

All inline formatting. `$phrase` is the syntax name (`phrase/strong`, `phrase/em`, ...); `$content` the inner text; `$link` is set when the phrase carries a `:url` link.

```php
function(HandlerInvocation $i, string $phrase, string $content, Texy\Modifier $mod, ?Texy\Link $link): HtmlElement|string|null
```

### block

Fenced blocks `/--type … \--`. `$blocktype` is prefixed, e.g. `block/code`; `$param` is the text after the type (e.g. language name).

```php
function(HandlerInvocation $i, string $blocktype, string $content, ?string $param, Texy\Modifier $mod): HtmlElement|string|null
```

### paragraph

Every paragraph produced from plain text between blocks.

```php
function(HandlerInvocation $i, string $content, ?Texy\Modifier $mod): HtmlElement|string|null
```

### heading

`$level` is the raw parsed level (before balancing); `$isSurrounded` distinguishes `### x` from underlined headings.

```php
function(HandlerInvocation $i, int $level, string $content, Texy\Modifier $mod, bool $isSurrounded): HtmlElement|string|null
```

### horizline

`$type` is the character sequence used (`---…` or `***…`).

```php
function(HandlerInvocation $i, string $type, Texy\Modifier $mod): HtmlElement|string|null
```

### htmlTag

HTML tags in the input. `$el` carries the tag name and attributes; `$isStart` says whether it is an opening tag; `$forceEmpty` marks self-closing tags.

```php
function(HandlerInvocation $i, Texy\HtmlElement $el, bool $isStart, ?bool $forceEmpty): HtmlElement|string|null
```

### htmlComment

`$content` is the text between `<!--` and `-->`.

```php
function(HandlerInvocation $i, string $content): HtmlElement|string|null
```

### script

Macros `{{command: args}}`. `$args` is the argument list split by `ScriptModule::$separator`; `$raw` the unparsed argument string. Texy has no default rendering for scripts – without a user handler the construct produces nothing, so this is the primary extension point for template-like macros.

```php
function(HandlerInvocation $i, string $command, array $args, ?string $raw): HtmlElement|string|null
```

### emoticon

`$emoticon` is the recognized emoticon (e.g. `:-)`), `$raw` the original text including repeated characters (`:-)))`). Emoticons are off by default (`$texy->allowed['emoticon'] = true`).

```php
function(HandlerInvocation $i, string $emoticon, string $raw): HtmlElement|string|null
```

## Notification events

Registered with the same `addHandler()`, but invoked via `invokeHandlers()`: all handlers always run in registration order, return values are ignored, and the chain cannot be interrupted. Use them for side effects – collecting statistics, logging, modifying the built DOM tree.

| Event | Signature | When |
|---|---|---|
| `beforeParse` | `function(Texy\Texy $texy, string &$text, bool $isSingleLine): void` | after preprocessing, before parsing; `$text` is by reference and may be modified |
| `afterParse` | `function(Texy\Texy $texy, Texy\HtmlElement $dom, bool $isSingleLine): void` | after parsing, before serialization; `$dom` is the document root |
| `beforeBlockParse` | `function(Texy\BlockParser $parser, string &$text): void` | before each block-level parse (including nested ones) |
| `afterList` | `function(Texy\BlockParser $parser, Texy\HtmlElement $el, Texy\Modifier $mod): void` | after a `<ul>`/`<ol>` is built |
| `afterDefinitionList` | `function(Texy\BlockParser $parser, Texy\HtmlElement $el, Texy\Modifier $mod): void` | after a `<dl>` is built |
| `afterTable` | `function(Texy\BlockParser $parser, Texy\HtmlElement $el, Texy\Modifier $mod): void` | after a `<table>` is built |
| `afterBlockquote` | `function(Texy\BlockParser $parser, Texy\HtmlElement $el, Texy\Modifier $mod): void` | after a `<blockquote>` is built |
| `postProcess` | `function(Texy\Texy $texy, string &$s): void` | on the final HTML string (this is where `HtmlOutputModule` well-forms the output) |

## Practical examples

### YouTube embed

```php
$texy->addHandler('image', function($invocation, Texy\Image $image, ?Texy\Link $link) {
    if (str_starts_with($image->URL, 'youtube:')) {
        $id = substr($image->URL, 8);
        $iframe = sprintf(
            '<iframe width="%d" height="%d" src="https://youtube.com/embed/%s" frameborder="0" allowfullscreen></iframe>',
            $image->width ?: 560,
            $image->height ?: 315,
            htmlspecialchars($id),
        );
        $texy = $invocation->getTexy();
        return $texy->protect($iframe, $texy::CONTENT_BLOCK);  // raw HTML must be protected!
    }
    return $invocation->proceed();
});
```

Usage: `[* youtube:dQw4w9WgXcQ 640x360 *]`

### Lightbox gallery wrapper

```php
$texy->addHandler('image', function($invocation, Texy\Image $image, ?Texy\Link $link) {
    $element = $invocation->proceed();
    if (isset($image->modifier->classes['gallery'])) {   // classes are array keys
        $wrapper = new Texy\HtmlElement('div');
        $wrapper->attrs['class'][] = 'lightbox-item';
        $wrapper->attrs['data-src'] = $image->URL;
        $wrapper->add($element);
        return $wrapper;
    }
    return $element;
});
```

Usage: `[* image.jpg .[gallery] *]`

### Link domain whitelist

```php
$allowedDomains = ['example.com', 'trusted.org'];

$texy->addHandler('linkURL', function($invocation, Texy\Link $link) use ($allowedDomains) {
    $host = parse_url($link->URL, PHP_URL_HOST);
    if ($host && !in_array($host, $allowedDomains, true)) {
        return null;   // refuse – the URL stays as plain text
    }
    return $invocation->proceed();
});
```

### Syntax highlighting

```php
$texy->addHandler('block', function($invocation, string $blocktype, string $content, ?string $param, Texy\Modifier $mod) {
    if ($blocktype !== 'block/code') {
        return $invocation->proceed();
    }

    $highlighted = (new MyHighlighter)->highlight($content, $param);
    $texy = $invocation->getTexy();

    $el = new Texy\HtmlElement('pre');
    $mod->decorate($texy, $el);
    $el->attrs['class'][] = 'language-' . $param;
    $el->create('code')->add($texy->protect($highlighted, $texy::CONTENT_MARKUP));
    return $el;
});
```

### Lazy loading via afterParse

```php
$texy->addHandler('afterParse', function(Texy\Texy $texy, Texy\HtmlElement $dom, bool $isSingleLine) {
    $walk = function(Texy\HtmlElement $el) use (&$walk) {
        foreach ($el->getChildren() as $child) {
            if ($child instanceof Texy\HtmlElement) {
                if ($child->getName() === 'img') {
                    $child->attrs['loading'] = 'lazy';
                }
                $walk($child);
            }
        }
    };
    $walk($dom);
});
```

### Collecting statistics

```php
$stats = [];

$texy->addHandler('beforeParse', function($texy, &$text, $isSingleLine) use (&$stats) {
    $stats = ['images' => 0, 'headings' => 0];
});
$texy->addHandler('image', function($invocation, $image, $link) use (&$stats) {
    $stats['images']++;
    return $invocation->proceed();
});
$texy->addHandler('heading', function($invocation, $level, $content, $mod, $isSurrounded) use (&$stats) {
    $stats['headings']++;
    return $invocation->proceed();
});
```

## Helper classes

### Texy\Image

```php
$image->URL;        // ?string – image path
$image->linkedURL;  // ?string – link target when the image is clickable
$image->width;      // ?int
$image->height;     // ?int
$image->asMax;      // bool – dimensions are maximums
$image->modifier;   // Modifier
$image->name;       // ?string – reference name
```

### Texy\Link

```php
$link->URL;        // ?string – target URL (after root/normalization)
$link->raw;        // string – original URL text
$link->modifier;   // Modifier
$link->type;       // Link::COMMON | Link::BRACKET | Link::IMAGE
$link->label;      // ?string – link text (for references)
$link->name;       // ?string – reference name
```

For `Texy\HtmlElement` see [html-element.md](html-element.md); for `Texy\Modifier` see [modifiers.md](modifiers.md).
