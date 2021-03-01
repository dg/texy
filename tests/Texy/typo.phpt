<?php

/**
 * Test: processTypo()
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;

Assert::match(
	'„Hello“ © …',
	$texy->processTypo('"Hello" (c) ...'),
);
