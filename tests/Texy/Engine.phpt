<?php declare(strict_types=1);

/**
 * Test: contract of Parsing\Engine, the part both languages stand on.
 */

use Tester\Assert;
use Texy\Nodes\{ContentNode, DocumentNode, ParagraphNode, TextNode};
use Texy\Parsing\{Engine, ParseContext};
use Texy\Stage;

require __DIR__ . '/../bootstrap.php';


function createEngine(): Engine
{
	$engine = new Engine;
	$engine->setGapHandler(fn(ParseContext $context, string $text) => [new ParagraphNode(new ContentNode([new TextNode($text)]))]);
	return $engine;
}


function inline(string $label): Closure
{
	return fn(ParseContext $context, array $matches) => new TextNode($label);
}


function block(string $label): Closure
{
	return fn(ParseContext $context, array $matches) => new ParagraphNode(new ContentNode([new TextNode($label)]));
}


function readInline(DocumentNode $document): string
{
	return $document->content->getPlainText();
}


function readBlocks(DocumentNode $document): array
{
	return array_map(fn($node) => $node->content->getPlainText(), $document->content->children);
}


test('only the syntaxes switched on in the map are used', function () {
	$engine = createEngine();
	$engine->addInline('a', '~a~', inline('A'), application: true);
	$engine->addInline('b', '~b~', inline('B'), application: true);

	Assert::same('Ab', readInline($engine->parse('ab', ['a' => true, 'b' => false], singleLine: true)));
	Assert::same('ab', readInline($engine->parse('ab', [], singleLine: true)));
});


test('re-registration replaces the handler and keeps the position', function () {
	$engine = createEngine();
	$engine->addInline('first', '~x~', inline('FIRST'), application: true);
	$engine->addInline('second', '~x~', inline('SECOND'), application: true);
	$engine->addInline('first', '~x~', inline('REPLACED'), application: true);

	Assert::same('REPLACED', readInline($engine->parse('x', ['first' => true, 'second' => true], singleLine: true)));
});


test('inline: the leftmost match wins', function () {
	$engine = createEngine();
	$engine->addInline('late', '~bc~', inline('LATE'), application: true);
	$engine->addInline('early', '~ab~', inline('EARLY'), application: true);

	Assert::same('EARLYc', readInline($engine->parse('abc', ['late' => true, 'early' => true], singleLine: true)));
});


test('inline: at the same offset the longer match wins', function () {
	$engine = createEngine();
	$engine->addInline('short', '~a~', inline('SHORT'), application: true);
	$engine->addInline('long', '~ab~', inline('LONG'), application: true);

	Assert::same('LONG', readInline($engine->parse('ab', ['short' => true, 'long' => true], singleLine: true)));
});


test('inline: at the same offset and length the earlier registration wins', function () {
	$engine = createEngine();
	$engine->addInline('first', '~a~', inline('FIRST'), application: true);
	$engine->addInline('second', '~a~', inline('SECOND'), application: true);

	Assert::same('FIRST', readInline($engine->parse('a', ['first' => true, 'second' => true], singleLine: true)));
});


test('inline: a refused match leaves the position to the other syntaxes', function () {
	$engine = createEngine();
	$engine->addInline('refusing', '~ab~', fn() => null, application: true);
	$engine->addInline('other', '~a~', inline('OTHER'), application: true);

	Assert::same('OTHERb', readInline($engine->parse('ab', ['refusing' => true, 'other' => true], singleLine: true)));
});


test('block: the earliest offset wins, then the earlier registration, whatever the length', function () {
	$engine = createEngine();
	$engine->addBlock('short', '~^a$~', block('SHORT'), application: true);
	$engine->addBlock('long', '~^a\nb$~', block('LONG'), application: true);

	Assert::same(['SHORT', 'b'], readBlocks($engine->parse("a\nb", ['short' => true, 'long' => true])));
});


test('block: a refused match keeps the matches of other patterns inside its range', function () {
	$engine = createEngine();
	$engine->addBlock('outer', '~^a\nb$~', fn() => null, application: true);
	$engine->addBlock('inner', '~^b$~', block('INNER'), application: true);

	Assert::same(["a\n", 'INNER'], readBlocks($engine->parse("a\nb", ['outer' => true, 'inner' => true])));
});


test('block: a refused match does not search its own pattern again inside its range', function () {
	$engine = createEngine();
	$engine->addBlock(
		'same',
		'~^a\nb$|^b$~',
		fn(ParseContext $context, array $matches) => $matches[0] === 'b'
			? new ParagraphNode(new ContentNode([new TextNode('INNER')]))
			: null,
		application: true,
	);

	Assert::same(["a\nb"], readBlocks($engine->parse("a\nb", ['same' => true])));
});


test('parsing blocks without a gap handler throws, even without any block syntax', function () {
	Assert::exception(
		fn() => (new Engine)->parse('text', []),
		LogicException::class,
		'What becomes of the text between block syntaxes is up to the language, call setGapHandler().',
	);
});


test('parsing inline content needs no gap handler', function () {
	Assert::same('text', readInline((new Engine)->parse('text', [], singleLine: true)));
});


test('an inline deferral takes the inline content at every level of nesting', function () {
	$engine = createEngine();
	$engine->addInline('x', '~x~', inline('PARSED'), application: true);
	$engine->addBlock(
		'outer',
		'~^outer$~',
		fn(ParseContext $context) => $context->parseBlock('inner'),
		application: true,
	);
	$engine->addBlock(
		'inner',
		'~^inner$~',
		fn(ParseContext $context) => new ParagraphNode($context->parseInline('x', 5)),
		application: true,
	);

	$document = $engine->parse(
		'outer',
		['x' => true, 'outer' => true, 'inner' => true],
		inlineDeferral: fn(string $text, int $offset) => new ContentNode([new TextNode("deferred $text at $offset")]),
	);

	Assert::same(['deferred x at 5'], readBlocks($document));
});


test('a syntax of the application precedes the language where the order of registration decides', function () {
	$engine = createEngine();
	$engine->addInline('language', '~x~', inline('LANGUAGE'));
	$engine->addInline('application', '~x~', inline('APPLICATION'), application: true);
	Assert::same('APPLICATION', readInline($engine->parse('x', ['language' => true, 'application' => true], singleLine: true)));

	$engine->addBlock('language/block', '~^x$~', block('LANGUAGE'));
	$engine->addBlock('application/block', '~^x$~', block('APPLICATION'), application: true);
	Assert::same(['APPLICATION'], readBlocks($engine->parse('x', ['language/block' => true, 'application/block' => true])));
});


test('a shorter inline syntax of the application does not beat a longer one of the language', function () {
	$engine = createEngine();
	$engine->addInline('language', '~ab~', inline('LANGUAGE'));
	$engine->addInline('application', '~a~', inline('APPLICATION'), application: true);
	Assert::same('LANGUAGE', readInline($engine->parse('ab', ['language' => true, 'application' => true], singleLine: true)));
});


test('a syntax of the application replaces the one of the language of that name', function () {
	$engine = createEngine();
	$engine->addInline('x', '~x~', inline('LANGUAGE'));
	$engine->addInline('x', '~x~', inline('APPLICATION'), application: true);
	$engine->addInline('x', '~x~', inline('LANGUAGE AGAIN'));
	Assert::same('APPLICATION', readInline($engine->parse('x', ['x' => true], singleLine: true)));
});


test('one name cannot be both inline and block', function () {
	$engine = createEngine();
	$engine->addInline('x', '~x~', inline('X'));
	Assert::exception(
		fn() => $engine->addBlock('x', '~^x$~', block('X')),
		LogicException::class,
		"Syntax 'x' cannot be both inline and block.",
	);
	Assert::exception(
		fn() => $engine->addBlock('x', '~^x$~', block('X'), application: true),
		LogicException::class,
		"Syntax 'x' cannot be both inline and block.",
	);
});


test('the map of enabled syntaxes is completed by the state declared first', function () {
	$engine = createEngine();
	$engine->declare('off', false);
	$engine->declare('off');
	$engine->addInline('off', '~x~', inline('X'));
	$engine->addInline('on', '~y~', inline('Y'));
	$engine->declare('patternless');

	Assert::same(['off' => false, 'on' => true, 'patternless' => true], $engine->completeAllowed([]));
	Assert::same(['on' => false, 'off' => false, 'patternless' => true], $engine->completeAllowed(['on' => false]));
});


test('an unknown name in the map of enabled syntaxes is refused with the closest known one', function () {
	$engine = createEngine();
	$engine->declare('image');

	Assert::exception(
		fn() => $engine->completeAllowed(['imgae' => false]),
		Texy\InvalidArgumentException::class,
		"Unknown syntax 'imgae', did you mean 'image'?",
	);
	Assert::exception(
		fn() => $engine->completeAllowed(['quite different' => false]),
		Texy\InvalidArgumentException::class,
		"Unknown syntax 'quite different'.",
	);
});


test('a parse context knows the enabled syntaxes, at every level of nesting', function () {
	$engine = createEngine();
	$seen = [];
	$engine->addBlock('outer', '~^outer$~', fn(ParseContext $context) => $context->parseBlock('inner'));
	$engine->addBlock('inner', '~^inner$~', function (ParseContext $context) use (&$seen) {
		$seen = $context->allowedSyntaxes;
		return new ContentNode;
	});

	$engine->parse('outer', ['outer' => true, 'inner' => true, 'other' => false]);
	Assert::same(['outer' => true, 'inner' => true, 'other' => false], $seen);
});


test('passes run in their stage, in registration order', function () {
	$engine = createEngine();
	$log = [];
	$engine->addPass(function () use (&$log) { $log[] = 'finalize'; }, Stage::Finalize);
	$engine->addPass(function () use (&$log) { $log[] = 'transform 1'; });
	$engine->addPass(function () use (&$log) { $log[] = 'syntax'; }, Stage::Syntax);
	$engine->addPass(function () use (&$log) { $log[] = 'transform 2'; });

	$document = $engine->parse('', []);
	Assert::same(['syntax'], $log);

	$engine->transform($document);
	Assert::same(['syntax', 'transform 1', 'transform 2', 'finalize'], $log);
});


test('blocks nest to ParseContext::MaxDepth, what lies deeper is the text between them', function () {
	$engine = createEngine();
	$engine->addBlock('quote', '~^>(.*)$~', fn(ParseContext $context, array $matches) => new Texy\Nodes\BlockQuoteNode(
		$context->parseBlock($matches[1]),
	));

	$document = $engine->parse(str_repeat('>', ParseContext::MaxDepth + 50) . 'a', ['quote' => true]);
	$quotes = $document->find(Texy\Nodes\BlockQuoteNode::class);

	Assert::count(ParseContext::MaxDepth, $quotes);
	Assert::same(str_repeat('>', 50) . 'a', end($quotes)->getPlainText());
});
