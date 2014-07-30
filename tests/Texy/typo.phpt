<?php

/**
 * Test: processTypo()
 */

use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;

Assert::match(
	'„Hello“ © …',
	$texy->processTypo('"Hello" (c) ...')
);
