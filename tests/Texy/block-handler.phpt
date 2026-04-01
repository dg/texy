<?php declare(strict_types=1);

/**
 * Test: Config::delimitedBlockHandler - the handler of delimited blocks.
 */

use Tester\Assert;
use Texy\{Modifier, Nodes, Syntax, Texy};
use Texy\Parsing\ParseContext;

require __DIR__ . '/../bootstrap.php';


function createTexy(): Texy
{
	$texy = new Texy;
	$texy->config->delimitedBlockHandler = fn(
		ParseContext $context,
		string $type,
		string $content,
		?string $param,
		?Modifier $modifier,
		Closure $next,
	) => match ($type) {
		'gallery' => new Nodes\DivNode($context->parseBlock($content), $modifier),
		'html' => new Nodes\CodeBlockNode($content, 'html', $modifier),
		default => $next(),
	};
	return $texy;
}


test('the handler gets every type that is switched on', function () {
	$texy = new Texy;
	$calls = [];
	$texy->config->delimitedBlockHandler = function (
		ParseContext $context,
		string $type,
		string $content,
		?string $param,
		?Modifier $modifier,
		Closure $next,
	) use (&$calls) {
		$node = $next();
		$calls[] = [$type, $content, $param, $modifier?->classes, $node === null ? null : $node::class];
		return $node;
	};
	$texy->parse("/--pre\n  a\n\\--\n\n/--\nb\n\\--\n\n/--Gallery big .[x]\n\tc\n\\--\n\n/--div\n\\--");

	Assert::same([
		['pre', 'a', null, null, Nodes\PreformattedNode::class],
		['', 'b', null, null, Nodes\PreformattedNode::class],
		['gallery', 'c', 'big', ['x' => true], Nodes\UnknownDelimitedNode::class],
		['div', '', null, null, null],
	], $calls);
});


test('a switched-off built-in type does not reach the handler and is text', function () {
	$texy = new Texy;
	$texy->config->delimitedBlockHandler = fn() => throw new LogicException('called');
	$texy->allowedSyntax[Syntax::DelimitedPre] = false;
	Assert::same('<p>/--pre x \--</p>', trim($texy->toHtml("/--pre\nx\n\\--")));
});


test('the handler redefines a built-in type for every output', function () {
	$texy = createTexy();
	$document = $texy->parse("/--html\n<b>x</b>\n\\--");
	Assert::type(Nodes\CodeBlockNode::class, $document->content->children[0]);
	Assert::same('<pre class="language-html"><code>&lt;b&gt;x&lt;/b&gt;</code></pre>', trim($texy->toHtml("/--html\n<b>x</b>\n\\--")));
});


test('what the handler parsed from the content points into the source', function () {
	$src = "intro\n\n/--gallery .[x]\n    a *b*\n\\--";
	$texy = createTexy();
	$div = $texy->parse($src)->content->children[1];
	Assert::type(Nodes\DivNode::class, $div);
	Assert::same("/--gallery .[x]\n    a *b*\n\\--", substr($src, $div->range->offset, $div->range->length));
	$text = $div->findFirst(Nodes\TextNode::class);
	Assert::same('a ', substr($src, $text->range->offset, $text->range->length));
	Assert::contains("<div class=\"x\">\n\t<p>a <em>b</em></p>\n</div>", $texy->toHtml($src));
});


test('a node of $next() keeps its ranges, returned or wrapped', function () {
	$src = "intro\n\n/--pre\n    a <b>x</b>\n\\--\n\n/--pre quoted\n    a <b>x</b>\n\\--";
	$texy = new Texy;
	$texy->config->delimitedBlockHandler = fn(
		ParseContext $context,
		string $type,
		string $content,
		?string $param,
		?Modifier $modifier,
		Closure $next,
	) => $param === 'quoted'
		? new Nodes\BlockQuoteNode(new Nodes\ContentNode([$next()]))
		: $next();
	[, $pre, $quote] = $texy->parse($src)->content->children;
	Assert::type(Nodes\BlockQuoteNode::class, $quote);
	foreach ([$pre, $quote->content->children[0]] as $node) {
		Assert::type(Nodes\PreformattedNode::class, $node);
		$tag = $node->findFirst(Nodes\HtmlTagNode::class);
		Assert::same('<b>', substr($src, $tag->range->offset, $tag->range->length));
	}
	Assert::same("/--pre quoted\n    a <b>x</b>\n\\--", substr($src, $quote->range->offset, $quote->range->length));
});


test('returning null leaves the block as text', function () {
	$texy = new Texy;
	$texy->config->delimitedBlockHandler = fn() => null;
	Assert::same('<p>/--pre x \--</p>', trim($texy->toHtml("/--pre\nx\n\\--")));
});


test('the handler lasts across documents until it is removed', function () {
	$texy = createTexy();
	Assert::contains('language-html', $texy->toHtml("/--html\nx\n\\--"));
	Assert::contains('language-html', $texy->toHtml("/--html\nx\n\\--"));
	$texy->config->delimitedBlockHandler = null;
	Assert::same('x', trim($texy->toHtml("/--html\nx\n\\--")));
});


test('/--php as a code block', function () {
	$texy = new Texy;
	$texy->config->delimitedBlockHandler = fn(
		ParseContext $context,
		string $type,
		string $content,
		?string $param,
		?Modifier $modifier,
		Closure $next,
	) => match ($type) {
		'php', 'neon' => new Nodes\CodeBlockNode($content, $type, $modifier),
		default => $next(),
	};
	Assert::same(
		"<pre class=\"language-php\"><code>echo 'x';</code></pre>",
		trim($texy->toHtml("/--php\necho 'x';\n\\--")),
	);
});
