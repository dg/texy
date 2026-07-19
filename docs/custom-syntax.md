# Adding Custom Syntax

This chapter covers adding **brand-new markup constructs** that Texy does not know – e.g. `@@username` mentions or `:::warning` alert blocks. To merely change the behavior of existing constructs, see [custom-handlers.md](custom-handlers.md).

You define what the construct looks like (a regular expression) and write a syntax handler that turns a match into an AST node. Texy then recognizes your syntax just like its built-in ones.

## Registering

```php
$texy->registerLinePattern(\Closure $handler, string $pattern, string $name);
$texy->registerBlockPattern(\Closure $handler, string $pattern, string $name);
```

- **Line patterns** recognize inline constructs; the pattern is searched anywhere in the text, so it must **not** be anchored with `^`.
- **Block patterns** recognize multi-line constructs; the pattern **must** be anchored to the start of a line (`^`). The `m` modifier is appended automatically.
- **`$name`** – unique syntax name; used in `$texy->allowed` and passed to the handler. Use a prefixed style such as `custom/username` to avoid clashes with future built-in syntaxes. Registration defaults `$allowed[$name]` to `true` unless already configured.

Register before calling `process()`. Built-in modules register their patterns during `process()`, so your pattern keeps its priority position across calls.

Your pattern behaves exactly as written: Texy adds only the `u` (UTF-8) flag, so what you verify in a regex tester works after registration. Flags of your own – including extended mode `x`, which lets you lay the pattern out over several lines with `#` comments – belong after the closing delimiter, as usual:

```php
$texy->registerLinePattern($handler, '~
	@ ([a-z0-9_]{2,30})   # user name
~x', 'custom/mention');
```

`Texy\Patterns` fragments (`MODIFIER`, `IMAGE`, `LINK_URL`, `EMAIL`…) are written in extended mode but carry it themselves, so you can embed them in a pattern of either kind. Likewise `Texy\Regexp::quote()` escapes whitespace, so quoted text matches literally in both modes.

## The syntax handler

```php
function (Texy\ParseContext $context, array $matches, array $offsets, string $name): ?Texy\Node
```

- `$matches[0]` is the whole match, `$matches[1]`… the capturing groups; `$offsets` holds the byte offset of each group in the source (for `Range` tracking).
- Return an **AST node** – an `InlineNode` for line patterns, a `BlockNode` for block patterns – or **`null` to refuse** the match (the text is left alone and other patterns get their chance).
- For nested content, parse recursively: `$context->parseInline($innerText, $offset)` / `$context->parseBlock(...)` return a `ContentNode` you attach to your node.

Returning an existing node type (`PhraseNode`, `LinkNode`, `HtmlElementNode`…) gives you rendering for free in every output format. A brand-new `Node` subclass needs a [renderer handler](custom-handlers.md#renderer-handlers) registered for it on each generator you use.

Collision resolution ([parsing.md](parsing.md#inlineparser)): at the same position the longer match wins, then earlier registration. Keep patterns specific.

## Examples

### User mentions (line)

```php
use Texy\Nodes;

$texy->registerLinePattern(
    function (Texy\ParseContext $context, array $matches, array $offsets): Nodes\LinkNode {
        $username = $matches[1];
        return new Nodes\LinkNode(
            url: '/user/' . urlencode($username),
            content: new Nodes\ContentNode([new Nodes\TextNode('@' . $username)]),
            range: new Texy\Range($offsets[0], strlen($matches[0])),
        );
    },
    '~@@([a-z0-9_]+)~i',
    'custom/username',
);
```

```texy
Check out the profile of @@johndoe.
```

### Alert boxes (block, with nested Texy content)

```php
$texy->registerBlockPattern(
    function (Texy\ParseContext $context, array $matches, array $offsets): Nodes\SectionNode {
        [, $type, $content] = $matches;
        $mod = new Texy\Modifier;
        $mod->classes['alert'] = $mod->classes['alert-' . $type] = true;
        return new Nodes\SectionNode(
            $context->parseBlock(trim($content), $offsets[2]),  // recursive Texy parsing
            'div',
            $mod,
        );
    },
    '~^:::(warning|info|danger)\n(.+?)\n:::$~s',
    'custom/alert',
);
```

```texy
:::warning
This is an **important** notice!
:::
```

### Conditional refusal

```php
$abbreviations = [
    'HTML' => 'HyperText Markup Language',
    'CSS' => 'Cascading Style Sheets',
];

$texy->registerLinePattern(
    function (Texy\ParseContext $context, array $matches, array $offsets) use ($abbreviations): ?Nodes\AnnotationNode {
        if (!isset($abbreviations[$matches[1]])) {
            return null;   // unknown abbreviation - leave the text alone
        }
        return new Nodes\AnnotationNode($matches[1], $abbreviations[$matches[1]]);
    },
    '~\b([A-Z]{2,})\b~',
    'custom/abbreviation',
);
```

### Multi-line structure with next()/moveBackward() (block)

For structures whose full extent the registered pattern cannot capture, use the block parser's navigation API:

```php
$texy->registerBlockPattern(
    function (Texy\ParseContext $context, array $matches, array $offsets): Nodes\BlockQuoteNode {
        $parser = $context->getBlockParser();
        $parser->moveBackward();   // pattern matched the first line; re-read from it

        $content = '';
        while ($parser->next('~^NOTE:\s*(.+)$~', $m)) {
            $content .= $m[1] . "\n";
        }

        return new Nodes\BlockQuoteNode($context->parseBlock(trim($content)));
    },
    '~^NOTE:\s*(.+)$~',
    'custom/note',
);
```

```texy
NOTE: This is an important note.
NOTE: It may span several lines.
```

## Best practices

- **Return `null` on refusal** – the parser leaves the text alone and other syntaxes get their chance.
- **Prefer existing node types** – they render in HTML and Markdown alike; add a custom `Node` subclass only when no existing node fits, and register renderer handlers for it.
- **Use prefixed names** (`custom/username`, `myapp/alert`) – both to avoid future collisions and to let users disable your syntax via `$texy->allowed`.
- **Support modifiers** – add `Texy\Patterns::MODIFIER . '?'` to your pattern and pass `Texy\Modifier::parse($matches[n])` into your node so users can style your construct like any other.
- **Fill in `Range`** where practical – editor tooling and passes rely on source positions.
- **Test combinations** – what happens when your markup appears inside a link? Inside a code block?
