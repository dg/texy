# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Texy is a text-to-HTML converter library that transforms plain text in Texy syntax into valid (X)HTML. It's a PHP library (v4.0-dev) with an AST-based architecture that supports typography, images, links, tables, lists, and integrates with Latte templating engine.

**Architecture**: Texy 4.0 uses a two-phase AST architecture:
1. **Parsing phase** - Converts Texy text into an Abstract Syntax Tree (34 node types)
2. **Generation phase** - Converts AST to HTML via pluggable handlers

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
- `Node.php` - Base class for all AST nodes
- `BlockParser.php` - Parses block-level elements into AST nodes
- `InlineParser.php` - Parses inline elements into AST nodes
- `ParseContext.php` - Context for parsing operations
- `Position.php` - Source position tracking
- `NodeTraverser.php` - AST traversal utility
- `Modifier.php` - Handles CSS classes, IDs, and styling modifiers
- `Helpers.php` - Utility functions
- `Regexp.php` - Regular expression utilities
- `Patterns.php` - Common regex patterns

**src/Texy/Nodes/** - AST node classes (34 types)
- Base classes: `BlockNode`, `InlineNode`, `ContentNode`
- Document: `DocumentNode`, `SectionNode`
- Block: `ParagraphNode`, `HeadingNode`, `CodeBlockNode`, `BlockQuoteNode`, `HorizontalRuleNode`
- Lists: `ListNode`, `ListItemNode`, `DefinitionListNode`
- Tables: `TableNode`, `TableRowNode`, `TableCellNode`
- Figures: `FigureNode`
- Inline: `TextNode`, `PhraseNode`, `LinkNode`, `ImageNode`, `EmoticonNode`, `LineBreakNode`
- Links/URLs: `UrlNode`, `EmailNode`, `LinkReferenceNode`
- Definitions: `LinkDefinitionNode`, `ImageDefinitionNode`
- HTML: `HtmlTagNode`, `HtmlCommentNode`, `RawTextNode`
- Special: `DirectiveNode`, `AnnotationNode`, `CommentNode`

**src/Texy/Output/Html/** - HTML generation
- `Generator.php` - AST→HTML dispatcher with handler registration
- `Element.php` - Lightweight HTML element builder
- `Formatter.php` - Output formatting and well-forming
- `Support.php` - Helper methods for rendering (content analysis, HTML tag validation)
  - *Previously documented as `Validator.php` which never existed*

**src/Texy/Output/Markdown/** - Markdown (GFM) generation *(planned, not yet implemented)*
- Directory structure prepared but implementation is pending

**src/Texy/Modules/** - Processing modules
- `BlockModule.php` - Special blocks (code, html, text with `/--` syntax)
- `HeadingModule.php` - Headings (uses afterParse for TOC)
- `ParagraphModule.php` - Paragraphs
- `ListModule.php` - Ordered and unordered lists
- `TableModule.php` - Tables
- `FigureModule.php` - Figures with images
- `ImageModule.php` - Images (uses afterParse for reference resolution)
- `LinkModule.php` - Hyperlinks (uses afterParse for reference resolution)
- `AutolinkModule.php` - Auto-detection of URLs and emails
- `PhraseModule.php` - Inline formatting (bold, italic, etc.)
- `TypographyModule.php` - Typography corrections (post-processing)
- `EmoticonModule.php` - Emoticons/smileys
- `BlockQuoteModule.php` - Block quotes
- `HorizontalRuleModule.php` - Horizontal lines
- `HtmlModule.php` - Inline HTML tags
- `DirectiveModule.php` - Script/directive handling
- `LongWordsModule.php` - Long word hyphenation (post-processing)

**src/Bridges/Latte/** - Latte templating integration
- `TexyExtension.php` - Latte v3 extension providing `{texy}` tag and `|texy` filter
- `TexyNode.php` - AST node for Latte compilation

### AST Node Hierarchy

All nodes inherit from `Texy\Node` which provides:
- `position: ?Position` - Source location tracking
- `getNodes(): Generator` - Child node iteration for traversal

**Node categories:**
- `BlockNode` - Block-level elements (paragraphs, headings, lists, tables)
- `InlineNode` - Inline elements (text, phrases, links, images)
- `ContentNode` - Container for child nodes (`children: array`)

**Key node properties:**
```php
// Most nodes have:
public ?Modifier $modifier;   // CSS classes, IDs, styles
public ?Position $position;   // Source location

// ContentNode (container):
public array $children;       // Child nodes (InlineNode|BlockNode)

// Specific nodes have semantic properties:
ImageNode: url, width, height
LinkNode: url, content (ContentNode), isImageLink
PhraseNode: content (ContentNode), type ('strong', 'em', etc.)
HeadingNode: content (ContentNode), level (1-6)
```

### Module System

Modules handle both parsing and HTML generation:

1. **Pattern Registration** - In `beforeParse()`, modules register patterns:
   ```php
   $texy->registerBlockPattern($this->parseHeading(...), $pattern, Syntax::Heading);
   $texy->registerLinePattern($this->parseImage(...), $pattern, Syntax::Image);
   ```

2. **Parsing** - Pattern handlers create AST nodes:
   ```php
   public function parseImage(ParseContext $context, array $matches, array $offsets): ImageNode
   {
       return new ImageNode($url, $width, $height, $modifier, $position);
   }
   ```

3. **HTML Generation** - Register handlers with the Generator:
   ```php
   // Handler closure (node type detected from first parameter)
   $texy->htmlGenerator->registerHandler(function(
       ImageNode $node,
       Html\Generator $generator,
       ?\Closure $previous,  // Previous handler in chain
   ): Html\Element|string|null {
       $el = new Html\Element('img');
       $el->attrs['src'] = $node->url;
       return $el;
   });
   ```
   > *Note: Earlier documentation mentioned `solve()` method - handlers are anonymous closures, not named methods.*

4. **afterParse Processing** - Some modules process the complete AST:
   ```php
   $texy->addHandler('afterParse', $this->resolveReferences(...));
   ```

### Processing Pipeline

```
1. Preprocessing
   └── Normalize line endings, tabs, remove soft hyphens

2. beforeParse()
   └── Modules register patterns

3. Parsing → AST
   ├── BlockParser creates BlockNode instances
   └── InlineParser creates InlineNode instances

4. afterParse(DocumentNode)
   ├── HeadingModule: Build TOC
   ├── LinkModule: Resolve references
   └── ImageModule: Resolve references

5. HTML Generation
   └── Generator dispatches to registered handlers

6. Post-processing
   ├── Typography fixes (TypographyModule.postLine)
   ├── Long word hyphenation (LongWordsModule.postLine)
   └── Formatter ensures well-formed HTML
```

### Handler System

**HTML Generation Handlers** - Registered via `$texy->htmlGenerator->registerHandler()`:

```php
// Register handler (node type detected from first parameter)
$texy->htmlGenerator->registerHandler(function(
    ImageNode $node,
    Html\Generator $generator,
    ?\Closure $previous,  // Previous handler in chain
): Html\Element|string|null {
    // Return null to delegate to previous handler
    if ($node->url === null) {
        return null;
    }

    // Or return Element/string to handle
    $el = new Html\Element('img');
    $el->attrs['src'] = $node->url;
    return $el;
});
```

**Notification Handlers** - For side effects (logging, AST modification):

```php
$texy->addHandler('afterParse', function(Nodes\DocumentNode $doc) {
    // Traverse and modify AST
    $traverser = new NodeTraverser;
    $traverser->traverse($doc,
        enter: function(Node $node) {
            // Called when entering a node
            // Return: Node (replace), DontTraverseChildren, StopTraversal, RemoveNode, or null
        },
        leave: function(Node $node) {
            // Called after processing children
        },
    );
});
```

**Events**: `afterParse`

> *Note: There is no `beforeParse` event. Text preprocessing is done via Module classes which have a `beforeParse(string &$text)` method called automatically.*

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
$texy->htmlGenerator->linkRoot = 'xxx/';
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

- PHP 8.2 - 8.5 supported
- All files must include `declare(strict_types=1);`
- Follows Nette Coding Standard (PSR-12 based)
- Uses modern PHP syntax (constructor property promotion, match expressions, etc.)

## Key Configuration Properties

The `Texy` class exposes module instances for configuration:
- `$texy->headingModule` - Heading settings, TOC access
- `$texy->linkModule` - Link reference definitions
- `$texy->imageModule` - Image reference definitions
- `$texy->phraseModule` - Inline phrase settings (e.g., `linksAllowed`)
- `$texy->emoticonModule` - Emoticon icons configuration
- `$texy->typographyModule` - Typography locale settings
- `$texy->longWordsModule` - Word hyphenation settings
- `$texy->htmlGenerator` - HTML generation handlers and output configuration
- `$texy->htmlOutputModule` - HTML output formatting (Formatter)
- `$texy->allowed[]` - Enable/disable specific syntax features

### HTML Generator Properties

The `$texy->htmlGenerator` exposes many configuration options:

**Image handling:**
- `imageRoot` - Base path for image URLs (default: `'images/'`)
- `imageFileRoot` - File system path for dimension detection
- `imageLeftClass` - CSS class for left-aligned images
- `imageRightClass` - CSS class for right-aligned images

**Link handling:**
- `linkRoot` - Base path for relative link URLs
- `linkNoFollow` - Add `rel="nofollow"` to external links (default: `false`)

**Figure handling:**
- `figureTagName` - Wrapper tag (default: `'div'`, can use `'figure'`)
- `figureClass` - CSS class for figures (default: `'figure'`)
- `figureLeftClass` / `figureRightClass` - Classes for aligned figures

**Alignment:**
- `alignClasses` - Map alignment to CSS classes instead of inline styles:
  ```php
  $texy->htmlGenerator->alignClasses = [
      'left' => 'text-left',
      'right' => 'text-right',
      'center' => 'text-center',
  ];
  ```

**Other options:**
- `obfuscateEmail` - Obfuscate email addresses (default: `true`)
- `shortenUrls` - Shorten displayed URLs (default: `true`)
- `nontextParagraph` - Element for image-only paragraphs (default: `'div'`)
- `passHtmlComments` - Pass HTML comments to output (default: `true`)
- `emoticonClass` - CSS class wrapper for emoticons
- `horizontalRuleClasses` - CSS classes for HR types (`'-'`, `'*'`)
- `phraseTags` - Syntax to HTML tag mapping (`Syntax::Strong` → `'strong'`, etc.)
- `allowedTags` - Whitelist of allowed HTML tags for passthrough

> **Migration note:** In earlier versions (Texy 3.x), image/link paths were configured on modules (`$texy->imageModule->root`, `$texy->linkModule->root`). In Texy 4.0, use `$texy->htmlGenerator->imageRoot`, `$texy->htmlGenerator->linkRoot` etc.

## Integration Notes

### Latte Integration
The `TexyExtension` provides two ways to use Texy in Latte templates:
1. Block tag: `{texy}...{/texy}`
2. Filter: `{$text|texy}`

Both accept a `Texy` instance or callable processor during construction.

### Typical Usage
```php
$texy = new Texy\Texy();
$html = $texy->process($texyText);
```

## CI/CD

Three GitHub Actions workflows:
1. **tests.yml** - Runs Nette Tester on PHP 8.2-8.5, generates code coverage
2. **static-analysis.yml** - Runs PHPStan (informative only, can fail)
3. **coding-style.yml** - Validates code style with Nette Code Checker and Coding Standard

## Basic Usage Patterns

### Processing Text
```php
$texy = new Texy\Texy;
$html = $texy->process($text);        // Full document with blocks
$html = $texy->processLine($text);    // Single line without <p> wrapper
$html = $texy->processTypo($text);    // Typography corrections only (no Texy markup)
```

### Configuration Example
```php
$texy = new Texy\Texy;

// Image paths (configured on htmlGenerator)
$texy->htmlGenerator->imageRoot = '/images/';
$texy->htmlGenerator->imageFileRoot = __DIR__ . '/public/images/';

// Link paths (configured on htmlGenerator)
$texy->htmlGenerator->linkRoot = '/articles/';

// Disable specific syntax features
$texy->allowed[Texy\Syntax::Image] = false;
$texy->allowed[Texy\Syntax::HtmlTag] = false;

// Enable emoticons (disabled by default)
$texy->allowed[Texy\Syntax::Emoticon] = true;
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

### Markdown Output (GFM) - NOT YET IMPLEMENTED

> **Note:** The Markdown generator is planned but not yet implemented. The API below shows the intended design.

```php
$texy = new Texy\Texy;
$ast = $texy->parse($text);

$generator = new Texy\Output\Markdown\Generator($texy);
$generator->headingStyle = 'atx';       // 'atx' (###) or 'setext' (===)
$generator->codeBlockStyle = 'fenced';  // 'fenced' (```) or 'indented'
$generator->linkStyle = 'inline';       // 'inline' or 'reference'
$markdown = $generator->render($ast);
```

**Planned limitations**: Modifiers (classes, IDs, styles) will be lost. Superscript/subscript and abbreviations will use HTML fallback.

## Custom Syntax Registration

### Inline Syntax

```php
$texy->registerLinePattern(
    function(
        Texy\ParseContext $context,
        array $matches,
        array $offsets,
    ): Texy\Nodes\InlineNode {
        $username = $matches[1];
        $position = new Texy\Position($offsets[0], strlen($matches[0]));

        // Create a LinkNode wrapping the username
        return new Texy\Nodes\LinkNode(
            url: '/user/' . urlencode($username),
            content: new Texy\Nodes\ContentNode([
                new Texy\Nodes\TextNode('@' . $username),
            ]),
            position: $position,
        );
    },
    '~@@([a-z0-9_]+)~i',      // Pattern
    'custom/username',         // Unique syntax name
);

// Register HTML generation handler
$texy->htmlGenerator->registerHandler(function(
    Texy\Nodes\LinkNode $node,
    Texy\Output\Html\Generator $generator,
    ?\Closure $previous,
): Texy\Output\Html\Element|string|null {
    // Let default handler process unless this is our custom link
    if (!str_starts_with($node->url ?? '', '/user/')) {
        return null; // Delegate to previous handler
    }

    // Delegate to default handler and modify result
    $element = $previous($node, $generator);
    $element->attrs['class'] = 'user-mention';
    return $element;
});
```

### Block Syntax

```php
$texy->registerBlockPattern(
    function(
        Texy\ParseContext $context,
        array $matches,
        array $offsets,
    ): Texy\Nodes\BlockNode {
        $type = $matches[1];
        $content = $matches[2];
        $position = new Texy\Position($offsets[0], strlen($matches[0]));

        // Parse content with Texy syntax
        $innerContent = $context->parseBlock(trim($content));

        // Create a custom block node (or use existing node types)
        return new Texy\Nodes\BlockQuoteNode(
            content: $innerContent,
            modifier: Texy\Modifier::parse('[alert-' . $type . ']'),
            position: $position,
        );
    },
    '~^:::(warning|info|danger)\n(.+?)(?=\n:::|$)~s',
    'custom/alert',
);
```

## Protection Marks

Texy uses special control characters to prevent re-processing of already-processed content. These are critical for typography and longwords modules.

### Protection Mark Types

| Mark | Hex | Constant | Usage |
|------|-----|----------|-------|
| `\x14` | 0x14 | `CONTENT_BLOCK` | Block elements (`<div>`, `<p>`, `<table>`) |
| `\x15` | 0x15 | `CONTENT_TEXTUAL` | Protected text (code, notexy) |
| `\x16` | 0x16 | `CONTENT_REPLACED` | Replaced elements (`<img>`, `<br>`, URLs) |
| `\x17` | 0x17 | `CONTENT_MARKUP` | Inline markup (`<strong>`, `<em>`, `<a>`) |

### Usage in Element.toString()

```php
// Element determines content type automatically:
$el = new Html\Element('p');      // CONTENT_BLOCK
$el = new Html\Element('strong'); // CONTENT_MARKUP
$el = new Html\Element('img');    // CONTENT_REPLACED
```

## Space Freezing in Attributes

To prevent line wrapping inside HTML attributes, spaces are "frozen" using `Helpers::freezeSpaces()`:

```php
// Freezes spaces: " " → \x01, "\t" → \x02, "\r" → \x03, "\n" → \x04
$value = Helpers::freezeSpaces($attrValue);

// At the end, unfreeze: \x01 → " ", etc.
$html = Helpers::unfreezeSpaces($html);
```

## Key Architectural Concepts

### Terminology
- **Syntax**: Named construct (e.g., `phrase/strong`, `image`)
- **Pattern**: Regular expression defining syntax appearance
- **AST Node**: Structured representation of parsed content
- **Generator Handler**: Function converting specific node type to HTML

### Two Parser Types
- **BlockParser**: Processes block structures (paragraphs, headings, lists). Creates `BlockNode` instances.
- **InlineParser**: Processes inline syntaxes (formatting, links, images). Creates `InlineNode` instances.

### Position Tracking

Position tracks source location with `offset` (byte position from document start) and `length` (byte length).

**Key principles:**
- Positions are **absolute** - relative to original document, not to extracted/transformed content
- Positions are **byte-based** - important for UTF-8 (日 = 3 bytes, not 1 character)
- `substr($source, $position->offset, $position->length)` should extract the original syntax

**For nested content** (blockquote, list, table cells):
- Content is extracted and transformed (prefixes like "> " or "- " removed)
- Modules must fix positions in parsed content using offset mapping
- `parseBlock()` and `parseInline()` accept optional `$baseOffset` parameter

**Example - verifying positions:**
```php
$doc = $texy->parse($source);
foreach ($doc->getNodes() as $node) {
    if ($node->position) {
        $extracted = substr($source, $node->position->offset, $node->position->length);
        // $extracted should match the original syntax that created this node
    }
}
```

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
```

## Security Practices

Always use `Configurator::safeMode()` for user-generated content. It:
- Restricts HTML tags to safe subset (`strong`, `em`, `a`, `code`, etc.)
- Disables classes, IDs, and inline styles
- Filters URL schemes (only http, https, ftp, mailto)
- Adds `rel="nofollow"` to links
- Disables images and HTML comments

Additional security controls:
```php
$texy->htmlGenerator->allowedTags = Texy\Texy::NONE;  // Disable all HTML
$texy->allowedClasses = ['highlight', 'important'];  // Whitelist classes
$texy->allowedStyles = ['color', 'background-color'];  // Whitelist CSS
$texy->urlSchemeFilters[Texy\Texy::FILTER_ANCHOR] = '#https?:#Ai';
```

## Important Implementation Details

1. **Module Registration**: Modules register patterns in `beforeParse()`. Registration order matters - earlier patterns have priority when multiple match at same position.

2. **AST Construction**: Parsing creates a tree of `Node` objects. Use `ContentNode` to hold child nodes. The `getNodes()` method enables traversal.

3. **HTML Generation**: `Generator` dispatches to registered handlers based on node class. Return `null` to delegate to previous handler (chain of responsibility).

4. **afterParse Processing**: `HeadingModule`, `LinkModule`, and `ImageModule` use afterParse to process the complete AST (build TOC, resolve references).

5. **Typography**: `TypographyModule` post-processes text for typographic correctness (quotes, dashes, non-breaking spaces). Locale-aware (`cs`, `en`, `fr`, `de`, `pl`).

6. **Reference System**: LinkModule and ImageModule support reference definitions. References are collected in afterParse and resolved via `NodeTraverser`.
