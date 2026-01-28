# Modifiers

Modifiers let the author attach attributes, CSS classes, styles, and alignment to elements without writing HTML. They are the mechanism behind the `.(title)[class #id]{style}<>` suffixes seen throughout the Texy syntax.

## Syntax

A modifier is a dot followed by one or more parts in round, square, or curly brackets, plus alignment shortcuts:

```
.(title)[class1 class2 #id]{style: value; attr: value}<>^
```

It is written before or at the end of the construct it applies to, e.g. `**text .(Important)[highlight]{color:red}**`.

| Part | Meaning |
|---|---|
| `(text)` | `title` attribute (or `alt` text for images). A literal `)` can be escaped as `\)`. HTML entities inside are decoded. |
| `[word word #id]` | CSS classes separated by spaces; an ID prefixed with `#`. The last `#id` wins if repeated. |
| `{prop: value; ...}` | CSS styles in standard `property:value` form separated by semicolons. Names from a known list of HTML attributes (`href`, `target`, `src`, `alt`, `rel`...) and any `data-*`/`aria-*` name are treated as **HTML attributes** instead – e.g. `{target: _blank}` sets the `target` attribute; everything else is a CSS style. |
| `<` `>` `=` `<>` | horizontal alignment: left, right, justify, center |
| `^` `-` `_` | vertical alignment: top, middle, bottom |

Parts may appear in any order and any may be omitted: `.[highlight]`, `.(Note)`, `.{color:blue}` are all valid modifiers.

Not every construct accepts every part. Three pattern building blocks in `src/Texy/Patterns.php` define what a given syntax accepts:

- `Patterns::MODIFIER` – title, classes/ID, styles (typical for inline phrases),
- `Patterns::MODIFIER_H` – additionally horizontal alignment (paragraphs, headings, lists, blocks),
- `Patterns::MODIFIER_HV` – additionally vertical alignment (tables and cells).

## The Modifier class

`Texy\Modifier` (`src/Texy/Modifier.php`) parses a modifier string and stores its parts. Instances are created by syntax handlers through the static factory `Modifier::parse($s)`, passing the modifier text captured by the pattern's regex group. (The instance method `setProperties()` does the same but is deprecated.)

Public properties:

```php
$mod->id;       // ?string – HTML id
$mod->classes;  // array<string, bool> – class names as keys
$mod->styles;   // array<string, string> – CSS property => value
$mod->attrs;    // array<string, string> – HTML attributes that are not styles
$mod->hAlign;   // ?string – 'left', 'right', 'center', 'justify'
$mod->vAlign;   // ?string – 'top', 'middle', 'bottom'
$mod->title;    // ?string – title / alt text (unescaped)
```

Note that `$classes` is an associative array with the class names as *keys* (`isset($mod->classes['gallery'])`), not a plain list.

## Applying to elements: decorate()

`Modifier::decorate(Texy $texy, HtmlElement $el): HtmlElement` applies the modifier to an element, respecting the Texy configuration. In order:

1. **Attributes** (`$mod->attrs`) are copied only if allowed: with `$texy->allowedTags === Texy::ALL` all of them; with an array configuration only those listed for the element's tag (or all if the tag maps to `Texy::ALL`).
2. **Title** is always applied if set; the text first passes through `TypographyModule::postLine()`, so quotes and dashes in titles are typographically corrected.
3. **Classes and ID** are filtered by `$texy->allowedClasses`: with `ALL` everything is applied; with a whitelist, only listed classes, and the ID only when the whitelist contains `'#' . $id`.
4. **Styles** are filtered analogously by `$texy->allowedStyles`.
5. **Alignment** is applied either as a CSS class (when `$texy->alignClasses` defines a class for the direction) or as an inline `text-align` / `vertical-align` style.

The result: the element carries everything from the modifier that the current configuration permits – the basis of safe processing of untrusted input (see the configuration reference (user manual)).

## Propagation through the system

1. The **syntax handler** extracts the modifier text from the regex match and constructs a `Modifier`.
2. The `Modifier` object travels as a parameter into **element handlers** – handlers receive the parsed object, not raw text, and may adjust it (add classes, change styles) before application.
3. The element handler creates the `HtmlElement` and calls `$mod->decorate($texy, $el)` – this is the moment the modifier takes effect, subject to configuration.

Some modules combine several modifiers. `TableModule` parses modifiers at the table, row, column, and cell level; a cell's effective modifier is a clone of its column modifier with the cell's own modifier applied on top, so column-wide defaults can be overridden per cell.
