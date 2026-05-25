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

$texy->htmlOutput->addRenderer(
    function (Nodes\ImageNode $node, Html\Renderer $gen, ?Closure $previous): Html\Element|Html\Raw|string|null {
        // your logic
    },
);
```

Registration validates the parameter: a nullable, union or untyped first parameter could never be dispatched to, so it is refused with an exception right away. Dispatch respects inheritance – a node subclass without its own renderer uses the renderer of its nearest ancestor, so `class MyImageNode extends ImageNode` renders like an image until you register a renderer for it.

A node no renderer is found for at all **degrades to its content**: the wrapper disappears, the children render as usual. That is what keeps a document renderable in every format – you register a renderer for the format you care about, and `toText()` still works on the same tree instead of failing on a class it has never heard of.

Handlers for one node class form a chain, executed **from the last registered to the first**; the generator's default renderer sits at the end. Your handler can:

- **replace rendering entirely** – return its own result,
- **delegate** – call `$previous($node, $gen)` (the previous handler, or the default renderer) and optionally post-process its result,
- **decline** – return `null`, which delegates to the previous handler automatically.

Valid return values are `Html\Element`, `Html\Raw`, a plain string (display text – it will be HTML-escaped), or `null` to delegate. The same mechanism applies to the other generators, with string results.

The same handler works for a construct wherever it appears – e.g. an `ImageNode` handler fires for inline images and for the image inside a figure alike, because the figure renderer delegates through `renderNode()`.

Handlers must respect the render-phase purity rule: **do not mutate the node or the modifier** – if you need a modified variant, clone it.

### Example: YouTube embed

```php
use Texy\Nodes\ImageNode;
use Texy\Output\Html;

$texy->htmlOutput->addRenderer(
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
$texy->htmlOutput->addRenderer(
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

For document-level changes, register a pass with `$texy->addPass()`; `transform()` calls it with the `DocumentNode` after the built-in passes and before the typography, and the AST may be freely changed there. A pass that only finishes recognizing the source, without consulting definitions or changing the meaning, may be registered with `Texy\Stage::Syntax` instead; it then runs in `parse()` and must keep ranges exact (see [architecture.md](architecture.md#two-states-of-the-tree)). A `Stage::Finalize` pass runs after the typography.

Most passes only visit nodes of one kind. `Texy\NodeHelpers::find()` collects them in traversal order, so the pass is a plain `foreach`:

```php
$texy->addPass(function (Texy\Nodes\DocumentNode $doc) {
    foreach (Texy\NodeHelpers::find($doc, Texy\Nodes\LinkNode::class) as $node) {
        if ($node->url !== null) {
            $node->url = addTrackingParams($node->url);  // transform phase may mutate
        }
    }
});
```

The filter is either a node class, which narrows the returned type to it, or a predicate `callable(Node): bool`. `NodeHelpers::findFirst()` returns the first match or `null` and stops the walk there, and `NodeHelpers::clone()` makes a deep copy of a tree.

To restructure the tree (replace or remove nodes, or skip whole subtrees), walk it with `Texy\NodeTraverser`:

```php
$texy->addPass(function (Texy\Nodes\DocumentNode $doc) {
    (new Texy\NodeTraverser)->traverse($doc, fn(Texy\Node $node) => $node instanceof Texy\Nodes\HtmlCommentNode
        ? Texy\NodeTraverser::RemoveNode
        : null);
});
```

The enter/leave callbacks may return a replacement `Node`, `NodeTraverser::RemoveNode` (only for nodes held in list containers such as `ContentNode`), `DontTraverseChildren`, or `StopTraversal`. Unlike `find()`, which collects the nodes first, the traverser walks the tree as your callback changes it.

Passes run in `transform()`, in registration order, and return values are ignored. Passes registered by modules always run before yours, so you cannot get in front of them; where that matters, call `parse()`, work on the AST, and then `transform()`.

### Example: collecting statistics

```php
$stats = [];

$texy->addPass(function (Texy\Nodes\DocumentNode $doc) use (&$stats) {
    $stats['images'] = count(Texy\NodeHelpers::find($doc, Texy\Nodes\ImageNode::class));
    $stats['headings'] = count(Texy\NodeHelpers::find($doc, Texy\Nodes\HeadingNode::class));
});
```

## Directives

`{{name: value}}` in the document is a directive. Register a handler for one with `directiveModule->addDirective()`; it receives the value, the comma-separated arguments and the node, and returns a replacement node, or `null` to drop the directive:

```php
$texy->directiveModule->addDirective('include', fn(?string $value) => new Texy\Nodes\HtmlCommentNode("include $value"));
```

A directive nobody handles is left in the document and renders as the text the author wrote. To learn about those, register a fallback; it takes the same arguments as a named handler, so the name is read from the node:

```php
$texy->directiveModule->setFallback(function (?string $value, array $args, Texy\Nodes\DirectiveNode $node) {
    echo 'Unknown directive ', $node->parseContent()['name'];
    return null;   // drops it, like any other handler
});
```

Handlers run in the **first** pass, before the built-in ones. That is what makes them the right place for a directive that configures how the rest of the document is processed – a plain `addPass()` would come too late, because module passes always run first:

```php
$texy->directiveModule->addDirective('title', function (?string $value) use ($texy) {
    $texy->headingModule->top = 2;   // HeadingPass has not run yet
    return null;
});
```

## Which extension point to choose

Apply the configuration placement test from [architecture.md](architecture.md): if the change affects the *document's meaning for every output format* (dropping links, rewriting URLs, restructuring), do it in a transform pass – every output format then gets it for free. If it only affects the *look of one output format* (tags, attributes, wrappers), use a renderer handler. A `{{directive}}` written by the author is served by `directiveModule->addDirective()`.
