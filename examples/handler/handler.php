<?php declare(strict_types=1);

/**
 * TEXY! AST HANDLER REFERENCE
 *
 * This file is a REFERENCE showing how to customize HTML output in Texy 4.0.
 * In the new AST architecture, parsing produces Node objects, and HTML
 * generation is handled separately via $texy->htmlOutput->registerHandler().
 *
 * WHAT YOU'LL LEARN:
 * - How to register HTML handlers for specific Node types
 * - All available Node types you can customize
 * - The correct handler signatures
 * - How to use event handlers (beforeParse, afterParse)
 *
 * HOW IT WORKS:
 * 1. Texy parses text into AST (Abstract Syntax Tree) of Node objects
 * 2. HTML Renderer traverses the AST and generates HTML
 * 3. Your handlers are called based on the first parameter type (Node class)
 * 4. Return Html\Element or string to customize output, or null for default
 */

use Texy\Nodes;
use Texy\Nodes\DocumentNode;
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;

// ============================================================
// HTML GENERATION HANDLERS
// ============================================================
//
// Register handlers using: $texy->htmlOutput->registerHandler($handler)
// The handler's first parameter type determines which Node class it handles.
//
// Handler signature:
// function(NodeType $node, Html\Renderer $gen, ?Closure $previous): Html\Element|string|null
//
// Parameters:
// - $node: The Node object to render
// - $gen: Renderer instance (use $gen->renderNodes() for nested content)
// - $previous: Previous handler for this Node type (or null if none)
//
// Return values:
// - Html\Element: Custom HTML element to render
// - string: Raw HTML or text to render
// - null: Delegate to previous handler (calls $previous automatically)
//
// This allows chaining handlers - return null to let the default handler process the node.


// ============================================================
// INLINE NODE HANDLERS
// ============================================================

// EmoticonNode - handles :-)  :-(  etc.
$texy->htmlOutput->registerHandler(
	fn(Nodes\EmoticonNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// ImageNode - handles [* image.jpg *]
$texy->htmlOutput->registerHandler(
	fn(Nodes\ImageNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// LinkNode - handles "text":url and [text](url)
$texy->htmlOutput->registerHandler(
	fn(Nodes\LinkNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// LinkReferenceNode - handles [ref] where ref is not defined
$texy->htmlOutput->registerHandler(
	fn(Nodes\LinkReferenceNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// UrlNode - handles auto-detected URLs like https://example.com
$texy->htmlOutput->registerHandler(
	fn(Nodes\UrlNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// EmailNode - handles auto-detected emails like john@example.com
$texy->htmlOutput->registerHandler(
	fn(Nodes\EmailNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// PhraseNode - handles **bold**, //italic//, etc.
$texy->htmlOutput->registerHandler(
	fn(Nodes\PhraseNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// TextNode - plain text
$texy->htmlOutput->registerHandler(
	fn(Nodes\TextNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// RawTextNode - ''notexy'' (unprocessed text)
$texy->htmlOutput->registerHandler(
	fn(Nodes\RawTextNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);


// ============================================================
// BLOCK NODE HANDLERS
// ============================================================

// ParagraphNode - paragraphs
$texy->htmlOutput->registerHandler(
	fn(Nodes\ParagraphNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// HeadingNode - headings (=== or ### style)
$texy->htmlOutput->registerHandler(
	fn(Nodes\HeadingNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// FigureNode - images with captions [* img *] *** caption
$texy->htmlOutput->registerHandler(
	fn(Nodes\FigureNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// CodeBlockNode - /--code and ```code blocks
$texy->htmlOutput->registerHandler(
	fn(Nodes\CodeBlockNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// BlockQuoteNode - blockquotes
$texy->htmlOutput->registerHandler(
	fn(Nodes\BlockQuoteNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// ListNode - ordered/unordered lists
$texy->htmlOutput->registerHandler(
	fn(Nodes\ListNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// ListItemNode - list items
$texy->htmlOutput->registerHandler(
	fn(Nodes\ListItemNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// DefinitionListNode - definition lists
$texy->htmlOutput->registerHandler(
	fn(Nodes\DefinitionListNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// TableNode - tables
$texy->htmlOutput->registerHandler(
	fn(Nodes\TableNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// TableRowNode - table rows
$texy->htmlOutput->registerHandler(
	fn(Nodes\TableRowNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// TableCellNode - table cells
$texy->htmlOutput->registerHandler(
	fn(Nodes\TableCellNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// HorizontalRuleNode - horizontal lines (---)
$texy->htmlOutput->registerHandler(
	fn(Nodes\HorizontalRuleNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// HtmlTagNode - HTML tags in the input
$texy->htmlOutput->registerHandler(
	fn(Nodes\HtmlTagNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// HtmlCommentNode - HTML comments <!-- -->
$texy->htmlOutput->registerHandler(
	fn(Nodes\HtmlCommentNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// DirectiveNode - {{command: args}} syntax
$texy->htmlOutput->registerHandler(
	fn(Nodes\DirectiveNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);

// SectionNode - /--div blocks
$texy->htmlOutput->registerHandler(
	fn(Nodes\SectionNode $node, Html\Renderer $gen): Html\Element|string|null => null,
);


// ============================================================
// EVENT HANDLERS
// ============================================================
// These are NOT HTML generation handlers - they're called during parsing.

// Called BEFORE parsing starts - can modify input text
$texy->addHandler('beforeParse', function (Texy\Texy $texy, string &$text, bool $isSingleLine): void {
	// $text - input text (can be modified!)
	// $isSingleLine - true if processing a single line
});

// Called AFTER parsing completes - can modify the AST
$texy->addHandler('afterParse', function (DocumentNode $doc): void {
	// $doc - the root DocumentNode containing the entire AST
	// Use NodeTraverser to walk and modify the tree
});


// ============================================================
// EXAMPLE: Custom YouTube embed
// ============================================================

$texy->htmlOutput->registerHandler(
	function (Nodes\ImageNode $node, Html\Renderer $gen) use ($texy): Html\Element|string|null {
		if ($node->url && str_starts_with($node->url, 'youtube:')) {
			$videoId = substr($node->url, 8);
			$width = $node->width ?: 640;
			$height = $node->height ?: 360;

			$code = '<iframe width="' . $width . '" height="' . $height . '"'
				. ' src="https://www.youtube.com/embed/' . htmlspecialchars($videoId) . '"'
				. ' frameborder="0" allowfullscreen></iframe>';

			return $texy->protect($code, Texy::CONTENT_BLOCK);
		}
		return null;
	},
);


// ============================================================
// EXAMPLE: Custom reference handler (comment mentions)
// ============================================================

$texy->htmlOutput->registerHandler(
	function (Nodes\LinkReferenceNode $node, Html\Renderer $gen) use ($texy): Html\Element|string|null {
		// Handle numeric references as comment mentions
		if (ctype_digit($node->identifier)) {
			$el = new Html\Element('a');
			$el->attrs['href'] = '#comment-' . $node->identifier;
			$el->attrs['class'] = 'mention';
			$el->setText('[' . $node->identifier . ']');
			return $el;
		}
		return null;
	},
);


// Process some sample text
$text = <<<'TEXY'
Title
=====

This is a **bold** text with //emphasis//.

[* youtube:dQw4w9WgXcQ 400x300 *]

See comment [1] for details.
TEXY;

$html = $texy->process($text);

header('Content-type: text/html; charset=utf-8');
echo $html;
