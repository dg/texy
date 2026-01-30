<?php

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

declare(strict_types=1);

use Texy\Helpers;
use Texy\Nodes\CodeBlockNode;
use Texy\Output\Html;
use Texy\Position;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}

if (@!include __DIR__ . '/vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;


// ============================================================
// REGISTER HTML HANDLER FOR CODE BLOCKS
// ============================================================

// Custom handler for CodeBlockNode that applies FSHL syntax highlighting
$texy->htmlGenerator->registerHandler(
	function (CodeBlockNode $node, Html\Generator $gen) use ($texy): ?Html\Element {
		static $lexers = [
			'html' => FSHL\Lexer\Html::class,
			'javascript' => FSHL\Lexer\Javascript::class,
			'php' => FSHL\Lexer\Php::class,
			'sql' => FSHL\Lexer\Sql::class,
		];

		$lang = $node->language;
		if ($lang === null || !isset($lexers[$lang])) {
			return null; // Let default handler process it
		}

		$langClass = $lexers[$lang];
		$content = Helpers::outdent($node->content);

		$fshl = new FSHL\Highlighter(new FSHL\Output\Html, FSHL\Highlighter::OPTION_TAB_INDENT);
		$content = $fshl->highlight($content, new $langClass);
		$content = $gen->protect($content, $gen::ContentBlock);

		$elPre = new Html\Element('pre');
		$gen->decorateElement($node->modifier, $elPre);
		$elPre->attrs['class'] = strtolower($lang);
		$elPre->create('code', $content);

		return $elPre;
	},
);


// ============================================================
// REGISTER NEW BLOCK PATTERNS
// ============================================================

// Register NEW syntax: recognize <?php ... ?​> blocks
// When Texy sees <?php at the start of a line, it will highlight it as PHP
$texy->registerBlockPattern(
	function (Texy\ParseContext $context, array $matches, array $offsets, string $name): CodeBlockNode {
		[$content] = $matches;
		$lang = $name === 'phpBlockSyntax' ? 'php' : 'javascript';

		return new CodeBlockNode(
			'block/code',
			$content,
			$lang,
			null,
			new Position($offsets[0], strlen($matches[0])),
		);
	},
	'~^
		<\?php \n .+? \n \?>
	$~ms', // Must be multiline (m) and single-line mode (s)
	'phpBlockSyntax',
);

// Register NEW syntax: recognize <script> ... </script> blocks
$texy->registerBlockPattern(
	function (Texy\ParseContext $context, array $matches, array $offsets, string $name): CodeBlockNode {
		[$content] = $matches;

		return new CodeBlockNode(
			'block/code',
			$content,
			'html', // HTML lexer handles <script> tags well
			null,
			new Position($offsets[0], strlen($matches[0])),
		);
	},
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
