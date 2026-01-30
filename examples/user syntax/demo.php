<?php

/**
 * CREATING YOUR OWN MARKUP SYNTAX
 *
 * This example shows how to add completely new syntax to Texy.
 * We'll create:
 * - *bold* (instead of Texy's default **bold**)
 * - _italic_ (instead of Texy's default //italic//)
 * - .h1 and .perex block syntax
 *
 * WHAT YOU'LL LEARN:
 * - How to register inline (line) patterns with registerLinePattern()
 * - How to register block patterns with registerBlockPattern()
 * - How to disable conflicting default syntax
 * - How to parse content inside your custom elements
 * - How to apply modifiers to your elements
 *
 * CUSTOM SYNTAX WE CREATE:
 * *bold text*        → <b class="myclass">bold text</b>
 * _italic text_      → <i class="myclass">italic text</i>
 * .h1
 * Title              → <h1>Title</h1>
 * .perex
 * Text               → <div class="perex">Text</div>
 */

declare(strict_types=1);

use Texy\Modifier;
use Texy\Nodes\ParagraphNode;
use Texy\Nodes\PhraseNode;
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;

// IMPORTANT: Disable conflicting default syntax
// We want to use * for bold, but Texy uses it for emphasis
// We must disable the default * patterns first
$texy->allowed[Texy\Syntax::StrongEmphasis] = false;         // Disables ***text***
$texy->allowed[Texy\Syntax::Strong] = false;                  // Disables **text**
$texy->allowed[Texy\Syntax::EmphasisSingleAsterisk] = false;  // Disables *text*
$texy->allowed[Texy\Syntax::EmphasisSingleAsterisk2] = false; // Disables *text*


// ============================================================
// REGISTER INLINE (LINE) PATTERNS
// ============================================================

// Add new syntax: *bold*
// The pattern captures the text between asterisks
$texy->registerLinePattern(
	function (Texy\ParseContext $context, array $matches, string $name) use ($texy): PhraseNode {
		[, $mContent, $mMod] = $matches;

		// Parse the content recursively (allows nesting like *bold _and italic_ text*)
		$content = $context->parseInline(trim($mContent));

		return new PhraseNode(
			$content,
			$name, // 'myInlineSyntax1' or 'myInlineSyntax2'
			Modifier::parse($mMod),
		);
	},
	'~
		(?<! \* ) \* (?! [ *] )
		(.+)
		' . Texy\Patterns::MODIFIER . '?
		(?<! [ *] ) \* (?! \* )
	~U', // regular expression
	'myInlineSyntax1', // Unique name for this syntax
);

// Add new syntax: _italic_
$texy->registerLinePattern(
	function (Texy\ParseContext $context, array $matches, string $name) use ($texy): PhraseNode {
		[, $mContent, $mMod] = $matches;

		$content = $context->parseInline(trim($mContent));

		return new PhraseNode(
			$content,
			$name,
			Modifier::parse($mMod),
		);
	},
	'~
		(?<! _ ) _ (?! [ _] )
		(.+)
		' . Texy\Patterns::MODIFIER . '?
		(?<! [ _] ) _ (?! _ )
	~U',
	'myInlineSyntax2',
);


// ============================================================
// REGISTER BLOCK PATTERNS
// ============================================================

// Add new syntax: .tagname followed by content on next line
// Examples: .h1, .h2, .perex, etc.
$texy->registerBlockPattern(
	function (Texy\ParseContext $context, array $matches, string $name): ParagraphNode {
		[, $mTag, $mText] = $matches;

		// Parse the content with inline parser
		$content = $context->parseInline($mText);

		// Create a paragraph node with modifier containing our tag info
		$modifier = new Modifier;
		$modifier->attrs['data-tag'] = $mTag;

		return new ParagraphNode($content, $modifier);
	},
	'~^
		\. ([a-z0-9]+) \n
		(.+)
	$~m', // Match .tagname\ncontent
	'myBlockSyntax1',
);


// ============================================================
// REGISTER HTML HANDLERS FOR CUSTOM NODE TYPES
// ============================================================

// Handler for our custom phrase types (myInlineSyntax1 and myInlineSyntax2)
$texy->htmlGenerator->registerHandler(
	function (PhraseNode $node, Html\Generator $gen) use ($texy): ?Html\Element {
		// Only handle our custom types
		if ($node->type !== 'myInlineSyntax1' && $node->type !== 'myInlineSyntax2') {
			return null; // Let default handler process it
		}

		// Decide which tag to use based on which syntax matched
		$tag = $node->type === 'myInlineSyntax1' ? 'b' : 'i';
		$el = new Html\Element($tag);

		// If a modifier was used (like *text*.[class]), apply it
		$node->modifier?->decorate($texy, $el);

		// Add our own class to all elements
		$el->attrs['class'] = 'myclass';

		// Generate content
		$el->children = $gen->renderNodes($node->content->children);

		return $el;
	},
);

// Handler for our custom block syntax
$texy->htmlGenerator->registerHandler(
	function (ParagraphNode $node, Html\Generator $gen) use ($texy): ?Html\Element {
		// Only handle paragraphs with our data-tag attribute
		$tag = $node->modifier?->attrs['data-tag'] ?? null;
		if ($tag === null) {
			return null; // Let default handler process it
		}

		// Handle special case: .perex creates a <div class="perex">
		if ($tag === 'perex') {
			$el = new Html\Element('div');
			$el->attrs['class'][] = 'perex';
		} else {
			// Otherwise, use the tag name directly (.h1 → <h1>)
			$el = new Html\Element($tag);
		}

		// Generate content
		$el->children = $gen->renderNodes($node->content->children);

		return $el;
	},
);


// Process the text
$text = file_get_contents(__DIR__ . '/syntax.texy');
$html = $texy->process($text);


// Display the result
echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';
echo $html;


// Show the generated HTML source code
echo '<hr>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
