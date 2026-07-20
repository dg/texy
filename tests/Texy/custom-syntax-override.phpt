<?php declare(strict_types=1);

/**
 * Test: a syntax registered by the consumer is never overwritten by module registration.
 */

use Tester\Assert;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Syntax;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test('overriding a built-in line syntax survives repeated parsing', function () {
	$texy = new Texy;
	$texy->registerLinePattern(
		fn(ParseContext $context, array $matches) => new Nodes\TextNode('CUSTOM-IMAGE'),
		'~\[\*(.+)\*\]~U',
		Syntax::Image,
	);

	Assert::contains('CUSTOM-IMAGE', $texy->process('see [* logo.gif *] here'));
	Assert::contains('CUSTOM-IMAGE', $texy->process('see [* logo.gif *] here')); // module re-registration must not clobber it
});


test('overriding a built-in block syntax survives repeated parsing', function () {
	$texy = new Texy;
	$texy->registerBlockPattern(
		fn(ParseContext $context, array $matches) => new Nodes\ParagraphNode(
			$context->parseInline('CUSTOM-RULE'),
		),
		'~^-{4,}$~m',
		Syntax::HorizontalRule,
	);

	Assert::contains('CUSTOM-RULE', $texy->process('----'));
	Assert::contains('CUSTOM-RULE', $texy->process('----'));
});


test('the last consumer registration of a name wins', function () {
	$texy = new Texy;
	$texy->registerLinePattern(
		fn(ParseContext $context, array $matches) => new Nodes\TextNode('FIRST'),
		'~@@(.+)@@~U',
		'custom/foo',
	);
	$texy->registerLinePattern(
		fn(ParseContext $context, array $matches) => new Nodes\TextNode('SECOND'),
		'~@@(.+)@@~U',
		'custom/foo',
	);

	Assert::contains('SECOND', $texy->process('@@x@@'));
});


test('allowed[] still disables a consumer-registered syntax', function () {
	$texy = new Texy;
	$texy->registerLinePattern(
		fn(ParseContext $context, array $matches) => new Nodes\TextNode('ACTIVE'),
		'~@@(.+)@@~U',
		'custom/foo',
	);
	$texy->allowed['custom/foo'] = false;

	Assert::contains('@@x@@', $texy->process('@@x@@'));
});


test('a custom syntax registered before the first parse precedes built-ins on a tie', function () {
	$texy = new Texy;
	$texy->registerLinePattern(
		fn(ParseContext $context, array $matches) => new Nodes\TextNode('WON'),
		'~\*\*(.+)\*\*~U',
		'custom/strong',
	);

	Assert::contains('WON', $texy->process('**text**'));
	Assert::contains('WON', $texy->process('**text**'));
});
