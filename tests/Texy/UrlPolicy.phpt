<?php declare(strict_types=1);

/**
 * Test: UrlPolicy - URL scheme security policy.
 */

use Tester\Assert;
use Texy\UrlPolicy;

require __DIR__ . '/../bootstrap.php';


test('null pattern allows everything', function () {
	$policy = new UrlPolicy;
	Assert::true($policy->isLinkAllowed('javascript:alert(1)'));
	Assert::true($policy->isImageAllowed('data:image/png;base64,x'));
});


test('scheme-less URLs always pass', function () {
	$policy = new UrlPolicy;
	$policy->linkPattern = '~https?:~A';
	foreach (['relative/path.html', '/absolute/path', '#fragment', '?query', 'www.example.com'] as $url) {
		Assert::true($policy->isLinkAllowed($url), $url);
	}
});


test('scheme is matched against the pattern', function () {
	$policy = new UrlPolicy;
	$policy->linkPattern = '~https?:|ftp:|mailto:~A';
	Assert::true($policy->isLinkAllowed('https://example.com'));
	Assert::true($policy->isLinkAllowed('mailto:a@b.cz'));
	Assert::false($policy->isLinkAllowed('javascript:alert(1)'));
	Assert::false($policy->isLinkAllowed('vbscript:x'));
});


test('scheme detection is case-insensitive and skips leading whitespace', function () {
	$policy = new UrlPolicy;
	$policy->linkPattern = '~https?:~A';
	Assert::false($policy->isLinkAllowed('JavaScript:alert(1)'));
	Assert::false($policy->isLinkAllowed(' javascript:alert(1)'));
});


test('link and image patterns are independent', function () {
	$policy = new UrlPolicy;
	$policy->linkPattern = '~https?:|mailto:~A';
	$policy->imagePattern = '~https?:~A';
	Assert::true($policy->isLinkAllowed('mailto:a@b.cz'));
	Assert::false($policy->isImageAllowed('mailto:a@b.cz'));
});
