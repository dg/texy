<?php declare(strict_types=1);

/**
 * SYNTAX HIGHLIGHTING FOR CODE BLOCKS
 *
 * This example shows how to add syntax highlighting to code blocks
 * using the FSHL library.
 *
 * WHAT YOU'LL LEARN:
 * - How to intercept code blocks using an HTML renderer
 * - How to detect the programming language from the node
 * - How to integrate a syntax highlighting library
 * - How to change only the content of the element the default renderer builds
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

use Texy\Nodes\CodeBlockNode;
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}

if (@!include __DIR__ . '/vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}


$texy = new Texy\Texy;

// Register our code block renderer
// The first parameter type (CodeBlockNode) determines which node class the renderer processes
$texy->htmlOutput->addRenderer(
	function (CodeBlockNode $node, Html\Renderer $renderer, Closure $previous): ?Html\Element {
		// Map of supported languages to FSHL lexer classes
		static $lexers = [
			'html' => FSHL\Lexer\Html::class,
			'javascript' => FSHL\Lexer\Javascript::class,
			'php' => FSHL\Lexer\Php::class,
			'sql' => FSHL\Lexer\Sql::class,
		];

		$lang = $node->language;

		// If we don't support this language, delegate to default renderer
		if ($lang === null || !isset($lexers[$lang])) {
			return null;
		}

		$langClass = $lexers[$lang];

		// Apply syntax highlighting
		$fshl = new FSHL\Highlighter(new FSHL\Output\Html, FSHL\Highlighter::OPTION_TAB_INDENT);
		$content = $fshl->highlight($node->code, new $langClass);

		// The default renderer builds <pre class="language-php"><code>...</code></pre> with the modifier applied,
		// we only replace the content of <code>; Raw tells Texy not to escape the highlighted HTML
		$el = $previous($node);
		$el->children[0]->children = [new Html\Raw($content)];
		return $el;
	},
);


// Process the text
$text = file_get_contents(__DIR__ . '/sample.texy');
$document = $texy->transform($texy->parse($text));
$html = $texy->renderHtml($document);


// Display the result with stylesheets
header('Content-type: text/html; charset=utf-8');
echo '<link rel="stylesheet" href="style.css">';
echo '<link rel="stylesheet" href="fshl.css">';
echo '<title>' . ($document->getHeadings()[0] ?? null)?->getTitle() . '</title>';
echo $html;


// Show the generated HTML source code
echo '<hr>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
