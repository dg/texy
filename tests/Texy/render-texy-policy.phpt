<?php declare(strict_types=1);

/**
 * Test: the printer of Texy keeps addresses unless it is given a policy.
 */

use Tester\Assert;
use Texy\{Texy, UrlPolicy};

require __DIR__ . '/../bootstrap.php';


function createPolicy(): UrlPolicy
{
	$policy = new UrlPolicy;
	$policy->linkPattern = '~https?:~A';
	$policy->imagePattern = '~https?:~A';
	return $policy;
}


test('without a policy the source stays as it is written', function () {
	$texy = new Texy;
	Assert::same("\"a\":javascript:x\n", $texy->renderTexy($texy->parse('"a":javascript:x')));
});


test('a policy filters links and images', function () {
	$texy = new Texy;
	Assert::same("a\n", $texy->renderTexy($texy->parse('"a":javascript:x'), createPolicy()));
	Assert::same("\"a\":https://x.cz\n", $texy->renderTexy($texy->parse('"a":https://x.cz'), createPolicy()));
	Assert::same("a alt b\n", $texy->renderTexy($texy->parse('a [* javascript:x .(alt) *] b'), createPolicy()));
});
