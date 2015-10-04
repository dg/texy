<?php

/**
 * Test: processTypo()
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;

Assert::match(
	'„Hello“ © …',
	$texy->processTypo('"Hello" (c) ...')
);
