<?php declare(strict_types=1);

/**
 * CHANGING HOW FIGURES (IMAGES WITH CAPTIONS) LOOK
 *
 * By default, Texy renders figures as:
 *   <div class="figure"><img ...><p>caption</p></div>
 *
 * This example shows how to change that to a definition list:
 *   <dl><dt><img ...></dt><dd>caption</dd></dl>
 *
 * WHAT YOU'LL LEARN:
 * - How to register a custom handler for figures
 * - How to modify the HTML output that Texy generates
 * - How to work with HtmlElement (change tag names, rearrange children)
 *
 * TEXY SYNTAX FOR FIGURES:
 * [* image.gif *] *** This is the caption
 */


if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


/**
 * Custom handler that transforms figure output from <div> to <dl>
 *
 * Handler workflow:
 * 1. Call proceed() to get the default HTML output
 * 2. Modify the output as needed
 * 3. Return the modified element
 */
function figureHandler(Texy\HandlerInvocation $invocation, Texy\Image $image, ?Texy\Link $link, $content, Texy\Modifier $modifier): Texy\HtmlElement|string|null
{
	// First, let Texy create the default output
	$el = $invocation->proceed();

	// Now modify it:
	// Change the wrapper from <div> to <dl>
	$el->setName('dl');

	// Change the caption from <p> to <dd>
	// (the caption is the second child, index 1)
	$el[1]->setName('dd');

	// Wrap the image in a <dt> element
	// (the image is the first child, index 0)
	$img = $el[0];
	unset($el[0]);

	$dt = new Texy\HtmlElement('dt');
	$dt->add($img);
	$el->insert(0, $dt);

	return $el;
}


$texy = new Texy;

// Register our custom handler for figures
$texy->addHandler('figure', figureHandler(...));

// You can also set CSS classes for figures (optional)
// $texy->figureModule->class = 'figure';
// $texy->figureModule->leftClass = 'figure-left';
// $texy->figureModule->rightClass = 'figure-right';


// Process the text
$text = file_get_contents(__DIR__ . '/sample.texy');
$html = $texy->process($text);


// Display the result
echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';
echo $html;


// Show the generated HTML source code
echo '<hr>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
