<?php declare(strict_types=1);

/**
 * Test: the link resolver of the application
 */

use Tester\Assert;
use Texy\Nodes\LinkNode;

require __DIR__ . '/../bootstrap.php';


test('the resolver gives the URL, a %s in it is the link text', function () {
	$texy = new Texy\Texy;
	$texy->config->linkResolver = fn(string $name) => match ($name) {
		'php' => 'https://php.net/%s',
		'texy' => 'https://texy.deegee.dev/',
		default => null,
	};
	Assert::same('<a href="https://php.net/strlen">strlen</a>', $texy->toInlineHtml('"strlen":[php]'));
	Assert::same('<a href="https://texy.deegee.dev/">Visit</a>', $texy->toInlineHtml('"Visit":[texy]'));
});


test('the resolver lasts across documents', function () {
	$texy = new Texy\Texy;
	$texy->config->linkResolver = fn() => 'https://texy.deegee.dev/';
	Assert::same('<a href="https://texy.deegee.dev/">Texy</a>', $texy->toInlineHtml('"Texy":[texy]'));
	Assert::same('<a href="https://texy.deegee.dev/">Texy</a>', $texy->toInlineHtml('"Texy":[texy]'));
});


test('null leaves the name as the address', function () {
	$texy = new Texy\Texy;
	$texy->config->linkResolver = fn() => null;
	Assert::same('<a href="page">Click</a>', $texy->toInlineHtml('"Click":[page]'));
});


test('a definition of the document wins, also by the base of the name', function () {
	$texy = new Texy\Texy;
	$names = [];
	$texy->config->linkResolver = function (string $name) use (&$names) {
		$names[] = $name;
		return 'https://resolver.cz/';
	};
	$texy->htmlOutput->lineWrap = 0;
	Assert::same(
		"<p><a href=\"https://document.cz\">a</a> <a href=\"https://document.cz#part\">b</a></p>\n",
		$texy->toHtml("\"a\":[link] \"b\":[LINK#part]\n\n[link]: https://document.cz"),
	);
	Assert::same([], $names);
});


test('the resolver gets the name as written, once per link', function () {
	$texy = new Texy\Texy;
	$names = [];
	$texy->config->linkResolver = function (string $name) use (&$names) {
		$names[] = $name;
		return null;
	};
	$texy->toHtml('"a":[Page#Section] "b":[api:Texy?q=1] "c":[page] "d":[page]');
	Assert::same(['Page#Section', 'api:Texy?q=1', 'page', 'page'], $names);
});


test('a bare reference is resolved and is recognised by its flag', function () {
	$texy = new Texy\Texy;
	$texy->allowedSyntax[Texy\Syntax::LinkReference] = true;
	$bare = [];
	$texy->config->linkResolver = function (string $name, LinkNode $link) use (&$bare) {
		$bare[$name] = $link->isBareRef;
		return 'https://wiki.cz/%s';
	};
	Assert::same(
		'<a href="https://wiki.cz/%s">page</a> <a href="https://wiki.cz/Text">Text</a>',
		$texy->toInlineHtml('[page] "Text":[other]'),
	);
	Assert::same(['page' => true, 'other' => false], $bare);
});


test('the resolver may change the content and the modifier of the link', function () {
	$texy = new Texy\Texy;
	$texy->allowedSyntax[Texy\Syntax::LinkReference] = true;
	$texy->config->linkResolver = function (string $name, LinkNode $link) {
		if ($link->isBareRef) {
			$link->content = Texy\Nodes\ContentNode::fromText('Title of ' . $name);
		}
		$link->modifier ??= new Texy\Modifier;
		$link->modifier->attrs['hreflang'] = 'en';
		return 'https://wiki.cz/' . $name;
	};
	Assert::same(
		'<a hreflang="en" href="https://wiki.cz/page">Title of page</a>',
		$texy->toInlineHtml('[page]'),
	);
});


test('the resolver is not asked about images, written URLs and names the document defines', function () {
	$texy = new Texy\Texy;
	$texy->allowedSyntax[Texy\Syntax::LinkReference] = true;
	$names = [];
	$texy->config->linkResolver = function (string $name) use (&$names) {
		$names[] = $name;
		return null;
	};
	$texy->toHtml("\"a\":[*img*] \"b\":https://example.com \"c\":[defined] [defined] [asked]\n\n[defined]: https://example.com");
	Assert::same(['asked'], $names);
});


test('the safe mode filters a URL the resolver returns', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	$texy->config->linkResolver = fn() => 'javascript:alert(1)';
	Assert::same('a', $texy->toInlineHtml('"a":[x]'));
});
