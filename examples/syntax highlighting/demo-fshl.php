<?php declare(strict_types=1);

/**
 * SYNTAX HIGHLIGHTING FOR CODE BLOCKS
 *
 * This example shows how to add syntax highlighting to code blocks
 * using the FSHL library.
 *
 * WHAT YOU'LL LEARN:
 * - How to intercept code blocks using a handler
 * - How to detect the programming language from the block type
 * - How to integrate a syntax highlighting library
 *
 * TEXY CODE BLOCK SYNTAX:
 * /--php
 * echo "Hello World";
 * \--
 *
 * The language (php, html, javascript, sql) is specified after /--
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
 * Custom handler for code blocks
 *
 * This handler intercepts /--code blocks and applies syntax highlighting
 * based on the specified language.
 */
function blockHandler(Texy\HandlerInvocation $invocation, $blocktype, $content, $lang, Texy\Modifier $modifier): ?Texy\HtmlElement
{
	// Only handle code blocks, let other blocks pass through
	if ($blocktype !== 'block/code') {
		return $invocation->proceed();
	}

	// Map of supported languages to FSHL lexer classes
	static $lexers = [
		'html' => FSHL\Lexer\Html::class,
		'javascript' => FSHL\Lexer\Javascript::class,
		'php' => FSHL\Lexer\Php::class,
		'sql' => FSHL\Lexer\Sql::class,
	];

	// If we don't support this language, skip highlighting
	if (!isset($lexers[$lang])) {
		return null;
	}

	$langClass = $lexers[$lang];
	$texy = $invocation->getTexy();

	// Remove common indentation from the code
	$content = Texy\Helpers::outdent($content);

	// Apply syntax highlighting
	$fshl = new FSHL\Highlighter(new FSHL\Output\Html, FSHL\Highlighter::OPTION_TAB_INDENT);
	$content = $fshl->highlight($content, new $langClass);

	// Tell Texy not to process the highlighted HTML further
	$content = $texy->protect($content, $texy::CONTENT_BLOCK);

	// Build the HTML structure: <pre class="php"><code>...</code></pre>
	$elPre = new Texy\HtmlElement('pre');
	if ($modifier) {
		$modifier->decorate($texy, $elPre);
	}
	$elPre->attrs['class'] = strtolower($lang);

	$elPre->create('code', $content);

	return $elPre;
}


$texy = new Texy;

// Register our code block handler
$texy->addHandler('block', 'blockHandler');


// Process the text
$text = file_get_contents(__DIR__ . '/sample.texy');
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
