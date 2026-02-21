<?php declare(strict_types=1);

/**
 * Test: processTypo()
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;

Assert::match(
	'„Hello“ © …',
	$texy->processTypo('"Hello" (c) ...'),
);
