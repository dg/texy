<?php declare(strict_types=1);

/**
 * Test: Custom ImageNode handler works inside FigureNode.
 */

use Tester\Assert;
use Texy\Nodes\ImageNode;
use Texy\Output\Html;

require __DIR__ . '/../bootstrap.php';


test('custom ImageNode handler is called for images in figures', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 500; // Prevent line wrapping in output

	// Register custom handler that transforms youtube: URLs to iframes
	$texy->htmlOutput->addRenderer(
		function (ImageNode $node, Html\Renderer $gen, ?Closure $previous): Html\Element|string|null {
			if (!str_starts_with($node->url ?? '', 'youtube:')) {
				return $previous ? $previous($node) : null;
			}

			$videoId = substr($node->url, 8);
			$width = $node->width ?: 640;
			$height = $node->height ?: 360;

			return $gen->protect(
				'<iframe src="https://youtube.com/embed/'
				. htmlspecialchars($videoId)
				. '" width="'
				. $width
				. '" height="'
				. $height
				. '"></iframe>',
				$gen::ContentBlock,
			);
		},
	);

	// Figure syntax (image on its own line)
	$html = $texy->process('[* youtube:abc123 320x240 *]');

	Assert::contains('https://youtube.com/embed/abc123', $html);
	Assert::contains('width="320"', $html);
	Assert::contains('height="240"', $html);
	Assert::contains('<div class="figure">', $html);
});


test('custom ImageNode handler is called for inline images', function () {
	$texy = new Texy\Texy;

	$texy->htmlOutput->addRenderer(
		function (ImageNode $node, Html\Renderer $gen, ?Closure $previous): Html\Element|string|null {
			if (!str_starts_with($node->url ?? '', 'youtube:')) {
				return $previous ? $previous($node) : null;
			}

			$videoId = substr($node->url, 8);
			return $gen->protect(
				'<iframe src="https://youtube.com/embed/' . htmlspecialchars($videoId) . '"></iframe>',
				$gen::ContentReplaced,
			);
		},
	);

	// Inline image (within paragraph)
	$html = $texy->process('Watch this: [* youtube:xyz789 *] video.');

	Assert::contains('<iframe src="https://youtube.com/embed/xyz789"></iframe>', $html);
	Assert::contains('<p>Watch this:', $html);
});


test('figure alignment is applied to wrapper, not to image', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->rightClass = 'float-right';

	// Right-aligned figure (alignment inside brackets)
	$html = $texy->process('[* image.jpg >]');

	// Alignment should be on wrapper
	Assert::contains('class="figure float-right"', $html);
	// Image should not have float class
	Assert::notContains('style="float', $html);
});


test('a custom FigureNode renderer applies the float as configured', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->leftClass = 'float-left';
	$texy->htmlOutput->addRenderer(function (Texy\Nodes\FigureNode $node, Html\Renderer $gen): Html\Element {
		$el = new Html\Element('dl');
		$el->create('dt')->children = $gen->renderNodes([$node->image]);
		$gen->decorateElement($node->modifier, $el);
		$gen->applyFloat($el, $node->float);
		return $el;
	});

	Assert::contains('<dl class="float-left">', $texy->process('[* image.jpg <]'));
});
