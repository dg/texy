<?php

/**
 * Test: InlineParser - isolated tests for AST inline parser.
 */

declare(strict_types=1);

use Tester\Assert;
use Texy\InlineParser;
use Texy\Nodes\ContentNode;
use Texy\Nodes\PhraseNode;
use Texy\Nodes\TextNode;
use Texy\ParseContext;

require __DIR__ . '/../bootstrap.php';


/**
 * Helper to create a simple phrase handler for testing.
 */
function createPhraseHandler(string $type): Closure
{
	return function (?ParseContext $context, array $matches, string $name) use ($type): PhraseNode {
		$content = $matches[1];
		return new PhraseNode(
			new ContentNode([new TextNode($content)]),
			$type,
		);
	};
}


/**
 * Helper to create a handler that returns null.
 */
function createNullHandler(): Closure
{
	return fn() => null;
}


/**
 * Helper to assert node types and content.
 */
function assertNodes(array $expected, ContentNode $content): void
{
	$actual = $content->children;
	Assert::count(count($expected), $actual, 'Node count mismatch');
	foreach ($expected as $i => [$type, $content]) {
		Assert::type($type, $actual[$i], "Node $i type mismatch");
		if ($type === TextNode::class) {
			Assert::same($content, $actual[$i]->content, "TextNode $i content mismatch");
		} elseif ($type === PhraseNode::class) {
			Assert::same($content, $actual[$i]->type, "PhraseNode $i type mismatch");
		}
	}
}


// =============================================================================
// A. Basic functionality
// =============================================================================

test('simple pattern match', function () {
	$parser = new InlineParser([
		'bold' => [
			'pattern' => '~\*\*(.+?)\*\*~',
			'handler' => createPhraseHandler('phrase/strong'),
		],
	]);

	$nodes = $parser->parse(null, 'Hello **world** today');

	assertNodes([
		[TextNode::class, 'Hello '],
		[PhraseNode::class, 'phrase/strong'],
		[TextNode::class, ' today'],
	], $nodes);
});


test('multiple patterns without overlap', function () {
	$parser = new InlineParser([
		'bold' => [
			'pattern' => '~\*\*(.+?)\*\*~',
			'handler' => createPhraseHandler('phrase/strong'),
		],
		'italic' => [
			'pattern' => '~//(.+?)//~',
			'handler' => createPhraseHandler('phrase/em'),
		],
	]);

	$nodes = $parser->parse(null, '**bold** and //italic//');

	assertNodes([
		[PhraseNode::class, 'phrase/strong'],
		[TextNode::class, ' and '],
		[PhraseNode::class, 'phrase/em'],
	], $nodes);
});


test('multiple matches of same pattern', function () {
	$parser = new InlineParser([
		'bold' => [
			'pattern' => '~\*\*(.+?)\*\*~',
			'handler' => createPhraseHandler('phrase/strong'),
		],
	]);

	$nodes = $parser->parse(null, '**one** **two** **three**');

	assertNodes([
		[PhraseNode::class, 'phrase/strong'],
		[TextNode::class, ' '],
		[PhraseNode::class, 'phrase/strong'],
		[TextNode::class, ' '],
		[PhraseNode::class, 'phrase/strong'],
	], $nodes);
});


test('adjacent matches without space', function () {
	$parser = new InlineParser([
		'bold' => [
			'pattern' => '~\*\*(.+?)\*\*~',
			'handler' => createPhraseHandler('phrase/strong'),
		],
		'italic' => [
			'pattern' => '~//(.+?)//~',
			'handler' => createPhraseHandler('phrase/em'),
		],
	]);

	$nodes = $parser->parse(null, '**bold**//italic//');

	assertNodes([
		[PhraseNode::class, 'phrase/strong'],
		[PhraseNode::class, 'phrase/em'],
	], $nodes);
});


// =============================================================================
// B. Overlapping and priority
// =============================================================================

test('longer match wins at same offset', function () {
	$parser = new InlineParser([
		'short' => [
			'pattern' => '~\*\*(.+?)\*\*~',
			'handler' => createPhraseHandler('phrase/strong'),
		],
		'long' => [
			'pattern' => '~\*\*\*(.+?)\*\*\*~',
			'handler' => createPhraseHandler('phrase/strong+em'),
		],
	]);

	$nodes = $parser->parse(null, '***bold***');

	assertNodes([
		[PhraseNode::class, 'phrase/strong+em'],
	], $nodes);
});


test('same offset and length - first registered wins', function () {
	$parser = new InlineParser([
		'first' => [
			'pattern' => '~\*\*(.+?)\*\*~',
			'handler' => createPhraseHandler('first-handler'),
		],
		'second' => [
			'pattern' => '~\*\*(.+?)\*\*~', // identical pattern
			'handler' => createPhraseHandler('second-handler'),
		],
	]);

	$nodes = $parser->parse(null, '**text**');

	// When offset and length are equal, the first one in $allMatches array wins
	// (which depends on pattern iteration order)
	Assert::count(1, $nodes->children);
	Assert::type(PhraseNode::class, $nodes->children[0]);
});


test('overlapping matches - earlier offset wins', function () {
	$parser = new InlineParser([
		'bold' => [
			'pattern' => '~\*\*(.+?)\*\*~',
			'handler' => createPhraseHandler('phrase/strong'),
		],
	]);

	$nodes = $parser->parse(null, '**bold *overlap** end*');

	Assert::count(2, $nodes->children);
	Assert::type(PhraseNode::class, $nodes->children[0]);
	Assert::type(TextNode::class, $nodes->children[1]);
	Assert::same(' end*', $nodes->children[1]->content);
});


// =============================================================================
// C. NULL handler return
// =============================================================================

test('handler returns null creates TextNode', function () {
	$parser = new InlineParser([
		'image' => [
			'pattern' => '~\[\*(.+?)\*\]~',
			'handler' => createNullHandler(),
		],
	]);

	$nodes = $parser->parse(null, '[*obr*]');

	assertNodes([
		[TextNode::class, '[*obr*]'],
	], $nodes);
});


test('null allows inner patterns to match', function () {
	$parser = new InlineParser([
		'outer' => [
			'pattern' => '~\[(.+?)\]~',
			'handler' => createNullHandler(),
		],
		'italic' => [
			'pattern' => '~\*(.+?)\*~',
			'handler' => createPhraseHandler('phrase/em'),
		],
	]);

	$nodes = $parser->parse(null, '[*text*]');

	// Outer pattern returns null, inner pattern gets a chance
	assertNodes([
		[TextNode::class, '['],
		[PhraseNode::class, 'phrase/em'],
		[TextNode::class, ']'],
	], $nodes);
});


test('null tries alternative pattern at same position', function () {
	// Two patterns match at same offset 0, first returns null
	// Second pattern should be tried
	$secondHandlerCalled = false;

	$parser = new InlineParser([
		'first' => [
			'pattern' => '~\*\*(.+?)\*\*~',
			'handler' => createNullHandler(), // returns null
		],
		'second' => [
			'pattern' => '~\*\*(.+?)\*\*~', // same pattern, same match
			'handler' => function ($context, $matches, $name) use (&$secondHandlerCalled) {
				$secondHandlerCalled = true;
				return new PhraseNode(
					new ContentNode([new TextNode($matches[1])]),
					'second',
				);
			},
		],
	]);

	$nodes = $parser->parse(null, '**text**');

	// Second handler is called because first returned null
	Assert::true($secondHandlerCalled);
	assertNodes([
		[PhraseNode::class, 'second'],
	], $nodes);
});


test('null does not block non-overlapping patterns', function () {
	$parser = new InlineParser([
		'broken' => [
			'pattern' => '~\[x\]~',
			'handler' => createNullHandler(),
		],
		'bold' => [
			'pattern' => '~\*\*(.+?)\*\*~',
			'handler' => createPhraseHandler('phrase/strong'),
		],
	]);

	$nodes = $parser->parse(null, '[x] **works**');

	// [x] returns null, so text before **works** becomes one TextNode
	assertNodes([
		[TextNode::class, '[x] '],
		[PhraseNode::class, 'phrase/strong'],
	], $nodes);
});


// =============================================================================
// D. Handler receives correct pattern name
// =============================================================================

test('handler receives correct pattern name', function () {
	$receivedName = null;

	$parser = new InlineParser([
		'my/custom-pattern' => [
			'pattern' => '~\*\*(.+?)\*\*~',
			'handler' => function ($context, $matches, $name) use (&$receivedName) {
				$receivedName = $name;
				return new TextNode($matches[1]);
			},
		],
	]);

	$parser->parse(null, '**text**');

	Assert::same('my/custom-pattern', $receivedName);
});


// =============================================================================
// E. Edge cases
// =============================================================================

test('empty text returns empty ContentNode', function () {
	$parser = new InlineParser([
		'bold' => [
			'pattern' => '~\*\*(.+?)\*\*~',
			'handler' => createPhraseHandler('phrase/strong'),
		],
	]);

	$nodes = $parser->parse(null, '');

	Assert::type(ContentNode::class, $nodes);
	Assert::same([], $nodes->children);
});


test('no pattern matches returns single TextNode', function () {
	$parser = new InlineParser([
		'bold' => [
			'pattern' => '~\*\*(.+?)\*\*~',
			'handler' => createPhraseHandler('phrase/strong'),
		],
	]);

	$nodes = $parser->parse(null, 'plain text');

	assertNodes([
		[TextNode::class, 'plain text'],
	], $nodes);
});


test('no patterns registered returns single TextNode', function () {
	$parser = new InlineParser([]);

	$nodes = $parser->parse(null, 'some text');

	assertNodes([
		[TextNode::class, 'some text'],
	], $nodes);
});


test('addPattern method works', function () {
	$parser = new InlineParser([]);

	$parser->addPattern('bold', '~\*\*(.+?)\*\*~', createPhraseHandler('phrase/strong'));

	$nodes = $parser->parse(null, '**text**');

	assertNodes([
		[PhraseNode::class, 'phrase/strong'],
	], $nodes);
});


// =============================================================================
// F. UTF-8 handling
// =============================================================================

test('UTF-8 content in match', function () {
	$parser = new InlineParser([
		'bold' => [
			'pattern' => '~\*\*(.+?)\*\*~u',
			'handler' => createPhraseHandler('phrase/strong'),
		],
	]);

	$nodes = $parser->parse(null, 'Hello **日本語** world');

	Assert::count(3, $nodes->children);
	Assert::type(TextNode::class, $nodes->children[0]);
	Assert::same('Hello ', $nodes->children[0]->content);
	Assert::type(PhraseNode::class, $nodes->children[1]);
	Assert::type(TextNode::class, $nodes->children[2]);
	Assert::same(' world', $nodes->children[2]->content);

	// Check that content inside PhraseNode is correct
	Assert::same('日本語', $nodes->children[1]->content->children[0]->content);
});


// =============================================================================
// H. Empty matches
// =============================================================================

test('pattern matching empty string is skipped', function () {
	$handlerCallCount = 0;

	$parser = new InlineParser([
		'empty' => [
			'pattern' => '~(?:)~', // matches empty string at every position
			'handler' => function () use (&$handlerCallCount) {
				$handlerCallCount++;
				return new TextNode('X');
			},
		],
	]);

	$nodes = $parser->parse(null, 'abc');

	// Empty matches should be skipped (line 56-58 in InlineParser)
	Assert::same(0, $handlerCallCount);
	assertNodes([
		[TextNode::class, 'abc'],
	], $nodes);
});
