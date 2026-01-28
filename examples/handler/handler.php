<?php declare(strict_types=1);

/**
 * TEXY! HANDLER REFERENCE
 *
 * This file is a REFERENCE showing all available handlers in Texy.
 * It demonstrates the correct function signatures for each handler type.
 *
 * WHAT YOU'LL LEARN:
 * - All element handlers you can use (image, link, phrase, heading, etc.)
 * - All notification handlers (beforeParse, afterParse, afterTable, etc.)
 * - The correct parameters each handler receives
 *
 * HOW TO USE:
 * Copy the handler you need into your own code and modify it.
 * Each handler calls $invocation->proceed() to let Texy do its default processing.
 * You can modify the input before calling proceed(), or modify the output after.
 */


if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;
$handler = new myHandler;

// ============================================================
// ELEMENT HANDLERS
// These let you customize how specific elements are processed.
// Your handler is called BEFORE Texy's default processing.
// Call $invocation->proceed() to let Texy do its thing.
// ============================================================

// Emoticons - handles :-)  :-(  etc.
$texy->addHandler('emoticon', $handler->emoticon(...));

// Images - handles [* image.jpg *]
$texy->addHandler('image', $handler->image(...));

// Reference links - handles [ref] where ref is defined elsewhere
$texy->addHandler('linkReference', $handler->linkReference(...));

// Email links - handles automatically detected emails like john@example.com
$texy->addHandler('linkEmail', $handler->linkEmail(...));

// URL links - handles automatically detected URLs like https://example.com
$texy->addHandler('linkURL', $handler->linkURL(...));

// Phrases - handles text formatting like **bold**, //italic//, etc.
$texy->addHandler('phrase', $handler->phrase(...));

// New references - handles [ref] where ref is NOT defined (you can define it dynamically)
$texy->addHandler('newReference', $handler->newReference(...));

// HTML comments - handles <!-- comment -->
$texy->addHandler('htmlComment', $handler->htmlComment(...));

// HTML tags - handles any HTML tag in the input
$texy->addHandler('htmlTag', $handler->htmlTag(...));

// Scripts - handles {{command: args}} syntax
$texy->addHandler('script', $handler->script(...));

// Figures - handles images with captions [* image.jpg *] *** Caption
$texy->addHandler('figure', $handler->figure(...));

// Headings - handles all heading syntaxes
$texy->addHandler('heading', $handler->heading(...));

// Horizontal lines - handles --- and ***
$texy->addHandler('horizline', $handler->horizline(...));

// Blocks - handles /--code, /--html, etc.
$texy->addHandler('block', $handler->block(...));


// ============================================================
// NOTIFICATION HANDLERS
// These are called AFTER certain structures are created.
// They don't return anything - use them for logging,
// modifying the DOM, or collecting statistics.
// ============================================================

// Called after a list (<ul> or <ol>) is created
$texy->addHandler('afterList', $handler->afterList(...));

// Called after a definition list (<dl>) is created
$texy->addHandler('afterDefinitionList', $handler->afterDefinitionList(...));

// Called after a table (<table>) is created
$texy->addHandler('afterTable', $handler->afterTable(...));

// Called after a blockquote (<blockquote>) is created
$texy->addHandler('afterBlockquote', $handler->afterBlockquote(...));

// Called BEFORE parsing starts - can modify input text
$texy->addHandler('beforeParse', $handler->beforeParse(...));

// Called AFTER parsing completes - can modify the DOM tree
$texy->addHandler('afterParse', $handler->afterParse(...));


/**
 * Example handler class showing all available handler signatures.
 * Copy the methods you need and modify them for your use case.
 */
class myHandler
{
	// ============================================================
	// INLINE ELEMENT HANDLERS
	// These handle elements that appear within lines of text.
	// ============================================================


	/**
	 * Emoticon handler - called for :-)  :-(  etc.
	 * @param string $emoticon The emoticon symbol
	 * @param string $rawEmoticon The original text (may include extra characters)
	 */
	public function emoticon(Texy\HandlerInvocation $invocation, $emoticon, $rawEmoticon): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/**
	 * Image handler - called for [* image.jpg *]
	 * @param Texy\Image $image Contains URL, dimensions, alt text
	 * @param Texy\Link|null $link If image is a link, contains link info
	 */
	public function image(
		Texy\HandlerInvocation $invocation,
		Texy\Image $image,
		?Texy\Link $link = null,
	): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/**
	 * Link reference handler - called for [ref] where ref is defined
	 * @param Texy\Link $link Contains URL and attributes
	 * @param string $content The HTML content to put inside the link
	 */
	public function linkReference(
		Texy\HandlerInvocation $invocation,
		Texy\Link $link,
		string $content,
	): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/**
	 * Email link handler - called for auto-detected emails
	 * @param Texy\Link $link Contains the email in URL property
	 */
	public function linkEmail(Texy\HandlerInvocation $invocation, Texy\Link $link): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/**
	 * URL link handler - called for auto-detected URLs
	 * @param Texy\Link $link Contains the URL
	 */
	public function linkURL(Texy\HandlerInvocation $invocation, Texy\Link $link): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/**
	 * Phrase handler - called for **bold**, //italic//, etc.
	 * @param string $phrase The syntax name (phrase/strong, phrase/em, etc.)
	 * @param string $content The text inside the phrase
	 * @param Texy\Modifier $modifier CSS classes, styles, etc.
	 * @param Texy\Link|null $link If phrase has a link attached
	 */
	public function phrase(
		Texy\HandlerInvocation $invocation,
		$phrase,
		$content,
		Texy\Modifier $modifier,
		?Texy\Link $link = null,
	): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/**
	 * New reference handler - called for [ref] where ref is NOT defined
	 * Use this to create links dynamically (like user mentions)
	 * @param string $name The reference name
	 */
	public function newReference(Texy\HandlerInvocation $invocation, $name): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/**
	 * HTML comment handler - called for <!-- comment -->
	 * @param string $content The comment text
	 */
	public function htmlComment(Texy\HandlerInvocation $invocation, $content): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/**
	 * HTML tag handler - called for any HTML tag in input
	 * @param Texy\HtmlElement $el The element being processed
	 * @param bool $isStart True for opening tag, false for closing
	 * @param bool|null $forceEmpty Force empty element
	 */
	public function htmlTag(
		Texy\HandlerInvocation $invocation,
		Texy\HtmlElement $el,
		$isStart,
		$forceEmpty = null,
	): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/**
	 * Script handler - called for {{command: args}}
	 * @param string $command The command name
	 * @param array $args Parsed arguments
	 * @param string $rawArgs Original argument string
	 */
	public function script(
		Texy\HandlerInvocation $invocation,
		$command,
		array $args,
		$rawArgs,
	): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	// ============================================================
	// BLOCK ELEMENT HANDLERS
	// These handle multi-line block structures.
	// ============================================================


	/**
	 * Figure handler - called for images with captions
	 * @param Texy\Image $image The image data
	 * @param Texy\Link|null $link If figure is a link
	 * @param string $content The caption text
	 * @param Texy\Modifier $modifier CSS classes, styles, etc.
	 */
	public function figure(
		Texy\HandlerInvocation $invocation,
		Texy\Image $image,
		?Texy\Link $link,
		$content,
		Texy\Modifier $modifier,
	): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/**
	 * Heading handler - called for all heading syntaxes
	 * @param int $level Heading level (1-6)
	 * @param string $content Heading text
	 * @param Texy\Modifier $modifier CSS classes, styles, etc.
	 * @param bool $isSurrounded True if ### style, false if underlined
	 */
	public function heading(
		Texy\HandlerInvocation $invocation,
		$level,
		$content,
		Texy\Modifier $modifier,
		$isSurrounded,
	): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/**
	 * Horizontal line handler - called for --- and ***
	 * @param string $type The characters used (- or *)
	 * @param Texy\Modifier $modifier CSS classes, styles, etc.
	 */
	public function horizline(
		Texy\HandlerInvocation $invocation,
		$type,
		Texy\Modifier $modifier,
	): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/**
	 * Block handler - called for /--code, /--html, etc.
	 * @param string $blocktype Block type with prefix (block/code, block/html)
	 * @param string $content Block content
	 * @param string|null $param Parameter after type (e.g., language for code)
	 * @param Texy\Modifier $modifier CSS classes, styles, etc.
	 */
	public function block(
		Texy\HandlerInvocation $invocation,
		$blocktype,
		$content,
		$param,
		Texy\Modifier $modifier,
	): Texy\HtmlElement|string
	{
		return $invocation->proceed();
	}


	// ============================================================
	// NOTIFICATION HANDLERS
	// These are called after structures are created.
	// They don't return anything - use for logging or DOM modification.
	// ============================================================


	/**
	 * Called after a list is created (<ul> or <ol>)
	 */
	public function afterList(Texy\BlockParser $parser, Texy\HtmlElement $element, Texy\Modifier $modifier): void
	{
	}


	/**
	 * Called after a definition list is created (<dl>)
	 */
	public function afterDefinitionList(
		Texy\BlockParser $parser,
		Texy\HtmlElement $element,
		Texy\Modifier $modifier,
	): void
	{
	}


	/**
	 * Called after a table is created (<table>)
	 */
	public function afterTable(Texy\BlockParser $parser, Texy\HtmlElement $element, Texy\Modifier $modifier): void
	{
	}


	/**
	 * Called after a blockquote is created (<blockquote>)
	 */
	public function afterBlockquote(Texy\BlockParser $parser, Texy\HtmlElement $element, Texy\Modifier $modifier): void
	{
	}


	// ============================================================
	// SPECIAL HANDLERS
	// These are called at specific points in the parsing process.
	// ============================================================


	/**
	 * Called BEFORE parsing starts
	 * Use this to pre-process the input text or set up data.
	 * @param Texy\Texy $texy The Texy instance
	 * @param string &$text The input text (can be modified!)
	 * @param bool $isSingleLine True if processing a single line
	 */
	public function beforeParse(Texy\Texy $texy, &$text, $isSingleLine): void
	{
	}


	/**
	 * Called AFTER parsing completes
	 * Use this to modify the DOM tree or collect statistics.
	 * @param Texy\Texy $texy The Texy instance
	 * @param Texy\HtmlElement $DOM The root element of the document
	 * @param bool $isSingleLine True if processing a single line
	 */
	public function afterParse(Texy\Texy $texy, Texy\HtmlElement $DOM, $isSingleLine): void
	{
	}
}
