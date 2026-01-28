<?php declare(strict_types=1);

/**
 * Test: Deprecation warnings.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('emoticon with file triggers deprecation', function () {
	$texy = new Texy\Texy;
	$texy->allowed['emoticon'] = true;
	$texy->emoticonModule->icons = [':-)' => 'smile.gif'];

	Assert::error(function () use ($texy) {
		$texy->process(':-)');
	}, E_USER_DEPRECATED, 'EmoticonModule: using image files is deprecated, use Unicode characters instead.');
});
