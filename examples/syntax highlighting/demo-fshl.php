<?php

/**
 * This demo shows how combine Texy! with syntax highlighter FSHL
 *       - define user callback (for /--code elements)
 */


// include libs
require_once __DIR__ . '/../../src/texy.php';

$fshlPath = __DIR__.'/fshl/';
@include_once $fshlPath . 'fshl.php';


if (!class_exists('fshlParser')) {
	die('DOWNLOAD <a href="http://hvge.sk/scripts/fshl/">FSHL</a> AND UNPACK TO FSHL FOLDER FIRST!');
}


/**
 * User handler for code block
 *
 * @param Texy\HandlerInvocation  handler invocation
 * @param string  block type
 * @param string  text to highlight
 * @param string  language
 * @param Texy\Modifier modifier
 * @return Texy\HtmlElement
 */
function blockHandler($invocation, $blocktype, $content, $lang, $modifier)
{
	if ($blocktype !== 'block/code') {
		return $invocation->proceed();
	}

	$lang = strtoupper($lang);
	if ($lang == 'JAVASCRIPT') $lang = 'JS';

	$fshl = new fshlParser('HTML_UTF8', P_TAB_INDENT);
	if (!$fshl->isLanguage($lang)) {
		return $invocation->proceed();
	}

	$texy = $invocation->getTexy();
	$content = Texy::outdent($content);
	$content = $fshl->highlightString($lang, $content);
	$content = $texy->protect($content, Texy::CONTENT_BLOCK);

	$elPre = Texy\HtmlElement::el('pre');
	if ($modifier) $modifier->decorate($texy, $elPre);
	$elPre->attrs['class'] = strtolower($lang);

	$elCode = $elPre->create('code', $content);

	return $elPre;
}


$texy = new Texy();
$texy->addHandler('block', 'blockHandler');

// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!

// echo Geshi Stylesheet
header('Content-type: text/html; charset=utf-8');
echo '<style type="text/css">'. file_get_contents($fshlPath.'styles/COHEN_style.css') . '</style>';
echo '<title>' . $texy->headingModule->title . '</title>';
// echo formated output
echo $html;

// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
