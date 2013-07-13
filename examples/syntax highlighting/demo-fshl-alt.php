<?php

/**
 * This demo shows how combine Texy! with syntax highlighter FSHL
 *       - define user callback (for /--code elements)
 */


// include libs
require_once dirname(__FILE__) . '/../../src/texy.php';

$fshlPath = dirname(__FILE__).'/fshl/';
@include_once $fshlPath . 'fshl.php';


if (!class_exists('fshlParser')) {
	die('DOWNLOAD <a href="http://hvge.sk/scripts/fshl/">FSHL</a> AND UNPACK TO FSHL FOLDER FIRST!');
}


/**
 * User handler for code block
 *
 * @param TexyHandlerInvocation  handler invocation
 * @param string  block type
 * @param string  text to highlight
 * @param string  language
 * @param TexyModifier modifier
 * @return TexyHtml
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

	$elPre = TexyHtml::el('pre');
	if ($modifier) $modifier->decorate($texy, $elPre);
	$elPre->attrs['class'] = strtolower($lang);

	$elCode = $elPre->create('code', $content);

	return $elPre;
}


/**
 * Pattern handler for PHP & JavaScript block syntaxes
 *
 * @param TexyBlockParser
 * @param array      regexp matches
 * @param string     pattern name
 * @return TexyHtml|string|FALSE
 */
function codeBlockHandler($parser, $matches, $name)
{
	list($content) = $matches;
	$lang = $name === 'phpBlockSyntax' ? 'PHP' : 'HTML';

	$fshl = new fshlParser('HTML_UTF8', P_TAB_INDENT);
	$texy = $parser->getTexy();
	$content = $fshl->highlightString($lang, $content);
	$content = $texy->protect($content, Texy::CONTENT_BLOCK);

	$elPre = TexyHtml::el('pre');
	$elPre->attrs['class'] = strtolower($lang);

	$elCode = $elPre->create('code', $content);

	return $elPre;
}


$texy = new Texy();
$texy->addHandler('block', 'blockHandler');

// add new syntax: <?php ... ? >
$texy->registerBlockPattern(
	'codeBlockHandler',
	'#^<\\?php\n.+?\n\\?>$#ms', // block patterns must be multiline and line-anchored
	'phpBlockSyntax'
);

// add new syntax: <script ...> ... </script>
$texy->registerBlockPattern(
	'codeBlockHandler',
	'#^<script(?: type=.?text/javascript.?)?>\n.+?\n</script>$#ms', // block patterns must be multiline and line-anchored
	'scriptBlockSyntax'
);

// processing
$text = file_get_contents('sample2.texy');
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
