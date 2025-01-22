<?php

declare(strict_types=1);

use Tester\Assert;
use Texy\Nodes\BlockQuoteNode;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;


function handler(BlockQuoteNode $node)
{
	$el = yield;
	$el->attrs['class'] = 'blockquote';
	return $el;
}


$texy->addHandler(BlockQuoteNode::class, 'handler');

echo $texy->process('
> ahih
> volč
> jak se máš
');

Assert::true(true);
