<?php declare(strict_types=1);

/**
 * CREATING YOUR OWN MARKUP SYNTAX
 *
 * This example shows how to add new syntax to Texy.
 * We'll create:
 * - *bold* (instead of Texy's default **bold**)
 * - _italic_ (instead of Texy's default //italic//)
 *
 * WHAT YOU'LL LEARN:
 * - How to register inline patterns with registerLinePattern()
 * - How to disable conflicting default syntax
 * - How to parse content inside your custom elements
 * - How to apply modifiers to your elements
 *
 * The new syntaxes create nodes Texy already knows, a strong or emphasized phrase,
 * so they need no renderer and every output format (HTML, plain text) understands
 * them. Which HTML tag a phrase gets is up to $phraseTags.
 *
 * CUSTOM SYNTAX WE CREATE:
 * *bold text*        → <b>bold text</b>
 * _italic text_      → <i>italic text</i>
 */

use Texy\{Modifier, Range, Syntax};
use Texy\Nodes\{PhraseNode, PhraseType};
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
$texy->htmlOutput->phraseTags[PhraseType::Strong->value] = 'b';
$texy->htmlOutput->phraseTags[PhraseType::Emphasis->value] = 'i';


// ============================================================
// REGISTER INLINE PATTERNS
// ============================================================

// Add new syntax: *bold*
// The pattern captures the text between asterisks and an optional modifier
$texy->registerLinePattern(
	function (ParseContext $context, array $matches, array $offsets): PhraseNode {
		[, $mContent, $mMod] = $matches;

		// Parse the content recursively (allows nesting like *bold _and italic_ text*)
		$content = $context->parseInline(trim($mContent), $offsets[1]);

		return new PhraseNode(
			$content,
			PhraseType::Strong, // the same phrase as **bold** creates
			Modifier::parse($mMod),
			new Range($offsets[0], strlen($matches[0])),
		);
	},
	'~
		(?<! \* ) \* (?! [ *] )
		(.+)
		' . Texy\Patterns::MODIFIER . '?
		(?<! [ *] ) \* (?! \* )
	~Ux', // regular expression
	'custom/bold', // Unique name for this syntax
);

// Add new syntax: _italic_
$texy->registerLinePattern(
	function (ParseContext $context, array $matches, array $offsets): PhraseNode {
		[, $mContent, $mMod] = $matches;

		$content = $context->parseInline(trim($mContent), $offsets[1]);

		return new PhraseNode(
			$content,
			PhraseType::Emphasis,
			Modifier::parse($mMod),
			new Range($offsets[0], strlen($matches[0])),
		);
	},
	'~
		(?<! _ ) _ (?! [ _] )
		(.+)
		' . Texy\Patterns::MODIFIER . '?
		(?<! [ _] ) _ (?! _ )
	~Ux',
	'custom/italic',
);


// Process the text
$text = <<<'TEXY'
	User syntax - this is *bold* and this _italic_
	TEXY;
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
