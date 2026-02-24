<?php declare(strict_types=1);

/**
 * Test: a syntax registered by the consumer is never overwritten by module registration.
 */

use Tester\Assert;
use Texy\{Nodes, Syntax, Texy};
use Texy\Parsing\ParseContext;

require __DIR__ . '/../bootstrap.php';


test('overriding a built-in line syntax survives repeated parsing', function () {
	$texy = new Texy;
	$texy->addInlineSyntax(
		Syntax::Image,
		'~\[\*(.+)\*\]~U',
		fn(ParseContext $context, array $matches, array $offsets) => new Nodes\TextNode('CUSTOM-IMAGE'),
	);

	Assert::contains('CUSTOM-IMAGE', $texy->toHtml('see [* logo.gif *] here'));
	Assert::contains('CUSTOM-IMAGE', $texy->toHtml('see [* logo.gif *] here')); // module re-registration must not clobber it
});


test('overriding a built-in block syntax survives repeated parsing', function () {
	$texy = new Texy;
	$texy->addBlockSyntax(
		Syntax::HorizontalRule,
		'~^-{4,}$~m',
		fn(ParseContext $context, array $matches, array $offsets) => new Nodes\ParagraphNode(
			$context->parseInline('CUSTOM-RULE', $offsets[0]),
		),
	);

	Assert::contains('CUSTOM-RULE', $texy->toHtml('----'));
	Assert::contains('CUSTOM-RULE', $texy->toHtml('----'));
});


test('the last consumer registration of a name wins', function () {
	$texy = new Texy;
	$texy->addInlineSyntax(
		'custom/foo',
		'~@@(.+)@@~U',
		fn(ParseContext $context, array $matches, array $offsets) => new Nodes\TextNode('FIRST'),
	);
	$texy->addInlineSyntax(
		'custom/foo',
		'~@@(.+)@@~U',
		fn(ParseContext $context, array $matches, array $offsets) => new Nodes\TextNode('SECOND'),
	);

	Assert::contains('SECOND', $texy->toHtml('@@x@@'));
});


test('allowed[] still disables a consumer-registered syntax', function () {
	$texy = new Texy;
	$texy->addInlineSyntax(
		'custom/foo',
		'~@@(.+)@@~U',
		fn(ParseContext $context, array $matches, array $offsets) => new Nodes\TextNode('ACTIVE'),
	);
	$texy->allowedSyntax['custom/foo'] = false;

	Assert::contains('@@x@@', $texy->toHtml('@@x@@'));
});


test('a custom syntax registered after the first parse precedes built-ins on a tie as well', function () {
	$texy = new Texy;
	$texy->toHtml('**text**');
	$texy->addInlineSyntax(
		'custom/strong',
		'~\*\*(.+)\*\*~U',
		fn(ParseContext $context, array $matches, array $offsets) => new Nodes\TextNode('WON'),
	);

	Assert::contains('WON', $texy->toHtml('**text**'));
});


test('a custom syntax registered before the first parse precedes built-ins on a tie', function () {
	$texy = new Texy;
	$texy->addInlineSyntax(
		'custom/strong',
		'~\*\*(.+)\*\*~U',
		fn(ParseContext $context, array $matches, array $offsets) => new Nodes\TextNode('WON'),
	);

	Assert::contains('WON', $texy->toHtml('**text**'));
	Assert::contains('WON', $texy->toHtml('**text**'));
});
