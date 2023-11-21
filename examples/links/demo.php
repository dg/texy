<?php

/**
 * This demo shows how control links in Texy!
 */

declare(strict_types=1);


if (@!include __DIR__ . '/../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


function phraseHandler(Texy\HandlerInvocation $invocation, $phrase, $content, Texy\Modifier $modifier, Texy\Link $link = null): Texy\HtmlElement|string|null
{
	// is there link?
	if (!$link) {
		return $invocation->proceed();
	}

	if (Texy\Helpers::isRelative($link->URL)) {
		// modifiy link
		$link->URL = 'index?page=' . urlencode($link->URL);

	} elseif (substr($link->URL, 0, 5) === 'wiki:') {
		// modifiy link
		$link->URL = 'https://en.wikipedia.org/wiki/Special:Search?search=' . urlencode(substr($link->URL, 5));
	}

	return $invocation->proceed();
}


$texy = new Texy;

// configuration
$texy->addHandler('phrase', 'phraseHandler');

// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!


// echo formated output
header('Content-type: text/html; charset=utf-8');
echo $html;


// echo all embedded links
echo '<hr />';
echo '<pre>';
print_r($texy->summary['links']);
echo '</pre>';


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
