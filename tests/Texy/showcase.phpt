<?php declare(strict_types=1);

/**
 * Test: Complete syntax.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('complete syntax showcase', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->imageBaseUrl = '../images/';
	$texy->htmlOutput->floatClasses['left'] = 'left';
	$texy->allowed[Texy\Syntax::Inserted] = true;
	$texy->allowed[Texy\Syntax::Deleted] = true;
	$texy->allowed[Texy\Syntax::Superscript] = true;
	$texy->allowed[Texy\Syntax::Subscript] = true;
	$texy->config->locale = 'en';
	$texy->htmlOutput->horizontalRuleClasses['*'] = 'hidden';

	Assert::matchFile(
		__DIR__ . '/expected/showcase.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/showcase.texy')),
	);
});
