<?php

/**
 * This demo shows how change default figures behaviour
 */


// include Texy!
require_once __DIR__ . '/../../src/texy.php';


/**
 * @return Texy\HtmlElement|string|false
 */
function figureHandler(Texy\HandlerInvocation $invocation, Texy\Image $image, Texy\Link $link = null, $content, Texy\Modifier $modifier)
{
	// finish invocation by default way
	$el = $invocation->proceed();

	// change div -> dl
	$el->setName('dl');

	// change p -> dd
	$el[1]->setName('dd');

	// wrap img into dt
	$img = $el[0];
	unset($el[0]);

	$dt = new Texy\HtmlElement('dt');
	$dt->add($img);
	$el->insert(0, $dt);

	return $el;
}


$texy = new Texy();
$texy->addHandler('figure', 'figureHandler');

// optionally set CSS classes
/*
$texy->figureModule->class = 'figure';
$texy->figureModule->leftClass = 'figure-left';
$texy->figureModule->rightClass = 'figure-right';
*/

// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!


// echo formated output
header('Content-type: text/html; charset=utf-8');

echo $html;


// echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
