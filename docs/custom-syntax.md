# Adding Custom Syntax

This chapter covers adding **brand-new markup constructs** that Texy does not know – e.g. `@@username` mentions or `:::warning` alert blocks. To merely change the behavior of existing constructs, see [custom-handlers.md](custom-handlers.md).

You define what the construct looks like (a regular expression) and write a syntax handler that turns a match into an AST node. Texy then recognizes your syntax just like its built-in ones.

## Registering

```php
$texy->addLineSyntax(string $name, string $pattern, callable $parser);
$texy->addBlockSyntax(string $name, string $pattern, callable $parser);
```

- **Line patterns** recognize inline constructs; the pattern is searched anywhere in the text, so it must **not** be anchored with `^`.
- **Block patterns** recognize multi-line constructs; the pattern **must** be anchored to the start of a line (`^`). The `m` modifier is appended automatically.
- **`$name`** – unique syntax name; used in `$texy->allowed` and passed to the handler. Use a prefixed style such as `custom/username` to avoid clashes with future built-in syntaxes. Registration defaults `$allowed[$name]` to `true` unless already configured.

Register before calling `process()`. Built-in modules register their patterns during `process()`, so your pattern keeps its priority position across calls.

Registering under a built-in name (a `Syntax::*` constant) **replaces** that syntax: a name you register is owned by you and modules never overwrite it, no matter how many times the instance parses. The override is permanent for the lifetime of the instance – there is no API to restore the built-in pattern, so if you need the original behavior back, create a fresh `Texy` instance. Disabling via `$texy->allowed[$name] = false` works for overridden syntaxes exactly as for built-in ones.

Your pattern behaves exactly as written: Texy adds only the `u` (UTF-8) flag, so what you verify in a regex tester works after registration. Flags of your own – including extended mode `x`, which lets you lay the pattern out over several lines with `#` comments – belong after the closing delimiter, as usual:

```php
$texy->addLineSyntax('custom/mention', '~
	@ ([a-z0-9_]{2,30})   # user name
~x', $parser);
```

`Texy\Patterns` fragments (`Modifier`, `LinkUrl`, `Email`…) are written in extended mode but carry it themselves, so you can embed them in a pattern of either kind. Likewise `Texy\Regexp::quote()` escapes whitespace, so quoted text matches literally in both modes.

## The syntax handler

```php
function (\Texy\Parsing\ParseContext $context, array $matches, array $offsets, string $name): ?Texy\Node
```

- `$matches[0]` is the whole match, `$matches[1]`… the capturing groups; `$offsets` holds the byte offset of each group in the source (for `Range` tracking).
- Return an **AST node** – an `InlineNode` for line patterns, a `BlockNode` for block patterns – or **`null` to refuse** the match (the text is left alone and other patterns get their chance).
- Return a **`ContentNode` to emit a sequence** – its children are spliced into the surrounding node list in place of a single node. An **empty `ContentNode`** consumes the match without emitting anything, which is how a construct that only collects data (such as a link reference definition in the Markdown parser example) disappears from the document.
- For nested content, parse recursively: `$context->parseInline($innerText, $offset)` / `$context->parseBlock(...)` return a `ContentNode` you attach to your node. (Attach it to your node's content – returning it directly would splice its children as a sequence, see above.)

Returning an existing node type (`PhraseNode`, `LinkNode`, `HtmlElementNode`…) gives you rendering for free in every output format. A brand-new `Node` subclass needs a [renderer handler](custom-handlers.md#renderer-handlers) on the generator you care about; the other formats fall back to rendering its children, so the document stays renderable everywhere. Implement `getChildren()` so they can find them. The one exception is printing the tree back as Texy source (`renderTexy()`): there is no notation to fall back to, so it throws until you give the node a printed form: create the renderer yourself (`new Output\Texy\Renderer`), give it the form with `addRenderer()` and call its `render()`, since `renderTexy()` always uses a fresh one.

Collision resolution ([parsing.md](parsing.md#inlineparser)): at the same position the longer match wins, then earlier registration. Keep patterns specific.

## Examples

### User mentions (line)

```php
use Texy\Nodes;

$texy->addLineSyntax(
    'custom/username',
    '~@@([a-z0-9_]+)~i',
    function (\Texy\Parsing\ParseContext $context, array $matches, array $offsets): Nodes\LinkNode {
        $username = $matches[1];
        return new Nodes\LinkNode(
            url: '/user/' . urlencode($username),
            content: new Nodes\ContentNode([new Nodes\TextNode('@' . $username)]),
            range: new Texy\Range($offsets[0], strlen($matches[0])),
        );
    },
);
```

```texy
Check out the profile of @@johndoe.
```

### Alert boxes (block, with nested Texy content)

```php
$texy->addBlockSyntax(
    'custom/alert',
    '~^:::(warning|info|danger)\n(.+?)\n:::$~s',
    function (\Texy\Parsing\ParseContext $context, array $matches, array $offsets): Nodes\SectionNode {
        [, $type, $content] = $matches;
        $mod = new Texy\Modifier;
        $mod->classes['alert'] = $mod->classes['alert-' . $type] = true;
        return new Nodes\SectionNode(
            $context->parseBlock(trim($content), $offsets[2]),  // recursive Texy parsing
            'div',
            $mod,
        );
    },
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

$texy->addLineSyntax(
    'custom/abbreviation',
    '~\b([A-Z]{2,})\b~',
    function (\Texy\Parsing\ParseContext $context, array $matches, array $offsets) use ($abbreviations): ?Nodes\AnnotationNode {
        if (!isset($abbreviations[$matches[1]])) {
            return null;   // unknown abbreviation - leave the text alone
        }
        return new Nodes\AnnotationNode($matches[1], $abbreviations[$matches[1]]);
    },
);
```

Refusal is also the **priority mechanism**. Collisions are resolved by position (leftmost, then longest, then registration order) – there is no global priority table. When your construct should lose to another syntax in a specific context (say, its content contains an unbalanced backtick that a code span will claim), inspect the match and return `null`; the position is handed back and the other patterns get their turn.

### Context-sensitive patterns

A handler sees only its own match, never the surrounding text. Two tools cover the cases where context matters:

- **A neighbouring character as a value** – capture it through the lookbehind: `(?:(?<=(.))|(?<!.))` puts the character before the match into group 1 (unset at the start of text). Use this when the rule *classifies* the neighbour (letter? punctuation?) rather than merely forbidding it, which a plain lookbehind guard would do.
- **The preceding block** – in a block handler, `$context->getBlockParser()->getNodes()` returns the blocks parsed so far; the last one is the block directly before your match (the gap handler has already run, so a preceding paragraph is already there). A rule like "this construct may not interrupt a paragraph" becomes a structural check on the last node instead of a lookbehind that guesses at blank lines. The nodes are live: a handler may even graft its consumed text onto the preceding block when the construct turns out to be a plain continuation of it.

### Multi-line structure with next()/moveBackward() (block)

For structures whose full extent the registered pattern cannot capture, use the block parser's navigation API:

```php
$texy->addBlockSyntax(
    'custom/note',
    '~^NOTE:\s*(.+)$~',
    function (\Texy\Parsing\ParseContext $context, array $matches, array $offsets): Nodes\BlockQuoteNode {
        $parser = $context->getBlockParser();
        $parser->moveBackward();   // pattern matched the first line; re-read from it

        $content = '';
        while ($parser->next('~^NOTE:\s*(.+)$~', $m)) {
            $content .= $m[1] . "\n";
        }

        return new Nodes\BlockQuoteNode($context->parseBlock(trim($content)));
    },
);
```

```texy
NOTE: This is an important note.
NOTE: It may span several lines.
```

## Best practices

- **Return `null` on refusal** – the parser leaves the text alone and other syntaxes get their chance.
- **Prefer existing node types** – they render in every output format alike; add a custom `Node` subclass only when no existing node fits. Subclassing a built-in node is the cheapest way to keep full rendering, since dispatch falls back to the ancestor's renderer.
- **Use prefixed names** (`custom/username`, `myapp/alert`) – both to avoid future collisions and to let users disable your syntax via `$texy->allowed`.
- **Support modifiers** – add `Texy\Patterns::Modifier . '?'` to your pattern and pass `Texy\Modifier::parse($matches[n])` into your node so users can style your construct like any other.
- **Fill in `Range`** from `$offsets`, covering all the node is written with – editor tooling relies on it. To tools your node is opaque: they can only replace its whole range. If it is a built-in node type, you may fill the parts of its notation too (`LinkNode::$syntax`, `$textRange`, `$urlRange`…, see [architecture.md](architecture.md#source-positions)); use your syntax name as `$syntax`.
- **Test combinations** – what happens when your markup appears inside a link? Inside a code block?
