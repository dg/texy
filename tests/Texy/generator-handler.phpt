<?php

/**
 * Test: Generator custom handlers
 */

declare(strict_types=1);

use Tester\Assert;
use Texy\Nodes;
use Texy\Output\Html\Generator;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


function createGenerator(Texy\Texy $texy): Generator
{
	$generator = new Generator($texy);
	foreach ($texy->getModules() as $module) {
		$module->registerGeneratorHandlers($generator);
	}
	return $generator;
}


test('registerHandler replaces default handler', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse('hello');

	$generator = createGenerator($texy);
	$generator->registerHandler(
		Nodes\ParagraphNode::class,
		fn(Nodes\ParagraphNode $node, Generator $g) => $texy->protect('<div>custom</div>', Texy::CONTENT_BLOCK),
	);

	Assert::match('<div>custom</div>', trim($generator->generate($ast, $texy)));
});


test('wrapHandler wraps existing handler', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse('hello');

	$generator = createGenerator($texy);
	$generator->enableFormatting = false;
	$generator->wrapHandler(
		Nodes\ParagraphNode::class,
		fn(Nodes\ParagraphNode $node, Generator $g, callable $next) => $texy->protect(
			'<div class="wrapper">',
			Texy::CONTENT_BLOCK,
		)
			. $next()
			. $texy->protect('</div>', Texy::CONTENT_BLOCK),
	);

	Assert::match('<div class="wrapper"><p>hello</p></div>', trim($generator->generate($ast, $texy)));
});


test('handler has access to generator helpers', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->root = '/img/';
	$ast = $texy->parse('[* image.jpg *]');

	$generator = createGenerator($texy);
	$generator->enableFormatting = false;
	$generator->wrapHandler(
		Nodes\ImageNode::class,
		fn(Nodes\ImageNode $node, Generator $g, callable $next) => $texy->protect(
			'<figure data-root="' . htmlspecialchars($this->texy->imageModule->root ?? '') . '">',
			Texy::CONTENT_BLOCK,
		)
			. $next()
			. $texy->protect('</figure>', Texy::CONTENT_BLOCK),
	);

	Assert::match(
		'<div><figure data-root="/img/"><img src="/img/image.jpg" alt=""></figure></div>',
		trim($generator->generate($ast, $texy)),
	);
});


test('handler has access to Texy instance', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->top = 3;
	$ast = $texy->parse("Title\n=====");
	$texy->headingModule->afterParse($ast);

	$generator = createGenerator($texy);
	$generator->enableFormatting = false;
	$generator->wrapHandler(
		Nodes\HeadingNode::class,
		function (Nodes\HeadingNode $node, Generator $g, callable $next) use ($texy) {
			$top = $texy?->headingModule->top ?? 1;
			return $texy->protect("<div data-top=\"{$top}\">", Texy::CONTENT_BLOCK)
				. $next()
				. $texy->protect('</div>', Texy::CONTENT_BLOCK);
		},
	);

	Assert::match(
		'<div data-top="3"><h3>Title</h3></div>',
		trim($generator->generate($ast, $texy)),
	);
});
