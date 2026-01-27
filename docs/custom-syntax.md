# Adding Custom Syntax

This chapter covers adding **brand-new markup constructs** that Texy does not know – e.g. `@@username` mentions or `:::warning` alert blocks. To merely change the behavior of existing constructs, see [custom-handlers.md](custom-handlers.md).

You define what the construct looks like (a regular expression) and write a function that processes it. Texy then recognizes your syntax just like its built-in ones.

## Registering a line syntax

Inline constructs inside lines of text:

```php
$texy->registerLinePattern(
    callable $handler,
    string $pattern,
    string $name,
    ?string $againTest = null,
);
```

- **`$handler`** – the syntax handler called on a match (see below).
- **`$pattern`** – a PCRE regex defining the appearance. It must **not** be anchored to the start of a line (`^`), because it is searched for anywhere in the text. Use capturing groups for the data you need.
- **`$name`** – unique syntax name; used in `$texy->allowed` and passed to the handler. Use a prefixed style such as `custom/username` to avoid clashes with future built-in syntaxes.
- **`$againTest`** – optional optimization regex. When the main pattern no longer matches anywhere ahead, the parser normally drops it from the search for the rest of the pass; if `$againTest` is provided and still matches, the pattern is kept and retried (useful because replacements performed by other handlers can introduce new matches). Give it a cheap regex that tests for the construct's distinctive character.

Registration automatically sets `$texy->allowed[$name] = true` unless already configured.

```php
$texy->registerLinePattern(
    $usernameHandler,
    '#@@([a-z0-9_]+)#i',
    'custom/username',
);
```

## Registering a block syntax

Multi-line block constructs:

```php
$texy->registerBlockPattern(
    callable $handler,
    string $pattern,
    string $name,
);
```

The pattern **must** be anchored to the start of a line (`^`) and typically to the end (`$`). `registerBlockPattern()` appends the `m` (multiline) modifier automatically – do not add it yourself, but do write the `^` anchor.

```php
$texy->registerBlockPattern(
    $alertHandler,
    '#^:::(warning|info|danger)\n(.+)$#s',
    'custom/alert',
);
```

## The syntax handler

The parser calls the syntax handler when it finds a match. Its job: process the matched data and return an `HtmlElement`, a string, or `null` to refuse (the parser then tries other syntaxes / positions).

### For line syntaxes

```php
function(Texy\InlineParser $parser, array $matches, string $name): Texy\HtmlElement|string|null
```

- `$parser->getTexy()` returns the `Texy` instance.
- `$matches[0]` is the whole match, `$matches[1]`… the capturing groups.
- `$name` identifies the syntax when one handler serves several.

The result replaces the matched text in the parsed string. An `HtmlElement` is serialized with its tags masked (see [parsing.md](parsing.md#protection-marks)); a returned raw HTML string must be protected manually with `$texy->protect($html, Texy::CONTENT_…)`, otherwise it will be escaped.

**`$parser->again`** – set `$parser->again = true` before returning when the produced element's textual content may itself contain further occurrences of the same syntax; the parser will then search for this syntax again at the same position instead of moving past the replacement.

### For block syntaxes

```php
function(Texy\BlockParser $parser, array $matches, string $name): Texy\HtmlElement|string|null
```

`BlockParser` adds an API for multi-line structures:

- **`$parser->next($pattern, &$matches): bool`** – matches the next line against `$pattern` (`Am` modifiers added automatically); on success fills `$matches`, advances past the line, returns `true`. Use in a loop to consume successive lines of your structure.
- **`$parser->moveBackward($lines = 1): void`** – moves back over the given number of line endings; useful when the registered pattern matched only part of the structure and you want to re-read it from the start with `next()`.
- **`$parser->isIndented(): bool`** – whether the current block context is indented (nested content).

## Examples

### User mentions (line)

```php
$texy->registerLinePattern(
    function(Texy\InlineParser $parser, array $matches, string $name): Texy\HtmlElement {
        $username = $matches[1];
        $el = new Texy\HtmlElement('a');
        $el->attrs['href'] = '/user/' . urlencode($username);
        $el->attrs['class'][] = 'user-profile';
        $el->setText('@' . $username);
        return $el;
    },
    '#@@([a-z0-9_]+)#i',
    'custom/username',
);
```

```texy
Check out the profile of @@johndoe.
```

### Alert boxes (block, with nested Texy content)

```php
$texy->registerBlockPattern(
    function(Texy\BlockParser $parser, array $matches, string $name): Texy\HtmlElement {
        [, $type, $content] = $matches;
        $el = new Texy\HtmlElement('div');
        $el->attrs['class'][] = 'alert';
        $el->attrs['class'][] = 'alert-' . $type;
        $el->parseBlock($parser->getTexy(), trim($content));  // recursive Texy parsing
        return $el;
    },
    '#^:::(warning|info|danger)\n(.+?)(?=\n:::|$)#s',
    'custom/alert',
);
```

```texy
:::warning
This is an important notice!
:::
```

### Hashtags (line, with $againTest optimization)

```php
$texy->registerLinePattern(
    function(Texy\InlineParser $parser, array $matches, string $name): Texy\HtmlElement {
        $el = new Texy\HtmlElement('a');
        $el->attrs['href'] = '/tag/' . urlencode($matches[1]);
        $el->attrs['class'][] = 'hashtag';
        $el->setText('#' . $matches[1]);
        return $el;
    },
    '#\#([a-z0-9_]+)#i',
    'custom/hashtag',
    '#\##',   // cheap pre-test: only worth searching while a # exists ahead
);
```

### Abbreviations (line, conditional refusal)

```php
$abbreviations = [
    'HTML' => 'HyperText Markup Language',
    'CSS' => 'Cascading Style Sheets',
];

$texy->registerLinePattern(
    function(Texy\InlineParser $parser, array $matches, string $name) use ($abbreviations): ?Texy\HtmlElement {
        if (!isset($abbreviations[$matches[1]])) {
            return null;   // unknown abbreviation – leave the text alone
        }
        $el = new Texy\HtmlElement('abbr');
        $el->attrs['title'] = $abbreviations[$matches[1]];
        $el->setText($matches[1]);
        return $el;
    },
    '#\b([A-Z]{2,})\b#',
    'custom/abbreviation',
);
```

### Multi-line structure with next()/moveBackward() (block)

```php
$texy->registerBlockPattern(
    function(Texy\BlockParser $parser, array $matches, string $name): Texy\HtmlElement {
        $parser->moveBackward();   // pattern matched the first line; re-read from it

        $content = '';
        while ($parser->next('#^NOTE:\s*(.+)$#', $matches)) {
            $content .= $matches[1] . "\n";
        }

        $el = new Texy\HtmlElement('aside');
        $el->attrs['class'][] = 'note';
        $el->parseBlock($parser->getTexy(), trim($content));
        return $el;
    },
    '#^NOTE:\s*(.+)$#',
    'custom/note',
);
```

```texy
NOTE: This is an important note.
NOTE: It may span several lines.
```

## Syntax collisions

When registering a custom syntax, watch out for collisions with built-in syntaxes and with other custom ones (see also [parsing.md](parsing.md#syntax-collisions)):

- **Registration order matters.** When several line syntaxes match at the same position, the one registered *earlier* wins. Register more specific syntaxes before more general ones. Note that almost all built-in syntaxes are registered in the `Texy` constructor, so your patterns are tried *after* them at equal positions (exceptions: `list`, `list/definition`, and `emoticon` register lazily in `beforeParse`, i.e. after your patterns).
- **Be specific in patterns.** `#\#\w+#` would also match parts of surrounded headings; `#(?<=\s)\#[a-z0-9_]+#i` requiring a preceding space is safer.
- **Exclude protected content.** Use `Texy\Patterns::MARK` (`\x14-\x1F`) in negated character classes so your pattern does not match inside already-processed placeholders: `[^'.Patterns::MARK.']`.
- **Test combinations.** What happens when your markup appears inside a link? Inside a code block?

## Best practices

- **Return `null` on refusal** – the parser leaves the text alone and other syntaxes get their chance.
- **Protect raw HTML** – strings returned instead of `HtmlElement` must go through `$texy->protect($html, Texy::CONTENT_…)`, otherwise they get escaped.
- **Set `$parser->again` deliberately** – for line syntaxes whose content can contain more of the same syntax.
- **Use prefixed names** (`custom/username`, `myapp/alert`) – both to avoid future collisions and to let users disable your syntax via `$texy->allowed`.
- **Respect `$texy->allowed`** – the parser already skips disabled syntaxes, but if your handler serves several syntaxes or does extra work, check `$texy->allowed[$name]` where relevant.
- **Support modifiers** – add `Texy\Patterns::MODIFIER . '?'` to your pattern and apply it with `(new Texy\Modifier($matches[n]))->decorate($texy, $el)` so users can style your construct like any other.
