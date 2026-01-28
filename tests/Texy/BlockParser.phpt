<?php

/**
 * Test: BlockParser - isolated tests for AST block parser.
 *
 * Note: Texy\Regexp adds 'ux' flags to all patterns.
 * In extended mode (x): # starts a comment, spaces are ignored.
 * Use \# for literal #, [ ] or \x20 for literal space.
 */

declare(strict_types=1);

use Tester\Assert;
use Texy\BlockParser;
use Texy\InlineParser;
use Texy\Nodes\BlockNode;
use Texy\ParseContext;

require __DIR__ . '/../bootstrap.php';


/**
 * Create a stub InlineParser for isolated BlockParser tests.
 */
function createInlineParser(): InlineParser
{
	return new InlineParser([]);
}


/**
 * Simple block node for testing.
 */
class TestBlockNode extends BlockNode
{
	public function __construct(
		public string $type,
		public string $content = '',
	) {
	}
}


/**
 * Gap node for capturing text between matches.
 */
class GapNode extends BlockNode
{
	public function __construct(
		public string $content,
	) {
	}
}


/**
 * Helper to create a simple block handler.
 */
function createBlockHandler(string $type): Closure
{
	return fn(ParseContext $context, array $matches, string $name): TestBlockNode => new TestBlockNode($type, $matches[0]);
}


/**
 * Helper to create a gap handler that wraps text in GapNode.
 */
function createGapHandler(): Closure
{
	return fn(ParseContext $context, string $text) => $text !== '' ? [new GapNode($text)] : [];
}


/**
 * Helper to create a null handler.
 */
function createNullHandler(): Closure
{
	return fn() => null;
}


// =============================================================================
// A. Basic functionality
// =============================================================================


/**
 * Helper to create BlockParser with context and parse text.
 */
function parseWithBlockParser(array $patterns, string $text): array
{
	$inlineParser = createInlineParser();
	$blockParser = new BlockParser($patterns, createGapHandler());
	$context = new ParseContext($inlineParser, $blockParser);
	return $blockParser->parse($context, $text)->children;
}


test('simple pattern match', function () {
	$nodes = parseWithBlockParser([
		'heading' => [
			// \# escapes # in extended mode, [ ] for literal space
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => createBlockHandler('heading'),
		],
	], "# Title\n");

	Assert::count(1, $nodes);
	Assert::type(TestBlockNode::class, $nodes[0]);
	Assert::same('heading', $nodes[0]->type);
});


test('multiple different patterns', function () {
	$nodes = parseWithBlockParser([
		'heading' => [
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => createBlockHandler('heading'),
		],
		'rule' => [
			'pattern' => '~^---~m',
			'handler' => createBlockHandler('rule'),
		],
	], "# Title\n---\n");

	Assert::count(2, $nodes);
	Assert::same('heading', $nodes[0]->type);
	Assert::same('rule', $nodes[1]->type);
});


test('multiple matches of same pattern', function () {
	$nodes = parseWithBlockParser([
		'heading' => [
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => createBlockHandler('heading'),
		],
	], "# One\n# Two\n# Three\n");

	Assert::count(3, $nodes);
	Assert::same('heading', $nodes[0]->type);
	Assert::same('heading', $nodes[1]->type);
	Assert::same('heading', $nodes[2]->type);
});


// =============================================================================
// B. Gap handler (text between matches)
// =============================================================================

test('text before match goes to gapHandler', function () {
	$nodes = parseWithBlockParser([
		'heading' => [
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => createBlockHandler('heading'),
		],
	], "paragraph\n# Title\n");

	Assert::count(2, $nodes);
	Assert::type(GapNode::class, $nodes[0]);
	Assert::same("paragraph\n", $nodes[0]->content);
	Assert::type(TestBlockNode::class, $nodes[1]);
});


test('text after match goes to gapHandler', function () {
	$nodes = parseWithBlockParser([
		'heading' => [
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => createBlockHandler('heading'),
		],
	], "# Title\nparagraph\n");

	Assert::count(2, $nodes);
	Assert::type(TestBlockNode::class, $nodes[0]);
	Assert::type(GapNode::class, $nodes[1]);
	Assert::same("paragraph\n", $nodes[1]->content);
});


test('text between matches goes to gapHandler', function () {
	$nodes = parseWithBlockParser([
		'heading' => [
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => createBlockHandler('heading'),
		],
	], "# One\nparagraph\n# Two\n");

	Assert::count(3, $nodes);
	Assert::type(TestBlockNode::class, $nodes[0]);
	Assert::type(GapNode::class, $nodes[1]);
	Assert::same("paragraph\n", $nodes[1]->content);
	Assert::type(TestBlockNode::class, $nodes[2]);
});


test('only text without matches', function () {
	$nodes = parseWithBlockParser([
		'heading' => [
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => createBlockHandler('heading'),
		],
	], "just text\n");

	Assert::count(1, $nodes);
	Assert::type(GapNode::class, $nodes[0]);
	Assert::same("just text\n", $nodes[0]->content);
});


// =============================================================================
// C. Priority (registration order)
// =============================================================================

test('earlier registered pattern wins at same offset', function () {
	$firstCalled = false;
	$secondCalled = false;

	$nodes = parseWithBlockParser([
		'first' => [
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => function (ParseContext $context) use (&$firstCalled) {
				$firstCalled = true;
				return new TestBlockNode('first');
			},
		],
		'second' => [
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => function (ParseContext $context) use (&$secondCalled) {
				$secondCalled = true;
				return new TestBlockNode('second');
			},
		],
	], "# Title\n");

	Assert::true($firstCalled);
	Assert::false($secondCalled);
	Assert::count(1, $nodes);
	Assert::same('first', $nodes[0]->type);
});


// =============================================================================
// D. NULL handler return
// =============================================================================

test('null handler tries next pattern at same position', function () {
	$secondCalled = false;

	$nodes = parseWithBlockParser([
		'rejecting' => [
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => createNullHandler(),
		],
		'accepting' => [
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => function (ParseContext $context) use (&$secondCalled) {
				$secondCalled = true;
				return new TestBlockNode('accepted');
			},
		],
	], "# Title\n");

	Assert::true($secondCalled);
	Assert::count(1, $nodes);
	Assert::same('accepted', $nodes[0]->type);
});


test('all handlers return null - text goes to gapHandler', function () {
	$nodes = parseWithBlockParser([
		'rejecting' => [
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => createNullHandler(),
		],
	], "# Title\n");

	Assert::count(1, $nodes);
	Assert::type(GapNode::class, $nodes[0]);
	Assert::same("# Title\n", $nodes[0]->content);
});


// =============================================================================
// E. Method next() (iterative matching)
// =============================================================================

test('handler uses next() to consume multiple lines', function () {
	$nodes = parseWithBlockParser([
		'list' => [
			'pattern' => '~^-[ ](.+)~m',
			'handler' => function (ParseContext $context, array $matches) {
				$items = [$matches[1]];
				while ($context->getBlockParser()->next('~^-[ ](.+)~', $m)) {
					$items[] = $m[1];
				}
				return new TestBlockNode('list', implode(',', $items));
			},
		],
	], "- one\n- two\n- three\n");

	Assert::count(1, $nodes);
	Assert::same('list', $nodes[0]->type);
	Assert::same('one,two,three', $nodes[0]->content);
});


test('next() returns false at end of text', function () {
	$nextResult = null;

	parseWithBlockParser([
		'test' => [
			'pattern' => '~^test~m',
			'handler' => function (ParseContext $context) use (&$nextResult) {
				$nextResult = $context->getBlockParser()->next('~^more~', $m);
				return new TestBlockNode('test');
			},
		],
	], "test\n");

	Assert::false($nextResult);
});


test('next() advances position correctly', function () {
	$nodes = parseWithBlockParser([
		'block' => [
			'pattern' => '~^start~m',
			'handler' => function (ParseContext $context) {
				// Consume "middle" line
				$context->getBlockParser()->next('~^middle~', $m);
				// Don't consume "after" - it should go to gapHandler
				return new TestBlockNode('block');
			},
		],
	], "start\nmiddle\nafter\n");

	Assert::count(2, $nodes);
	Assert::same('block', $nodes[0]->type);
	Assert::type(GapNode::class, $nodes[1]);
	Assert::same("after\n", $nodes[1]->content);
});


// =============================================================================
// F. Method moveBackward()
// =============================================================================

test('moveBackward allows next pattern to match', function () {
	$nodes = parseWithBlockParser([
		'quote' => [
			'pattern' => '~^>[ ](.+)~m',
			'handler' => function (ParseContext $context, array $matches) {
				$lines = [$matches[1]];
				while ($context->getBlockParser()->next('~^(.+)~', $m)) {
					if (!str_starts_with($m[0], '> ')) {
						$context->getBlockParser()->moveBackward();
						break;
					}
					$lines[] = substr($m[0], 2);
				}
				return new TestBlockNode('quote', implode("\n", $lines));
			},
		],
		'other' => [
			'pattern' => '~^other~m',
			'handler' => createBlockHandler('other'),
		],
	], "> quote1\n> quote2\nother\n");

	Assert::count(2, $nodes);
	Assert::same('quote', $nodes[0]->type);
	Assert::same("quote1\nquote2", $nodes[0]->content);
	Assert::same('other', $nodes[1]->type);
});


// =============================================================================
// G. Edge cases
// =============================================================================

test('empty text returns empty array', function () {
	$nodes = parseWithBlockParser([
		'heading' => [
			'pattern' => '~^\#[ ](.+)~m',
			'handler' => createBlockHandler('heading'),
		],
	], '');

	Assert::same([], $nodes);
});


test('no patterns registered', function () {
	$nodes = parseWithBlockParser([], "text\n");

	Assert::count(1, $nodes);
	Assert::type(GapNode::class, $nodes[0]);
	Assert::same("text\n", $nodes[0]->content);
});


test('handler receives correct pattern name', function () {
	$receivedName = null;

	parseWithBlockParser([
		'my/custom-pattern' => [
			'pattern' => '~^test~m',
			'handler' => function (ParseContext $context, $matches, $name) use (&$receivedName) {
				$receivedName = $name;
				return new TestBlockNode('test');
			},
		],
	], "test\n");

	Assert::same('my/custom-pattern', $receivedName);
});
