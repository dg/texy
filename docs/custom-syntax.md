# Adding Custom Syntax

This chapter covers adding **brand-new markup constructs** that Texy does not know – e.g. `@@username` mentions or `:::warning` alert blocks. To merely change the behavior of existing constructs, see [custom-handlers.md](custom-handlers.md).

You define what the construct looks like (a regular expression) and write a syntax handler that turns a match into an AST node. Texy then recognizes your syntax just like its built-in ones.

## Registering

```php
$texy->addInlineSyntax(string $name, string $pattern, callable $parser);
$texy->addBlockSyntax(string $name, string $pattern, callable $parser);
```

- **Inline patterns** recognize inline constructs; the pattern is searched anywhere in the text, so it must **not** be anchored with `^`.
- **Block patterns** recognize multi-line constructs; the pattern **must** be anchored to the start of a line (`^`). The `m` modifier is appended automatically.
- **`$name`** – unique syntax name; used in `$texy->allowedSyntax` and passed to the handler. Use a prefixed style such as `custom/username` to avoid clashes with future built-in syntaxes. Registration defaults `$allowedSyntax[$name]` to `true` unless already configured.

Register before calling `toHtml()`. Built-in modules register their patterns during `toHtml()`, so your pattern keeps its priority position across calls.

Registering under a built-in name (a `Syntax::*` constant) **replaces** that syntax: a name you register is owned by you and modules never overwrite it, no matter how many times the instance parses. The override is permanent for the lifetime of the instance – there is no API to restore the built-in pattern, so if you need the original behavior back, create a fresh `Texy` instance. Disabling via `$texy->allowedSyntax[$name] = false` works for overridden syntaxes exactly as for built-in ones.

Your pattern behaves exactly as written: Texy adds only the `u` (UTF-8) flag, and to a block pattern also `m`, so that `^` and `$` mean the edges of a line; what you verify in a regex tester with these flags works after registration. Flags of your own – including extended mode `x`, which lets you lay the pattern out over several lines with `#` comments – belong after the closing delimiter, as usual:

```php
$texy->addInlineSyntax('custom/mention', '~
	@ ([a-z0-9_]{2,30})   # user name
~x', $parser);
```

`Texy\Patterns` fragments (`Modifier`, `LinkUrl`, `Email`…) are written in extended mode but carry it themselves, so you can embed them in a pattern of either kind. Likewise `Texy\Regexp::quote()` escapes whitespace, so quoted text matches literally in both modes.

## A block type of /--

A block written as `/--gallery … \--` needs no pattern of its own: register the type on the configuration. The handler gets the content with its shared indentation stripped, the parameter written after the type and the modifier, and returns a node, or `null` to leave the block as text. Whatever it parses from the content with `$context` is mapped back to the source, nesting and modifiers work as in any `/--` block:

```php
$texy->config->addBlockType('gallery', function (ParseContext $context, string $content, ?string $param, ?Modifier $modifier) {
    $modifier ??= new Modifier;
    $modifier->classes['gallery'] = true;
    return new Nodes\DivNode($context->parseBlock($content), $modifier);
});
```

A registered type replaces a built-in one of the same name, and like a built-in one it is switched off by `$texy->allowedSyntax['block/gallery'] = false`.

## The syntax handler

```php
function (\Texy\Parsing\ParseContext $context, array $matches, array $offsets, string $name): ?Texy\Node
```

- `$matches[0]` is the whole match, `$matches[1]`… the capturing groups; `$offsets` holds the byte offset of each group in the source (for `Range` tracking).
- Return an **AST node** – an `InlineNode` for line patterns, a `BlockNode` for block patterns – or **`null` to refuse** the match (the text is left alone and other patterns get their chance).
- Return a **`ContentNode` to emit a sequence** – its children are spliced into the surrounding node list in place of a single node. An **empty `ContentNode`** consumes the match without emitting anything, which is how a construct that only collects data (such as a link reference definition of Markdown) disappears from the document.
- For nested content, parse recursively: `$context->parseInline($innerText, $offset)` / `$context->parseBlock(...)` return a `ContentNode` you attach to your node. (Attach it to your node's content – returning it directly would splice its children as a sequence, see above.)

Returning an existing node type (`PhraseNode`, `LinkNode`, `HtmlElementNode`…) gives you rendering for free in every output format. A brand-new `Node` subclass needs a [renderer handler](custom-handlers.md#renderer-handlers) on the generator you care about; the other formats fall back to rendering its children, so the document stays renderable everywhere. Implement `getChildren()` so they can find them; typography reads the content found there as text around markup, and treats a node without content like an image. Text that must stay verbatim belongs in a subclass of `InlineCodeNode` or `RawTextNode`. The one exception is printing the tree back as Texy source (`renderTexy()`): there is no notation to fall back to, so it throws until you give the node a printed form: create the renderer yourself (`new Output\Texy\Renderer($texy)`), give it the form with `addRenderer()` and call its `render()`, since `renderTexy()` always uses a fresh one.

Collision resolution ([parsing.md](parsing.md#inlineparser)): at the same position the longer match wins, then your syntax over a built-in one, then earlier registration. Keep patterns specific.

## Examples

### User mentions (line)

```php
use Texy\Nodes;

$texy->addInlineSyntax(
    'custom/username',
    '~@@([a-z0-9_]+)~i',
    function (\Texy\Parsing\ParseContext $context, array $matches, array $offsets): Nodes\LinkNode {
        $username = $matches[1];
        return new Nodes\LinkNode(
            url: '/user/' . urlencode($username),
            content: Nodes\ContentNode::fromText('@' . $username),
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
    function (\Texy\Parsing\ParseContext $context, array $matches, array $offsets): Nodes\DivNode {
        [, $type, $content] = $matches;
        return new Nodes\DivNode(
            $context->parseBlock(trim($content), $offsets[2]),  // recursive Texy parsing
            new Texy\Modifier(classes: ['alert' => true, 'alert-' . $type => true]),
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

$texy->addInlineSyntax(
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

Refusal is also the **priority mechanism**. Collisions are resolved by position (leftmost, then longest, then your syntaxes before the built-in ones, then registration order; a block syntax compares no length) – there is no global priority table. When your construct should lose to another syntax in a specific context (say, its content contains an unbalanced backtick that a code span will claim), inspect the match and return `null`; the position is handed back and the other patterns get their turn.

### Refusal of a block syntax

The block parser finds the matches of every block pattern once, before it calls any handler. When a handler refuses its match, the matches of **other** patterns inside the refused range stay and get their turn, but its **own** pattern is not searched again there: a match it would have found inside, overlapping the refused one, is lost. A block pattern that can find another match of its own inside a match therefore carries its refusal conditions in the regular expression, not in the handler.

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
- **Use prefixed names** (`custom/username`, `myapp/alert`) – both to avoid future collisions and to let users disable your syntax via `$texy->allowedSyntax`.
- **Support modifiers** – add `Texy\Patterns::Modifier . '?'` to your pattern and pass `Texy\Modifier::parse($matches[n])` into your node so users can style your construct like any other.
- **Fill in `Range`** from `$offsets`, covering all the node is written with – editor tooling relies on it. To tools your node is opaque: they can only replace its whole range. If it is a built-in node type, you may fill the parts of its notation too (`LinkNode::$syntax`, `$textRange`, `$urlRange`…, see [architecture.md](architecture.md#source-positions)); use your syntax name as `$syntax`.
- **Test combinations** – what happens when your markup appears inside a link? Inside a code block?
