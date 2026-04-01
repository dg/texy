<?php declare(strict_types=1);

/**
 * Test: TexyConfig::addBlockType() - a block type of the consumer.
 */

use Tester\Assert;
use Texy\{Modifier, Nodes};
use Texy\Parsing\ParseContext;

require __DIR__ . '/../bootstrap.php';


function createTexy(): Texy\Texy
{
	$texy = new Texy\Texy;
	$texy->config->addBlockType('gallery', function (ParseContext $context, string $content, ?string $param, ?Modifier $modifier) {
		$modifier ??= new Modifier;
		$modifier->classes['gallery'] = true;
		return new Nodes\DivNode($context->parseBlock($content), $modifier);
	});
	return $texy;
}


test('the content is outdented and parsed by the handler, the modifier applies', function () {
	Assert::same(
		"<div class=\"x gallery\">\n\t<p>a <em>b</em></p>\n</div>",
		trim(createTexy()->toHtml("/--gallery .[x]\n\ta *b*\n\\--")),
	);
});


test('what the handler parsed points into the source', function () {
	$src = "intro\n\n/--gallery\n    a *b*\n\\--";
	$document = createTexy()->parse($src);
	$div = $document->content->children[1];
	Assert::type(Nodes\DivNode::class, $div);
	Assert::same("/--gallery\n    a *b*\n\\--", substr($src, $div->range->offset, $div->range->length));
	$text = $div->findFirst(Nodes\TextNode::class);
	Assert::same('a ', substr($src, $text->range->offset, $text->range->length));
});


test('a handler returning null leaves the block as text', function () {
	$texy = new Texy\Texy;
	$texy->config->addBlockType('nothing', fn() => null);
	Assert::same('<p>/--nothing x \--</p>', trim($texy->toHtml("/--nothing\nx\n\\--")));
});


test('a type is allowed like a built-in one', function () {
	$texy = createTexy();
	$texy->allowedSyntax['block/gallery'] = false;
	Assert::same('<p>/--gallery x \--</p>', trim($texy->toHtml("/--gallery\nx\n\\--")));
});
