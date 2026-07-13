<?php declare(strict_types=1);

/**
 * Test: what the outputs make of a soft break, of paragraphs in list items and of a task.
 */

use Tester\Assert;
use Texy\{Configurator, Texy};
use Texy\Nodes\{ContentNode, DocumentNode, ItemParagraphs, ListItemNode, ListNode, ParagraphNode, SoftBreakNode, TextNode};
use Texy\Output\Text\Renderer as TextRenderer;

require __DIR__ . '/../bootstrap.php';


function paragraph(string $text): ParagraphNode
{
	return new ParagraphNode(new ContentNode([new TextNode($text)]));
}


function createList(ItemParagraphs $paragraphs, ?bool $checked = null): DocumentNode
{
	$item = new ListItemNode(new ContentNode([paragraph('a'), paragraph('b')]), checked: $checked);
	return new DocumentNode(new ContentNode([new ListNode([$item], paragraphs: $paragraphs)]));
}


function createHtml(DocumentNode $document): string
{
	$texy = new Texy;
	$texy->htmlOutput->indent = false;
	return trim($texy->createHtmlRenderer()->render($document));
}


test('paragraphs of a list item in HTML', function () {
	Assert::same('<ul><li><p>a</p><p>b</p></li></ul>', createHtml(createList(ItemParagraphs::Wrapped)));
	Assert::same('<ul><li>ab</li></ul>', createHtml(createList(ItemParagraphs::Unwrapped)));
	Assert::same('<ul><li>a<p>b</p></li></ul>', createHtml(createList(ItemParagraphs::FirstUnwrapped)));
});


test('the list of Texy keeps the first paragraph unwrapped', function () {
	$document = (new Texy)->parse("- a\n\n  b");
	Assert::same(ItemParagraphs::FirstUnwrapped, $document->content->children[0]->paragraphs);
});


test('a task in HTML', function () {
	Assert::same(
		'<ul><li><input type="checkbox" disabled checked> ab</li></ul>',
		createHtml(createList(ItemParagraphs::Unwrapped, checked: true)),
	);
	Assert::same(
		'<ul><li><input type="checkbox" disabled> ab</li></ul>',
		createHtml(createList(ItemParagraphs::Unwrapped, checked: false)),
	);
	Assert::same(
		'<ul><li><p><input type="checkbox" disabled checked> a</p><p>b</p></li></ul>',
		createHtml(createList(ItemParagraphs::Wrapped, checked: true)),
	);
});


test('a task survives the safe mode, it is no tag of the document', function () {
	$texy = new Texy;
	Configurator::safeMode($texy);
	$texy->htmlOutput->indent = false;
	Assert::same(
		'<ul><li><input type="checkbox" disabled checked> ab</li></ul>',
		trim($texy->createHtmlRenderer()->render(createList(ItemParagraphs::Unwrapped, checked: true))),
	);
});


test('a task in plain text and in Texy', function () {
	Assert::same("[x] a\nb\n", (new TextRenderer)->render(createList(ItemParagraphs::Wrapped, checked: true)));
	Assert::same("[ ] a\nb\n", (new TextRenderer)->render(createList(ItemParagraphs::Wrapped, checked: false)));

	$single = fn(?bool $checked) => new DocumentNode(new ContentNode([
		new ListNode([new ListItemNode(new ContentNode([paragraph('a')]), checked: $checked)]),
	]));
	Assert::same("- [x] a\n", (new Texy)->renderTexy($single(true)));
	Assert::same("- [ ] a\n", (new Texy)->renderTexy($single(false)));
	Assert::same("- a\n", (new Texy)->renderTexy($single(null)));
});


test('a soft break in the outputs', function () {
	$soft = fn(string $second) => new DocumentNode(new ContentNode([
		new ParagraphNode(new ContentNode([new TextNode('a'), new SoftBreakNode, new TextNode($second)])),
	]));

	Assert::same("<p>a b</p>\n", (new Texy)->createHtmlRenderer()->render($soft('b')));
	Assert::same("a\nb\n", (new TextRenderer)->render($soft('b')));
	Assert::same("a\nb\n", (new Texy)->renderTexy($soft('b')));
	Assert::same("a - b\n", (new Texy)->renderTexy($soft('- b')));
});
