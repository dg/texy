<?php

/**
 * Test: Deprecation warnings.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('emoticon with file triggers deprecation', function () {
	$texy = new Texy\Texy;
	$texy->allowed[Texy\Syntax::Emoticon] = true;
	$texy->emoticonModule->icons = [':-)' => 'smile.gif'];

	Assert::error(function () use ($texy) {
		$texy->process(':-)');
	}, E_USER_DEPRECATED, 'EmoticonModule: using image files is deprecated, use Unicode characters instead.');
});


test('deprecated || syntax in image triggers warning', function () {
	$texy = new Texy\Texy;

	Assert::error(function () use ($texy) {
		$texy->process('[* small.jpg || big.jpg *]');
	}, E_USER_DEPRECATED, "Image syntax with '|' or '||' inside brackets is deprecated%a%");
});


test('deprecated | syntax in image triggers warning', function () {
	$texy = new Texy\Texy;

	Assert::error(function () use ($texy) {
		$texy->process('[* small.jpg | big.jpg *]');
	}, E_USER_DEPRECATED, "Image syntax with '|' or '||' inside brackets is deprecated%a%");
});
