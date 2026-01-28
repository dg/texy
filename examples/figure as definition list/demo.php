<?php

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
 * - How to register a custom HTML handler for FigureNode
 * - How to build HTML output from node data
 * - How to work with Html\Element
 *
 * TEXY SYNTAX FOR FIGURES:
 * [* image.gif *] *** This is the caption
 */

declare(strict_types=1);

use Texy\Nodes\FigureNode;
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;

// Register our custom handler for figures
// The first parameter type (FigureNode) determines which node class the handler processes
$texy->htmlGenerator->registerHandler(
	function (FigureNode $node, Html\Generator $gen) use ($texy): Html\Element {
		$el = new Html\Element('dl');

		// Image in <dt> - use generator to handle ImageNode (and LinkNode if linked)
		$dt = $el->create('dt');
		$dt->children = $gen->renderNodes([$node->image]);

		// Caption in <dd>
		if ($node->caption) {
			$dd = $el->create('dd');
			$dd->children = $gen->renderNodes($node->caption->children);
		}

		// Apply modifier classes/styles if present
		$node->modifier?->decorate($texy, $el);

		return $el;
	},
);

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
