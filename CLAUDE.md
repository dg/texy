# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Texy is a text-to-HTML converter library that transforms plain text in Texy syntax into valid (X)HTML. It's a mature PHP library (currently v3.2.6) that supports typography, images, links, tables, lists, and integrates with Latte templating engine.

## Essential Commands

### Testing
```bash
# Run all tests
composer run tester
# or
vendor/bin/tester tests -s

# Run specific test file
vendor/bin/tester tests/Texy/blocks.phpt -s
```

### Static Analysis
```bash
# Run PHPStan (level 5)
composer run phpstan
# or
vendor/bin/phpstan analyse --no-progress
```

## Architecture

### Core Structure

**src/Texy/** - Main parsing engine
- `Texy.php` - Main converter class with configuration and processing
- `Parser.php` - Base parser interface
- `BlockParser.php` - Parses block-level elements
- `LineParser.php` - Parses inline/line-level elements
- `HtmlElement.php` - HTML element representation and manipulation
- `Modifier.php` - Handles CSS classes, IDs, and styling modifiers
- `Helpers.php` - Utility functions
- `Regexp.php` - Regular expression utilities
- `Patterns.php` - Common regex patterns
- `DTD.php` - HTML Document Type Definition rules

**src/Texy/Modules/** - Processing modules (each handles specific syntax)
- `BlockModule.php` - Special blocks (code, html, text blocks with `/--` syntax)
- `HeadingModule.php` - Headings
- `ParagraphModule.php` - Paragraphs
- `ListModule.php` - Ordered and unordered lists
- `TableModule.php` - Tables
- `FigureModule.php` - Figures with images
- `ImageModule.php` - Images
- `LinkModule.php` - Hyperlinks
- `PhraseModule.php` - Inline formatting (bold, italic, etc.)
- `TypographyModule.php` - Typography corrections (quotes, dashes, etc.)
- `EmoticonModule.php` - Emoticons/smileys
- `BlockQuoteModule.php` - Block quotes
- `HorizLineModule.php` - Horizontal lines
- `HtmlModule.php` - Inline HTML tags
- `HtmlOutputModule.php` - HTML output formatting
- `ScriptModule.php` - Script/style tag handling
- `LongWordsModule.php` - Long word hyphenation

**src/Bridges/Latte/** - Latte templating integration
- `TexyExtension.php` - Latte v3 extension providing `{texy}` tag and `|texy` filter
- `TexyNode.php` - AST node for Latte compilation

### Module System

Each module is a self-contained processor that:
1. Registers patterns via `registerBlockPattern()` or `registerLinePattern()`
2. Provides handler callbacks for matched patterns
3. Returns `HtmlElement` instances or HTML strings
4. Can be enabled/disabled via `$texy->allowed[]` configuration

Modules communicate through the `HandlerInvocation` system, allowing modules to wrap or modify each other's output.

### Processing Flow

1. **Input** → `Texy::process(string $text)`
2. **Block parsing** → `BlockParser` splits text into block-level elements
3. **Module processing** → Each module's patterns are matched and processed
4. **Line parsing** → `LineParser` processes inline elements within blocks
5. **HTML generation** → `HtmlElement` objects are converted to HTML strings
6. **Output** → Valid (X)HTML

Protection marks (CONTENT_MARKUP, CONTENT_REPLACED, etc.) prevent re-processing of already-processed content.

### Testing Architecture

- Uses Nette Tester with `.phpt` extension
- Test structure: `test('description', function() { ... })`
- Tests compare output against expected HTML files in `tests/Texy/expected/`
- Input Texy source files in `tests/Texy/sources/`
- `tests/bootstrap.php` sets up test environment

**Testing guidelines:**
- **Never use `Assert::contains()`** - it leads to false positives and missed bugs
- **Always test exact output** using `Assert::matchFile()` or `Assert::match()`
- `Assert::match()` ignores line ending differences (`\r\n` vs `\n`)
- Avoid wildcard patterns like `%a%` - test the complete output
- For AST tests, create separate expected files (e.g., `paragraphs-ast.html`)

Example test pattern:
```php
$texy = new Texy\Texy;
$texy->linkModule->root = 'xxx/';
Assert::matchFile(
    __DIR__ . '/expected/output.html',
    $texy->process(file_get_contents(__DIR__ . '/sources/input.texy')),
);
```

### Test Naming Convention

**Format:** `{subject}[-{aspect}].phpt`

- **Subject** = module/component name in singular (`image`, `link`, `heading`)
- **Aspect** = optional suffix for specific feature (`-reference`, `-handler`, `-syntax`)

**Common suffixes:**
- `-reference` — reference definitions (`[*img*]: url`)
- `-handler` — custom handlers
- `-syntax` — detailed syntax parsing
- `-alignment` — alignment features

**Expected files:** Use descriptive suffixes instead of numbers:
- `figure.html`, `figure-nocaption.html`, `figure-html5.html` (not `figure1.html`, `figure2.html`)

**Examples:**
- `image.phpt`, `image-reference.phpt`, `image-handler.phpt`
- `html.phpt`, `html-safe.phpt`, `html-none.phpt`

## PHP Requirements

- PHP 8.1 - 8.5 supported
- All files must include `declare(strict_types=1);`
- Follows Nette Coding Standard (PSR-12 based)
- Uses modern PHP syntax (constructor property promotion, match expressions, etc.)

## Key Configuration Properties

The `Texy` class exposes module instances for configuration:
- `$texy->headingModule` - Heading settings
- `$texy->linkModule` - Link processing (root paths, references)
- `$texy->imageModule` - Image processing (root paths, file checking)
- `$texy->htmlOutputModule` - HTML output settings (line wrap, doctype)
- `$texy->allowed[]` - Enable/disable specific syntax features

## Integration Notes

### Latte Integration
The `TexyExtension` provides two ways to use Texy in Latte templates:
1. Block tag: `{texy}...{/texy}`
2. Filter: `{$text|texy}`

Both accept a `Texy` instance or callable processor during construction.

### Typical Usage
```php
$texy = new Texy\Texy();
$texy->setOutputMode(Texy\Texy::HTML5);
$html = $texy->process($texyText);
```

## CI/CD

Three GitHub Actions workflows:
1. **tests.yml** - Runs Nette Tester on PHP 8.1-8.5, generates code coverage
2. **static-analysis.yml** - Runs PHPStan (informative only, can fail)
3. **coding-style.yml** - Validates code style with Nette Code Checker and Coding Standard

## Basic Usage Patterns

### Processing Text
```php
$texy = new Texy\Texy;
$html = $texy->process($text);        // Full document with blocks
$html = $texy->processLine($text);    // Single line without <p> wrapper
```

### Configuration Example
```php
$texy = new Texy\Texy;

// Image paths
$texy->imageModule->root = '/images/';
$texy->imageModule->fileRoot = __DIR__ . '/public/images/';

// Link paths
$texy->linkModule->root = '/articles/';

// Disable specific syntax features
$texy->allowed['image'] = false;
$texy->allowed['html/tag'] = false;

// Enable emoticons (disabled by default)
$texy->allowed['emoticon'] = true;
```

### Safe Mode for User Content
When processing untrusted user input (comments, forums):
```php
$texy = new Texy\Texy;
Texy\Configurator::safeMode($texy);
$html = $texy->process($userInput);
```

SafeMode disables classes/IDs/styles, allows only safe HTML tags, disables images, adds rel="nofollow", and filters URL schemes.

### Accessing Processing Results
```php
$html = $texy->process($text);

// First heading (suitable for <title>)
$title = $texy->headingModule->title;

// Table of contents
$toc = $texy->headingModule->TOC;
```

## Handler System (Chain of Responsibility)

Texy uses a sophisticated handler system allowing customization at two levels:

### Element Handlers
Modify behavior of existing elements (images, links, headings, etc.):

```php
$texy->addHandler('image', function(
    Texy\HandlerInvocation $invocation,
    Texy\Image $image,
    ?Texy\Link $link,
) {
    // Option 1: Modify input before processing
    $image->width ??= 800;

    // Option 2: Delegate to default handler
    $element = $invocation->proceed();

    // Option 3: Modify output
    if ($element) {
        $element->attrs['loading'] = 'lazy';
    }

    // Option 4: Replace completely (e.g., YouTube embed)
    if (str_starts_with($image->URL, 'youtube:')) {
        return $this->createYouTubeEmbed($image);
    }

    return $element;
});
```

**Execution order**: Last registered handler runs first, can call `$invocation->proceed()` to delegate to next handler or module's default implementation.

**Common elements**: `image`, `linkReference`, `linkEmail`, `linkURL`, `phrase`, `figure`, `heading`, `block`, `emoticon`

### Notification Handlers
Called for side effects (logging, modifications), don't return values:

```php
$texy->addHandler('beforeParse', function(
    Texy\Texy $texy,
    string &$text,
    bool $isSingleLine,
) {
    // Preprocess text, load reference definitions
});

$texy->addHandler('afterParse', function(
    Texy\Texy $texy,
    Texy\HtmlElement $DOM,
    bool $isSingleLine,
) {
    // Traverse and modify DOM tree
    foreach ($DOM->getIterator() as $child) {
        // Add attributes, build TOC, etc.
    }
});
```

**Common events**: `beforeParse`, `afterParse`, `afterList`, `afterTable`, `afterBlockquote`

## Custom Syntax Registration

### Line Syntax (Inline Elements)

```php
$texy->registerLinePattern(
    function(
        Texy\InlineParser $parser,
        array $matches,
        string $name,
    ): Texy\HtmlElement|string|null {
        $username = $matches[1];

        $el = new Texy\HtmlElement('a');
        $el->attrs['href'] = '/user/' . urlencode($username);
        $el->setText('@' . $username);

        return $el;
    },
    '#@@([a-z0-9_]+)#i',      // Pattern (not anchored)
    'custom/username',         // Unique syntax name
    '#@@#'                     // Optional optimization test
);
```

### Block Syntax (Multi-line Elements)
```php
$texy->registerBlockPattern(
    function(
        Texy\BlockParser $parser,
        array $matches,
        string $name,
    ): Texy\HtmlElement|string|null {
        $type = $matches[1];
        $content = $matches[2];

        $el = new Texy\HtmlElement('div');
        $el->attrs['class'][] = 'alert-' . $type;

        // Parse content with Texy syntax
        $el->parseBlock($parser->getTexy(), trim($content));

        return $el;
    },
    '#^:::(warning|info|danger)\n(.+?)(?=\n:::|$)#s',  // Pattern (anchored with ^)
    'custom/alert'
);
```

**BlockParser API**:
- `$parser->next($pattern, &$matches)` - Match next line, advance position
- `$parser->moveBackward($lines)` - Move back N lines
- `$parser->isIndented()` - Check if current block is indented

## Protection Marks and Nesting

Texy uses special control characters to prevent re-processing of already-processed content. These are critical for typography and longwords modules.

### Protection Mark Types

| Mark | Hex | Constant | Usage | In longwords exclusion? |
|------|-----|----------|-------|-------------------------|
| `\x14` | 0x14 | `CONTENT_BLOCK` | Block elements (`<div>`, `<p>`, `<table>`) | YES - splits text processing |
| `\x15` | 0x15 | `CONTENT_TEXTUAL` | Protected text (code, notexy) | YES - skipped entirely |
| `\x16` | 0x16 | `CONTENT_REPLACED` | Replaced elements (`<img>`, `<br>`, URLs, emails) | YES - skipped entirely |
| `\x17` | 0x17 | `CONTENT_MARKUP` | Inline markup (`<strong>`, `<em>`, `<a>`) | NO - text flows through |

### How Protection Works

1. **CONTENT_BLOCK** (`\x14`) - Splits text for post-processing. `invokePostLineHandlers()` uses `explode(CONTENT_BLOCK, $text)` and only processes even-indexed segments.

2. **CONTENT_REPLACED/TEXTUAL** (`\x15`, `\x16`) - Excluded from longwords pattern `[^ \n\t\x14\x15\x16...]`. Content is completely skipped.

3. **CONTENT_MARKUP** (`\x17`) - NOT excluded from longwords. This allows text to flow through inline tags for hyphenation: `<strong>very-long-word</strong>` can be hyphenated.

### Protection Key Format

Protection keys have format: `[TYPE][COUNTER][TYPE]` where:
- TYPE is one of `\x14-\x17`
- COUNTER uses `\x18-\x1F` for octal-encoded digits

Example: `\x17\x18\x17` = CONTENT_MARKUP with counter 0

### Usage in HtmlGenerator

```php
// Block elements - use CONTENT_BLOCK
$this->protect("<p>", self::ContentBlock);

// Inline markup - use CONTENT_MARKUP
$this->protect("<strong>", self::ContentMarkup);

// Replaced elements - use CONTENT_REPLACED
$this->protect("<img ...>", self::ContentReplaced);

// Protected text - use CONTENT_TEXTUAL
$this->protect($codeContent, self::ContentTextual);
```

## Space Freezing in Attributes

To prevent line wrapping inside HTML attributes, spaces are "frozen" using `Helpers::freezeSpaces()`:

```php
// Freezes spaces: " " → \x01, "\t" → \x02, "\r" → \x03, "\n" → \x04
$value = Helpers::freezeSpaces($attrValue);

// At the end, unfreeze: \x01 → " ", etc.
$html = Helpers::unfreezeSpaces($html);
```

This ensures that `alt="long description with spaces"` won't be broken across lines by the formatter.

**Patterns** should exclude already-processed content: `[^\x14-\x1F]` excludes all protected content.

## Key Architectural Concepts

### Terminology
- **Syntax**: Named construct (e.g., `phrase/strong`, `image`)
- **Pattern**: Regular expression defining syntax appearance
- **Syntax Handler**: Function called when pattern matches, invokes element handlers
- **Element**: Type of processable item (e.g., `image`, `linkURL`)
- **Element Handler**: Function processing specific element type, uses `proceed()` for chaining
- **Notification Handler**: Event callback, doesn't return value or affect processing

### Two Parser Types
- **BlockParser**: Processes block structures (paragraphs, headings, lists). Blocks never overlap.
- **LineParser**: Processes inline syntaxes (formatting, links, images). Allows nesting via progressive expansion.

### Modifier System
Modifiers add attributes: `.(title)[class #id]{style:value}<align>^valign`

```php
// Parsing (use static factory method)
$mod = Texy\Modifier::parse($modifierText);
$mod = Texy\Modifier::parse($modifierText, $offset);  // with position tracking

// Access
$mod->id;          // HTML id
$mod->classes;     // array of classes
$mod->styles;      // array of CSS properties
$mod->attrs;       // custom attributes (data-*, aria-*, etc.)
$mod->hAlign;      // left, right, center, justify
$mod->vAlign;      // top, middle, bottom
$mod->title;       // title attribute
$mod->position;    // Position object

// Apply to element (legacy, deprecated)
$mod->decorate($texy, $element);
```

**Note:** Constructor `new Modifier($text)` and `setProperties()` are deprecated. Use `Modifier::parse()` instead.

## Security Practices

Always use `Configurator::safeMode()` for user-generated content. It:
- Restricts HTML tags to safe subset (`strong`, `em`, `a`, `code`, etc.)
- Disables classes, IDs, and inline styles
- Filters URL schemes (only http, https, ftp, mailto)
- Adds `rel="nofollow"` to links
- Disables images and HTML comments

Additional security controls:
```php
$texy->allowedTags = Texy\Texy::NONE;  // Disable all HTML
$texy->allowedClasses = ['highlight', 'important'];  // Whitelist classes
$texy->allowedStyles = ['color', 'background-color'];  // Whitelist CSS
$texy->urlSchemeFilters[Texy\Texy::FILTER_ANCHOR] = '#https?:#Ai';
```

## Important Implementation Details

1. **Module Registration**: Modules register patterns and handlers in their constructors. Registration order matters - earlier patterns have priority when multiple match at same position.

2. **DOM Construction**: `HtmlElement` builds DOM tree. Use `parseLine()` or `parseBlock()` methods to recursively parse content with Texy syntax.

3. **Well-formed HTML**: `HtmlOutputModule` ensures valid HTML by auto-closing tags, fixing nesting, and formatting output.

4. **Typography**: `TypographyModule` post-processes text for typographic correctness (quotes, dashes, non-breaking spaces). Locale-aware (`cs`, `en`, `fr`, `de`, `pl`).

5. **Reference System**: LinkModule and ImageModule support reference definitions separated from usage. References stored in module's internal dictionary.
