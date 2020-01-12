<?php

/**
 * This demo shows how combine Texy! with syntax highlighter GeSHi
 *       - define user callback (for /--code elements)
 *       - load language, highlight and return stylesheet + html output
 */

declare(strict_types=1);


// include libs
if (@!include __DIR__ . '/../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


if (!class_exists('GeSHi')) {
	die('Install GeSHi using `composer require geshi/geshi`');
}


/**
 * User handler for code block
 */
function blockHandler(Texy\HandlerInvocation $invocation, $blocktype, $content, $lang, Texy\Modifier $modifier): Texy\HtmlElement
{
	if ($blocktype !== 'block/code') {
		return $invocation->proceed();
	}

	$texy = $invocation->getTexy();

	if ($lang == 'html') {
		$lang = 'html4strict';
	}
	$content = Texy\Helpers::outdent($content);
	$geshi = new GeSHi($content, $lang);

	// GeSHi could not find the language
	if ($geshi->error()) {
		return $invocation->proceed();
	}

	// do syntax-highlighting
	$geshi->set_encoding('UTF-8');
	$geshi->set_header_type(GESHI_HEADER_PRE);
	$geshi->enable_classes();
	$geshi->set_overall_style('color: #000066; border: 1px solid #d0d0d0; background-color: #f0f0f0;', true);
	$geshi->set_line_style('font: normal normal 95% \'Courier New\', Courier, monospace; color: #003030;', 'font-weight: bold; color: #006060;', true);
	$geshi->set_code_style('color: #000020;', 'color: #000020;');
	$geshi->set_link_styles(GESHI_LINK, 'color: #000060;');
	$geshi->set_link_styles(GESHI_HOVER, 'background-color: #f0f000;');

	// save generated stylesheet
	global $styleSheet;
	$styleSheet .= $geshi->get_stylesheet();

	$content = $geshi->parse_code();

	// check buggy GESHI, it sometimes produce not UTF-8 valid code :-((
	$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);

	// protect output is in HTML
	$content = $texy->protect($content, $texy::CONTENT_BLOCK);

	$el = new Texy\HtmlElement;
	$el->setText($content);
	return $el;
}


$texy = new Texy;
$texy->addHandler('block', 'blockHandler');

// prepare CSS stylesheet
$styleSheet = 'pre { padding:10px } ';

// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!

// echo Geshi Stylesheet
header('Content-type: text/html; charset=utf-8');
echo '<style type="text/css">' . $styleSheet . '</style>';
echo '<title>' . $texy->headingModule->title . '</title>';
// echo formated output
echo $html;

// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
