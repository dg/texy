<?php declare(strict_types=1);

/**
 * ADDING NEW BLOCK SYNTAX FOR CODE HIGHLIGHTING
 *
 * This example shows an alternative approach: instead of just handling
 * /--code blocks, we register completely NEW syntax patterns that Texy
 * will recognize.
 *
 * WHAT YOU'LL LEARN:
 * - How to register new block syntax using addBlockSyntax()
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

use Texy\{Helpers, Range};
use Texy\Nodes\CodeBlockNode;
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}

if (@!include __DIR__ . '/vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}


$texy = new Texy;


// ============================================================
// REGISTER HTML HANDLER FOR CODE BLOCKS
// ============================================================

// Custom handler for CodeBlockNode that applies FSHL syntax highlighting
$texy->htmlOutput->addRenderer(
	function (CodeBlockNode $node, Html\Renderer $gen) use ($texy): ?Html\Element {
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
		$content = Helpers::outdent($node->code);

		$fshl = new FSHL\Highlighter(new FSHL\Output\Html, FSHL\Highlighter::OPTION_TAB_INDENT);
		$content = $fshl->highlight($content, new $langClass);

		$elPre = new Html\Element('pre');
		$gen->decorateElement($node->modifier, $elPre);
		$elPre->attrs['class'] = strtolower($lang);
		$elPre->create('code')->add(new Html\Raw($content)); // Raw = do not process further

		return $elPre;
	},
);


// ============================================================
// REGISTER NEW BLOCK PATTERNS
// ============================================================

// Register NEW syntax: recognize blocks starting with <?php
// When Texy sees <?php at the start of a line, it will highlight it as PHP
$texy->addBlockSyntax( // Must be multiline (m) and single-line mode (s)
	'phpBlockSyntax',
	'~^
		<\?php \n .+? \n \?>
	$~ms',
	function (Texy\Parsing\ParseContext $context, array $matches, array $offsets, string $name): CodeBlockNode {
		[$content] = $matches;
		$lang = $name === 'phpBlockSyntax' ? 'php' : 'javascript';

		return new CodeBlockNode(
			'block/code',
			$content,
			$lang,
			null,
			new Range($offsets[0], strlen($matches[0])),
		);
	},
);

// Register NEW syntax: recognize <script> ... </script> blocks
$texy->addBlockSyntax( // block patterns must be multiline and line-anchored
	'scriptBlockSyntax',
	'~^
		<script (?: type=.?text/javascript.?)? > \n
		.+? \n
		</script>
	$~ms',
	function (Texy\Parsing\ParseContext $context, array $matches, array $offsets, string $name): CodeBlockNode {
		[$content] = $matches;

		return new CodeBlockNode(
			'block/code',
			$content,
			'html', // HTML lexer handles <script> tags well
			null,
			new Range($offsets[0], strlen($matches[0])),
		);
	},
);


// Process the text
$text = file_get_contents(__DIR__ . '/sample2.texy');
$document = $texy->parse($text);
$texy->transform($document);
$html = $texy->renderHtml($document);


// Display the result with stylesheets
header('Content-type: text/html; charset=utf-8');
echo '<link rel="stylesheet" href="style.css">';
echo '<link rel="stylesheet" href="fshl.css">';
echo '<title>' . Texy\Nodes\HeadingNode::collectFrom($document)[0]?->getTitle() . '</title>';
echo $html;


// Show the generated HTML source code
echo '<hr>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
