<?php declare(strict_types=1);

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
 * *bold text*        ? <b class="myclass">bold text</b>
 * _italic text_      ? <i class="myclass">italic text</i>
 * .h1
 * Title              ? <h1>Title</h1>
 * .perex
 * Text               ? <div class="perex">Text</div>
 */


if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;

// IMPORTANT: Disable conflicting default syntax
// We want to use * for bold, but Texy uses it for emphasis
// We must disable the default * patterns first
$texy->allowed['phrase/strong+em'] = false;  // Disables ***text***
$texy->allowed['phrase/strong'] = false;     // Disables **text**
$texy->allowed['phrase/em-alt'] = false;     // Disables *text*
$texy->allowed['phrase/em-alt2'] = false;    // Disables *text*


// ============================================================
// REGISTER INLINE (LINE) PATTERNS
// ============================================================

// Add new syntax: *bold*
// The pattern captures the text between asterisks
$texy->registerLinePattern(
	'userInlineHandler',  // Handler function to call
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
	'userInlineHandler',                      // Same handler, different name
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
	'userBlockHandler',
	'~^
		\. ([a-z0-9]+) \n
		(.+)
	$~m', // Match .tagname\ncontent
	'myBlockSyntax1',
);


/**
 * Handler for our inline syntax (*bold* and _italic_)
 *
 * @param Texy\LineParser $parser  The parser instance
 * @param array $matches           Regex matches [full, content, modifier]
 * @param string $name             Which syntax matched (myInlineSyntax1 or 2)
 */
function userInlineHandler(Texy\InlineParser $parser, array $matches, string $name): Texy\HtmlElement|string
{
	[, $mContent, $mMod] = $matches;

	$texy = $parser->getTexy();

	// Decide which tag to use based on which syntax matched
	$tag = $name === 'myInlineSyntax1' ? 'b' : 'i';
	$el = new Texy\HtmlElement($tag);

	// If a modifier was used (like *text*.[class]), apply it
	$mod = new Texy\Modifier($mMod);
	$mod->decorate($texy, $el);

	// Add our own class to all elements
	$el->attrs['class'] = 'myclass';

	// Set the text content
	$el->setText($mContent);

	// IMPORTANT: Tell Texy to look for more patterns inside this element
	// This allows nesting like *bold _and italic_ text*
	$parser->again = true;

	return $el;
}


/**
 * Handler for our block syntax (.h1, .perex, etc.)
 *
 * @param Texy\BlockParser $parser  The parser instance
 * @param array $matches            Regex matches [full, tagname, content]
 * @param string $name              Syntax name (myBlockSyntax1)
 */
function userBlockHandler(Texy\BlockParser $parser, array $matches, string $name): Texy\HtmlElement|string|null
{
	[, $mTag, $mText] = $matches;

	$texy = $parser->getTexy();

	// Handle special case: .perex creates a <div class="perex">
	if ($mTag === 'perex') {
		$el = new Texy\HtmlElement('div');
		$el->attrs['class'][] = 'perex';
	} else {
		// Otherwise, use the tag name directly (.h1 ? <h1>)
		$el = new Texy\HtmlElement($mTag);
	}

	// Parse the content with Texy (allows inline formatting inside)
	$el->parseLine($texy, $mText);

	return $el;
}


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
