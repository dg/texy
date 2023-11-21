<?php

/**
 * This demo shows how implement Texy! as comment formatter
 *     - relative links to other comment
 *     - rel="nofollow"
 *     - used links checking (antispam)
 */

declare(strict_types=1);


if (@!include __DIR__ . '/../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


/**
 * User handler for unknown reference
 */
function newReferenceHandler(Texy\HandlerInvocation $parser, $refName): Texy\HtmlElement|string|null
{
	$names = ['Me', 'Punkrats', 'Servats', 'Bonifats'];

	if (!isset($names[$refName])) {
		return null; // it's not my job
	}

	$name = $names[$refName];

	$el = new Texy\HtmlElement('a');
	$el->attrs['href'] = '#comm-' . $refName; // set link destination
	$el->attrs['class'][] = 'comment';        // set class name
	$el->attrs['rel'] = 'nofollow';           // enable rel="nofollow"
	$el->setText("[$refName] $name:"); // set link label (with Texy formatting)
	return $el;
}


$texy = new Texy;

// references link [1] [2] will be processed through user function
$texy->addHandler('newReference', 'newReferenceHandler');

// configuration
Texy\Configurator::safeMode($texy);     // safe mode prevets attacker to inject some HTML code and disable images

// how generally disable links or enable images? here is a way:
//    $disallow = array('image', 'figure', 'linkReference', 'linkEmail', 'linkURL', 'linkQuick');
//    foreach ($diallow as $item)
//        $texy->allowed[$item] = false;


// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!


// echo formated output
header('Content-type: text/html; charset=utf-8');
echo $html;


// do some antispam filtering - this is just very simple example ;-)
$spam = false;
foreach ($texy->summary['links'] as $link) {
	if (strpos($link, 'casino')) {
		$spam = true;
		break;
	}
}


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
