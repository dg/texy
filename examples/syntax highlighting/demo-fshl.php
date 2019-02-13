<?php

/**
 * This demo shows how combine Texy! with syntax highlighter FSHL
 *       - define user callback (for /--code elements)
 */

declare(strict_types=1);


// include libs
if (@!include __DIR__ . '/../vendor/autoload.php') {
	die('Install packages using `composer install`');
}

if (!class_exists('FSHL\Highlighter')) {
	die('Install FSHL using `composer require kukulich/fshl`');
}


/**
 * User handler for code block
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

	$elCode = $elPre->create('code', $content);

	return $elPre;
}


$texy = new Texy;
$texy->addHandler('block', 'blockHandler');

// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!

// echo Geshi Stylesheet
header('Content-type: text/html; charset=utf-8');
echo '<style>', file_get_contents('style.css'), '</style>';
echo '<title>' . $texy->headingModule->title . '</title>';
// echo formated output
echo $html;

// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
