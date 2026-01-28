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
| [images](images/) | Control image paths, add handlers for special URLs |
| [links](links/) | Transform links, handle custom URL schemes |
| [headings](headings/) | Configure heading levels, generate TOC |
| [Figure as Definition List](Figure%20as%20Definition%20List/) | Change figure HTML from `<div>` to `<dl>` |

### Security

| Example | Description |
|---------|-------------|
| [HTML filtering](HTML%20filtering/) | Control allowed HTML tags (important for user input!) |
| [modifiers](modifiers/) | Control allowed CSS classes and styles |
| [references](references/) | Build a safe comment system with user mentions |

### Adding Features

| Example | Description |
|---------|-------------|
| [emoticons](emoticons/) | Enable and customize smileys |
| [syntax highlighting](syntax%20highlighting/) | Add code highlighting with FSHL library |
| [Youtube video](Youtube%20video/) | Embed videos using image syntax |
| [user syntax](user%20syntax/) | Create your own markup syntax |

### Reference

| Example | Description |
|---------|-------------|
| [handler](handler/) | Complete list of all available handlers |

---

## Quick Reference

### Basic Processing

```php
$texy = new Texy;
$html = $texy->process($text);
```

### Safe Mode (for user input)

```php
$texy = new Texy;
Texy\Configurator::safeMode($texy);
$html = $texy->process($userInput);
```

### Adding a Handler

```php
$texy = new Texy;
$texy->addHandler('image', function($invocation, $image, $link) {
    // Modify the image or link
    return $invocation->proceed();
});
```

### Registering Custom Syntax

```php
$texy = new Texy;
$texy->registerLinePattern(
    'myHandler',           // callback function
    '#@@(\w+)#',          // regex pattern
    'custom/username'      // syntax name
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
- `[*< image.jpg *]` → left-aligned
- `[*> image.jpg *]` → right-aligned

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

- [Texy Documentation](https://texy.info)
- [GitHub Repository](https://github.com/dg/texy)
