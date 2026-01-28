<?php declare(strict_types=1);

/**
 * ADDING NEW BLOCK SYNTAX FOR CODE HIGHLIGHTING
 *
 * This example shows an alternative approach: instead of just handling
 * /--code blocks, we register completely NEW syntax patterns that Texy
 * will recognize.
 *
 * WHAT YOU'LL LEARN:
 * - How to register new block syntax using registerBlockPattern()
 * - Recognize <?php ... ?> blocks automatically
 * - Recognize <script> ... </script> blocks automatically
 * - Combine pattern-based and handler-based approaches
 *
 * NEW SYNTAX ADDED:
 * <?php
 * // PHP code here
 * ?>
 *
 * <script>
 * // JavaScript code here
 * </script>
 *
 * These will be automatically highlighted without needing /--code syntax.
 *
 * REQUIREMENTS:
 * Install FSHL: composer require kukulich/fshl
 */


if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}

if (@!include __DIR__ . '/vendor/autoload.php') {
	die('Install packages using `composer install`');
}


/**
 * Handler for standard /--code blocks (same as demo-fshl.php)
 */
function blockHandler(Texy\HandlerInvocation $invocation, $blocktype, $content, $lang, Texy\Modifier $modifier): ?Texy\HtmlElement
{
	if ($blocktype !== 'block/code') {
		return $invocation->proceed();
	}

	static $lexers = [
		'html' => FSHL\Lexer\Html::class,
		'javascript' => FSHL\Lexer\Javascript::class,
		'php' => FSHL\Lexer\Php::class,
		'sql' => FSHL\Lexer\Sql::class,
	];

	if (!isset($lexers[$lang])) {
		return null;
	}

	$langClass = $lexers[$lang];
	$texy = $invocation->getTexy();
	$content = Texy\Helpers::outdent($content);

	$fshl = new FSHL\Highlighter(new FSHL\Output\Html, FSHL\Highlighter::OPTION_TAB_INDENT);
	$content = $fshl->highlight($content, new $langClass);
	$content = $texy->protect($content, $texy::CONTENT_BLOCK);

	$elPre = new Texy\HtmlElement('pre');
	if ($modifier) {
		$modifier->decorate($texy, $elPre);
	}
	$elPre->attrs['class'] = strtolower($lang);
	$elPre->create('code', $content);

	return $elPre;
}


/**
 * Pattern handler for the NEW <?php ?> and <script> syntaxes
 *
 * This is called when our custom patterns match in the text.
 */
function codeBlockHandler(Texy\BlockParser $parser, array $matches, string $name): Texy\HtmlElement|string|null
{
	[$content] = $matches;

	// Choose the lexer based on which pattern matched
	$langClass = $name === 'phpBlockSyntax'
		? FSHL\Lexer\Php::class
		: FSHL\Lexer\Html::class;  // HTML lexer handles <script> tags well

	$texy = $parser->getTexy();

	// Apply syntax highlighting
	$fshl = new FSHL\Highlighter(new FSHL\Output\Html, FSHL\Highlighter::OPTION_TAB_INDENT);
	$content = $fshl->highlight($content, new $langClass);
	$content = $texy->protect($content, $texy::CONTENT_BLOCK);

	// Build the HTML
	$elPre = new Texy\HtmlElement('pre');
	$elPre->create('code', $content);

	return $elPre;
}


$texy = new Texy;

// Register the standard block handler for /--code syntax
$texy->addHandler('block', blockHandler(...));

// Register NEW syntax: recognize <?php ... ?​> blocks
// When Texy sees <?php at the start of a line, it will highlight it as PHP
$texy->registerBlockPattern(
	codeBlockHandler(...),
	'~^
		<\?php \n .+? \n \?>
	$~ms', // Must be multiline (m) and single-line mode (s)
	'phpBlockSyntax',
);

// Register NEW syntax: recognize <script> ... </script> blocks
$texy->registerBlockPattern(
	codeBlockHandler(...),
	'~^
		<script (?: type=.?text/javascript.?)? > \n
		.+? \n
		</script>
	$~ms', // block patterns must be multiline and line-anchored
	'scriptBlockSyntax',
);


// Process the text
$text = file_get_contents(__DIR__ . '/sample2.texy');
$html = $texy->process($text);


// Display the result with stylesheets
header('Content-type: text/html; charset=utf-8');
echo '<link rel="stylesheet" href="style.css">';
echo '<link rel="stylesheet" href="fshl.css">';
echo '<title>' . $texy->headingModule->title . '</title>';
echo $html;


// Show the generated HTML source code
echo '<hr>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
