<?php

/**
 * SYNTAX HIGHLIGHTING FOR CODE BLOCKS
 *
 * This example shows how to add syntax highlighting to code blocks
 * using the FSHL library.
 *
 * WHAT YOU'LL LEARN:
 * - How to intercept code blocks using an HTML handler
 * - How to detect the programming language from the node
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

declare(strict_types=1);

use Texy\Helpers;
use Texy\Nodes\CodeBlockNode;
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}

if (@!include __DIR__ . '/vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;

// Register our code block handler
// The first parameter type (CodeBlockNode) determines which node class the handler processes
$texy->htmlGenerator->registerHandler(
	function (CodeBlockNode $node, Html\Generator $gen, ?Closure $previous) use ($texy): Html\Element|string|null {
		// Map of supported languages to FSHL lexer classes
		static $lexers = [
			'html' => FSHL\Lexer\Html::class,
			'javascript' => FSHL\Lexer\Javascript::class,
			'php' => FSHL\Lexer\Php::class,
			'sql' => FSHL\Lexer\Sql::class,
		];

		$lang = $node->language;

		// If we don't support this language, delegate to default handler
		if ($lang === null || !isset($lexers[$lang])) {
			return null;
		}

		$langClass = $lexers[$lang];

		// Remove common indentation from the code
		$content = Helpers::outdent($node->content);

		// Apply syntax highlighting
		$fshl = new FSHL\Highlighter(new FSHL\Output\Html, FSHL\Highlighter::OPTION_TAB_INDENT);
		$content = $fshl->highlight($content, new $langClass);

		// Tell Texy not to process the highlighted HTML further
		$content = $gen->protect($content, $gen::ContentBlock);

		// Build the HTML structure: <pre class="php"><code>...</code></pre>
		$elPre = new Html\Element('pre');
		$gen->decorateElement($node->modifier, $elPre);
		$elPre->attrs['class'] = strtolower($lang);

		$elPre->create('code', $content);

		return $elPre;
	},
);


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
