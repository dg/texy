<?php declare(strict_types=1);

/**
 * Test: $allowedSyntax holds syntaxes only, is complete from the start and checked.
 */

use Tester\Assert;
use Texy\{InvalidArgumentException, Syntax, Texy};

require __DIR__ . '/../bootstrap.php';


test('the map is complete after the constructor and reports the defaults', function () {
	$texy = new Texy;
	Assert::true($texy->allowedSyntax[Syntax::Image]);
	Assert::true($texy->allowedSyntax[Syntax::BlockCode]);
	Assert::false($texy->allowedSyntax[Syntax::Emoticon]);
	Assert::false($texy->allowedSyntax[Syntax::Inserted]);
	Assert::false(isset($texy->allowedSyntax['typography']));
});


test('an unknown key fails in every entry point', function () {
	$texy = new Texy;
	$texy->allowedSyntax['imgae'] = false;
	$message = "Unknown syntax 'imgae', did you mean 'image'?";

	Assert::exception(fn() => $texy->parse('a'), InvalidArgumentException::class, $message);
	Assert::exception(fn() => $texy->parseInline('a'), InvalidArgumentException::class, $message);
});


test('a switched off syntax survives repeated registration', function () {
	$texy = new Texy;
	$texy->allowedSyntax[Syntax::Image] = false;
	$texy->parse('a');
	$texy->parse('a');
	Assert::false($texy->allowedSyntax[Syntax::Image]);
});


test('a removed key falls back to the declared state', function () {
	$texy = new Texy;
	$texy->allowedSyntax = [];
	$texy->parse('a');
	Assert::true($texy->allowedSyntax[Syntax::Image]);
	Assert::false($texy->allowedSyntax[Syntax::Emoticon]);
});


test('a name stays declared when its pattern is gone', function () {
	$texy = new Texy;
	$texy->allowedSyntax[Syntax::Emoticon] = true;
	$texy->config->emoticons = [];
	Assert::same("<p>a</p>\n", $texy->process('a'));
	Assert::true($texy->allowedSyntax[Syntax::Emoticon]);
});
