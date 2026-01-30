<?php

/**
 * Test: Complete syntax.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('complete syntax showcase', function () {
	$texy = new Texy\Texy;
	$texy->tabWidth = 0;
	$texy->htmlGenerator->imageRoot = '../images/';
	$texy->htmlGenerator->imageLeftClass = 'left';
	$texy->allowed[Texy\Syntax::Inserted] = true;
	$texy->allowed[Texy\Syntax::Deleted] = true;
	$texy->allowed[Texy\Syntax::Superscript] = true;
	$texy->allowed[Texy\Syntax::Subscript] = true;
	$texy->typographyModule->locale = 'en';
	$texy->htmlGenerator->horizontalRuleClasses['*'] = 'hidden';

	Assert::matchFile(
		__DIR__ . '/expected/showcase.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/showcase.texy')),
	);

	/*Assert::matchFile(
		__DIR__ . '/expected/showcase.txt',
		$texy->toText(),
	);*/
});
