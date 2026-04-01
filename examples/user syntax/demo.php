<?php declare(strict_types=1);

/**
 * CREATING YOUR OWN MARKUP SYNTAX
 *
 * This example shows how to add new syntax to Texy.
 * We'll create:
 * - *bold* (instead of Texy's default **bold**)
 * - _italic_ (instead of Texy's default //italic//)
 * - a /--perex block
 *
 * WHAT YOU'LL LEARN:
 * - How to register inline syntaxes with addInlineSyntax()
 * - How to register a new /--type block with config->addBlockType()
 * - How to disable conflicting default syntax
 * - How to parse content inside your custom elements
 * - How to apply modifiers to your elements
 *
 * The new syntaxes create nodes Texy already knows, a strong or emphasized phrase
 * and a division, so they need no renderer and every output format (HTML, plain text)
 * understands them. Which HTML tag a phrase gets is up to $phraseTags.
 *
 * CUSTOM SYNTAX WE CREATE:
 * *bold text*        → <b>bold text</b>
 * _italic text_      → <i>italic text</i>
 * /--perex
 * Text
 * \--                → <div class="perex"><p>Text</p></div>
 */

use Texy\{Modifier, Range, Syntax};
use Texy\Nodes\{DivNode, PhraseNode};
use Texy\Parsing\ParseContext;

if (@!include __DIR__ . '/../../vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}


$texy = new Texy\Texy;

// IMPORTANT: Disable conflicting default syntax
// We want to use * for bold, but Texy uses it for emphasis
// We must disable the default * patterns first
$texy->allowedSyntax[Syntax::StrongEmphasis] = false;         // Disables ***text***
$texy->allowedSyntax[Syntax::Strong] = false;                  // Disables **text**
$texy->allowedSyntax[Syntax::EmphasisSingleAsterisk] = false;  // Disables *text*
$texy->allowedSyntax[Syntax::EmphasisSingleAsterisk2] = false; // Disables *text*

// Strong and emphasized phrases are rendered as <b> and <i> instead of <strong> and <em>
$texy->htmlOutput->phraseTags[Syntax::Strong] = 'b';
$texy->htmlOutput->phraseTags[Syntax::Emphasis] = 'i';


// ============================================================
// REGISTER INLINE PATTERNS
// ============================================================

// Add new syntax: *bold*
// The pattern captures the text between asterisks and an optional modifier
$texy->addInlineSyntax(
	'custom/bold', // Unique name for this syntax
	'~
		(?<! \* ) \* (?! [ *] )
		(.+)
		' . Texy\Patterns::Modifier . '?
		(?<! [ *] ) \* (?! \* )
	~Ux', // regular expression
	function (ParseContext $context, array $matches, array $offsets): PhraseNode {
		[, $mContent, $mMod] = $matches;

		// Parse the content recursively (allows nesting like *bold _and italic_ text*)
		$content = $context->parseInline(trim($mContent), $offsets[1]);

		return new PhraseNode(
			$content,
			Syntax::Strong, // the same phrase as **bold** creates
			Modifier::parse($mMod),
			new Range($offsets[0], strlen($matches[0])),
		);
	},
);

// Add new syntax: _italic_
$texy->addInlineSyntax(
	'custom/italic',
	'~
		(?<! _ ) _ (?! [ _] )
		(.+)
		' . Texy\Patterns::Modifier . '?
		(?<! [ _] ) _ (?! _ )
	~Ux',
	function (ParseContext $context, array $matches, array $offsets): PhraseNode {
		[, $mContent, $mMod] = $matches;

		$content = $context->parseInline(trim($mContent), $offsets[1]);

		return new PhraseNode(
			$content,
			Syntax::Emphasis,
			Modifier::parse($mMod),
			new Range($offsets[0], strlen($matches[0])),
		);
	},
);


// ============================================================
// REGISTER BLOCK TYPE
// ============================================================

// Add new block: /--perex ... \--
// It needs no pattern of its own, the block module hands over the content already outdented
$texy->config->addBlockType(
	'perex',
	function (ParseContext $context, string $content, ?string $param, ?Modifier $modifier): DivNode {
		// Keep the modifier written after /--perex and add our class
		$modifier ??= new Modifier;
		$modifier->classes['perex'] = true;

		// Parse the content as blocks, so it may hold paragraphs, lists, etc.
		return new DivNode($context->parseBlock($content), $modifier);
	},
);


// Process the text
$text = file_get_contents(__DIR__ . '/syntax.texy');
$html = $texy->toHtml($text);


// Display the result
echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';
echo $html;


// Show the generated HTML source code
echo '<hr>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
