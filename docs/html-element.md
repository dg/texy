# HtmlElement – the DOM Representation

`Texy\HtmlElement` (`src/Texy/HtmlElement.php`) represents one node of the DOM tree that parsing builds. It carries a tag name, an associative array of attributes, and a list of children, and provides the interface for manipulating and serializing the tree.

## Structure

```php
$el = new Texy\HtmlElement('div');
$el = new Texy\HtmlElement('div', ['class' => ['box']]);  // with attributes
$el = new Texy\HtmlElement('span', 'text content');       // with textual content
$el = Texy\HtmlElement::el('div');                        // static factory
```

**Name.** `setName(?string $name, ?bool $empty = null)` / `getName()`. The special value `null` means a *transparent* element: it renders no tags, only its content – used e.g. for the document root. The second parameter forces the "empty element" flag; by default it is derived from the known set of void elements (`img`, `br`, `hr`, `input`...), queryable via `isEmpty()`.

**Attributes** are publicly accessible through the `$attrs` array; property overloading maps any undeclared property to an attribute, so `$el->href = '...'` and `$el->attrs['href'] = '...'` are equivalent. Values may be:

- `string` or `int` – rendered as a normal attribute value,
- `true` – boolean attribute rendered without a value (`checked`),
- `false` or `null` – attribute is not rendered at all,
- `array` – items are joined during rendering; for `class`-like attributes with spaces, for `style` as `property:value` pairs joined with semicolons. This is why module code often writes `$el->attrs['class'][] = 'foo'`.

Convenience methods: `setAttribute()`, `getAttribute()` (returns `null` when absent), and `href($path, ?array $query)` which builds a query string.

**Children** are either nested `HtmlElement` instances or plain strings:

- `add(HtmlElement|string $child)` – append,
- `insert(?int $index, $child, bool $replace = false)` – insert at position (or append when `$index` is `null`),
- `create(string $name, $attrs = null)` – create a child element, attach it, and return it,
- `removeChildren()`, `getChildren()`, `count()` (`Countable`), `getIterator()` (`IteratorAggregate`),
- `setText(string $text)` – replace all children with a single text node; `getText()` returns the concatenated text or `null` if any child is an element,
- `ArrayAccess`: `$el[0]` reads the first child, `$el[0] = $child` replaces it, `isset()`/`unset()` work as expected.

## Serialization

Three methods convert an element (sub)tree to a string; the distinction matters:

- **`toString(Texy $texy): string`** – recursively renders the element into Texy's *internal* representation. HTML tags are immediately masked with `Texy::protect()` (see [parsing.md](parsing.md#protection-marks)), so the result contains placeholders, not literal tags. This is what parsing uses when inserting processed elements back into the text being parsed.
- **`toHtml(Texy $texy): string`** – returns final HTML: `toString()` followed by `Texy::stringToHtml()` (post-line handlers, escaping, unprotect, well-forming).
- **`toText(Texy $texy): string`** – plain-text rendition: `toHtml()` followed by tag stripping and entity decoding (`Texy::stringToText()`).

`startTag()` / `endTag()` render the opening/closing tag alone. During rendering, attribute values are HTML-escaped and their spaces are frozen (`Helpers::freezeSpaces()`) so later post-processing cannot corrupt them.

`getContentType()` classifies the element for the protection mechanism: replaced elements (`img`, `br`...) → `Texy::CONTENT_REPLACED`, known inline elements → `CONTENT_MARKUP`, everything else → `CONTENT_BLOCK`.

## Parsing content

An element can recursively parse its own content, which is how the DOM tree grows:

- **`parseLine(Texy $texy, string $s): void`** – creates an `InlineParser` with this element as the container and parses the string's inline syntaxes; results become the element's children.
- **`parseBlock(Texy $texy, string $s, bool $indented = false): void`** – creates a `BlockParser` and parses the string as block content; `$indented` signals that the text comes from an indented context (affects paragraph handling in list items etc.).

A typical syntax handler creates an element, sets its attributes, and calls one of these to process the inner text through the standard pipeline – including nested syntax recognition and handler invocation.

## DTD validation

Texy validates output against an HTML DTD table loaded from `src/Texy/DTD.php` into `Texy::$dtd` (accessible via `Texy::getDTD()`). The structure maps each tag name to a pair:

```php
$dtd[$element][0]  // allowed attributes (as array keys)
$dtd[$element][1]  // content model: allowed child elements as keys,
                   // or false = empty element, or 0 = transparent
```

- **`validateAttrs(array $dtd): void`** – removes attributes not allowed for the element's tag. Wildcard entries `data-*` and `aria-*` in the DTD permit any attribute with that prefix. Called when modifiers are applied (`Modifier::decorate()`), so an invalid attribute never reaches the output – important for both correctness and security.
- **`validateChild(HtmlElement|string $child, array $dtd): bool`** – checks whether the child may appear inside this element according to the content model. Used mainly by `HtmlOutputModule` when fixing nesting; module-generated structure is usually correct by design.

The DTD is also what `$texy->allowedTags` defaults are derived from: on construction, every tag known to the DTD is allowed with all its attributes (see the configuration reference (user manual)).
