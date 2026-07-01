<?php declare(strict_types=1);

/**
 * Test: applyTypography()
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;

Assert::match(
	'„Hello“ © …',
	$texy->applyTypography('"Hello" (c) ...'),
);

// disabled typography is honoured
$texy = new Texy\Texy;
$texy->allowed[Texy\Syntax::Typography] = false;
Assert::match(
	'"Hello" (c) ...',
	$texy->applyTypography('"Hello" (c) ...'),
);
