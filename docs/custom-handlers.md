# Changing the Behavior of Existing Constructs

This chapter covers modifying how **existing** constructs are processed. To add a **brand-new** markup construct, see [custom-syntax.md](custom-syntax.md).

There are two extension points, matching the two phases where behavior can change:

- **Renderer handlers** – change how a node is *rendered* (the typical case: custom image rendering, extra attributes, wrapping elements).
- **Transform passes** – change the *parsed document* itself before rendering (restructure content, remove nodes, collect data).

## Renderer handlers

Register a closure on an output generator; the node class is determined from the type of the closure's first parameter:

```php
use Texy\Nodes;
use Texy\Output\Html;

$texy->htmlOutput->registerHandler(
    function (Nodes\ImageNode $node, Html\Renderer $gen, ?Closure $previous): Html\Element|Html\Raw|string|null {
        // your logic
    },
);
```

Handlers for one node class form a chain, executed **from the last registered to the first**; the generator's default renderer sits at the end. Your handler can:

- **replace rendering entirely** – return its own result,
- **delegate** – call `$previous($node, $gen)` (the previous handler, or the default renderer) and optionally post-process its result,
- **decline** – return `null`, which delegates to the previous handler automatically.

Valid return values are `Html\Element`, `Html\Raw`, a plain string (display text – it will be HTML-escaped), or `null` to delegate. For the Markdown generator the same mechanism applies with string results.

The same handler works for a construct wherever it appears – e.g. an `ImageNode` handler fires for inline images and for the image inside a figure alike, because the figure renderer delegates through `renderNode()`.

Handlers must respect the render-phase purity rule: **do not mutate the node or the modifier** – if you need a modified variant, clone it.

### Example: YouTube embed

```php
use Texy\Nodes\ImageNode;
use Texy\Output\Html;

$texy->htmlOutput->registerHandler(
    function (ImageNode $node, Html\Renderer $gen, ?Closure $previous): Html\Element|Html\Raw|string|null {
        if (!str_starts_with($node->url ?? '', 'youtube:')) {
            return null;   // not ours - delegate to the default renderer
        }
        $el = new Html\Element('iframe', [
            'src' => 'https://youtube.com/embed/' . substr($node->url, 8),
            'width' => $node->width ?: 640,
            'height' => $node->height ?: 360,
            'allowfullscreen' => true,
        ]);
        return $el;
    },
);
```

Usage: `[* youtube:dQw4w9WgXcQ 640x360 *]`

### Example: post-processing the default result

```php
$texy->htmlOutput->registerHandler(
    function (Texy\Nodes\HeadingNode $node, Html\Renderer $gen, ?Closure $previous) {
        $el = $previous($node, $gen);       // default <h#> element
        $el->attrs['class'][] = 'anchored';
        return $el;
    },
);
```

### Raw HTML strings

To emit ready-made HTML, return `new Html\Raw($html)` – it bypasses text escaping and is tokenized by the well-forming engine as-is.

## Transform passes

For document-level changes, register an `afterParse` handler; it receives the `DocumentNode` after parsing and the built-in transform passes may be freely combined with yours. Use `Texy\NodeTraverser` to walk and modify the tree:

```php
$texy->addHandler('afterParse', function (Texy\Nodes\DocumentNode $doc) {
    (new Texy\NodeTraverser)->traverse($doc, function (Texy\Node $node) {
        if ($node instanceof Texy\Nodes\LinkNode && $node->url !== null) {
            $node->url = addTrackingParams($node->url);  // transform phase may mutate
        }
        return null;
    });
});
```

The enter/leave callbacks may return a replacement `Node`, `NodeTraverser::RemoveNode` (only for nodes held in list containers such as `ContentNode`), `DontTraverseChildren`, or `StopTraversal`.

`addHandler()` is a plain notification mechanism: all handlers for an event run in registration order and return values are ignored. The only built-in event is `afterParse`.

### Example: collecting statistics

```php
$stats = ['images' => 0, 'headings' => 0];

$texy->addHandler('afterParse', function (Texy\Nodes\DocumentNode $doc) use (&$stats) {
    (new Texy\NodeTraverser)->traverse($doc, function (Texy\Node $node) use (&$stats) {
        match (true) {
            $node instanceof Texy\Nodes\ImageNode => $stats['images']++,
            $node instanceof Texy\Nodes\HeadingNode => $stats['headings']++,
            default => null,
        };
        return null;
    });
});
```

## Which extension point to choose

Apply the configuration placement test from [architecture.md](architecture.md): if the change affects the *document's meaning for every output format* (dropping links, rewriting URLs, restructuring), do it in a transform pass – the Markdown output then gets it for free. If it only affects the *look of one output format* (tags, attributes, wrappers), use a renderer handler.
