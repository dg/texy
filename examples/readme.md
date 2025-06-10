# Texy Examples

This directory contains examples showing how to use Texy. Each example is self-contained and demonstrates a specific feature.

## Getting Started

Before running the examples, install the dependencies:

```bash
composer install
```

Then open any example in your browser or run it from command line:

```bash
php "1-2-3 start/demo.php"
```

---

## Examples Overview

### Basic Usage

| Example | Description |
|---------|-------------|
| [1-2-3 start](1-2-3%20start/) | **Start here!** Basic Texy setup in 3 steps |
| [typography](typography/) | Typography-only processing (quotes, dashes, spaces) |

### Customizing Output

| Example | Description |
|---------|-------------|
| [images](images/) | Control image paths, define images by name, change the `<img>` |
| [links](links/) | Rewrite links in a transform pass, handle custom URL schemes |
| [headings](headings/) | Configure heading levels, generate TOC |
| [Figure as Definition List](Figure%20as%20Definition%20List/) | Change figure HTML from `<div>` to `<dl>` |

### Security

| Example | Description |
|---------|-------------|
| [HTML filtering](HTML%20filtering/) | Control allowed HTML tags (important for user input!) |
| [modifiers](modifiers/) | Control allowed CSS classes and styles |
| [references](references/) | Build a safe comment system with mentions of other comments resolved in a pass |

### Adding Features

| Example | Description |
|---------|-------------|
| [emoticons](emoticons/) | Enable and customize smileys |
| [syntax highlighting](syntax%20highlighting/) | Add code highlighting with FSHL library |
| [Youtube video](Youtube%20video/) | Embed videos using image syntax |
| [user syntax](user%20syntax/) | Create your own markup syntax |

---

## Quick Reference

### Basic Processing

```php
$texy = new Texy\Texy;
$html = $texy->toHtml($text);
```

### Safe Mode (for user input)

```php
$texy = new Texy\Texy;
Texy\Configurator::safeMode($texy);
$html = $texy->toHtml($userInput);
```

### Changing the Document

```php
$texy = new Texy\Texy;
$texy->addHandler(function (Texy\Nodes\DocumentNode $document): void {
    foreach ($document->find(Texy\Nodes\LinkNode::class) as $link) {
        // change the link, every output format gets it
    }
});
```

### Customizing Output

```php
$texy = new Texy\Texy;
$texy->htmlOutput->addRenderer(
    function (Texy\Nodes\ImageNode $node, Texy\Output\Html\Renderer $renderer, ?Closure $previous) {
        // modify the rendered image, or return null to delegate
        return $previous($node);
    },
);
```

### Registering Custom Syntax

```php
$texy = new Texy\Texy;
$texy->registerLinePattern(
    $parser,               // fn(ParseContext, array $matches, array $offsets): ?Node
    '#@@(\w+)#',           // regex pattern
    'custom/username',     // syntax name
);
```

---

## Texy Syntax Cheat Sheet

### Text Formatting
- `**bold**` → **bold**
- `//italic//` → *italic*
- `` `code` `` → `code`
- `"link text":url` → link

### Images
- `[* image.jpg *]` → image
- `[* image.jpg *]:link` → linked image
- `[* image.jpg <]` → left-floated
- `[* image.jpg >]` → right-floated

### Headings
```
Title
=====

Subtitle
--------

### Also a heading ###
```

### Lists
```
- item 1
- item 2
- item 3

1) first
2) second
3) third
```

### Code Blocks
```
/--php
echo "Hello";
\--
```

---

## Learn More

- [Texy Documentation](https://texy.deegee.dev)
- [GitHub Repository](https://github.com/dg/texy)
