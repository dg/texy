<?php

/**
 * Test: processTypo()
 */

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;

Assert::match(
	'„Hello“ © …',
	$texy->processTypo('"Hello" (c) ...')
);
