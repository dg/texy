<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\Output\NodeRenderer;
use Texy\Syntax;


/**
 * Configuration of the HTML output: formatting and presentation options
 * and user node handlers, exposed as $texy->htmlOutput. Consumed by the
 * per-render Renderer and the WellFormer engine.
 */
class Config
{
	/** indent HTML code? */
	public bool $indent = true;

	/** @var string[]  tags whose content keeps its whitespace verbatim */
	public array $preserveSpaces = ['textarea', 'pre', 'script', 'code', 'samp', 'kbd'];

	/** base indent level */
	public int $baseIndent = 0;

	/** wrap width, doesn't include indent space */
	public int $lineWrap = 80;

	/** @var array{left: ?string, right: ?string, center: ?string, justify: ?string}  classes in place of style="text-align" */
	public array $textAlignClasses = ['left' => null, 'right' => null, 'center' => null, 'justify' => null];

	/** @var array{top: ?string, middle: ?string, bottom: ?string}  classes in place of style="vertical-align", null keeps the style */
	public array $verticalAlignClasses = ['top' => null, 'middle' => null, 'bottom' => null];

	/** @var array{left: ?string, right: ?string}  classes in place of style="float" of images and figures, null keeps the style */
	public array $floatClasses = ['left' => null, 'right' => null];

	/** Do obfuscate e-mail addresses? */
	public bool $obfuscateEmail = true;

	/** element in place of <p> for a paragraph holding images only */
	public string $imageParagraphTag = 'div';

	// links
	/** Shorten URLs to more readable form? */
	public bool $shortenUrls = true;

	/** CSS class for emoticons */
	public ?string $emoticonClass = null;

	/** element wrapping an image with its caption; <figure> puts the caption in <figcaption>, other elements in <p> */
	public string $figureTag = 'figure';

	/** class of every figure; a floated one gets the class of its float too */
	public ?string $figureClass = null;

	// horizontal rules
	/** @var array<string, ?string>  default CSS class for HR types */
	public array $horizontalRuleClasses = [
		'-' => null,
		'*' => null,
	];

	// HTML in the document
	/** Pass HTML comments to output? */
	public bool $passHtmlComments = true;

	/** base URL prepended to relative image URLs */
	public ?string $imageBaseUrl = null;

	/** base path of the image files on disk, where the dimensions of an image are read from */
	public ?string $imageBasePath = null;

	/** Always use rel="nofollow" for absolute links? */
	public bool $linkNoFollow = false;

	/** base URL prepended to relative link URLs */
	public ?string $linkBaseUrl = null;

	// phrases
	/** @var array<string, string> syntax → HTML tag mapping */
	public array $phraseTags = [
		Syntax::Strong => 'strong',
		Syntax::Emphasis => 'em',
		Syntax::EmphasisSingleAsterisk => 'em',
		Syntax::EmphasisSingleAsterisk2 => 'em',
		Syntax::Inserted => 'ins',
		Syntax::Deleted => 'del',
		Syntax::Superscript => 'sup',
		Syntax::SuperscriptShort => 'sup',
		Syntax::Subscript => 'sub',
		Syntax::SubscriptShort => 'sub',
		Syntax::SpanQuotes => 'span',
		Syntax::SpanTilde => 'span',
		Syntax::AbbreviationQuotes => 'abbr',
		Syntax::Abbreviation => 'abbr',
		Syntax::Code => 'code',
		Syntax::Quote => 'q',
		Syntax::QuickLink => 'a',
	];

	/** @var list<\Closure>  user node renderers (see addRenderer) */
	private array $userRenderers = [];


	/**
	 * Registers how a node class is rendered, fn(SomeNode $node, Renderer $renderer, ?\Closure $previous);
	 * the class is taken from the type of the first parameter. $previous($node) renders the node
	 * as it would be without this renderer, and returning null passes the node on to it.
	 */
	public function addRenderer(callable $renderer): void
	{
		$renderer = $renderer(...);
		NodeRenderer::renderedClass($renderer); // fail here, not at render time
		$this->userRenderers[] = $renderer;
	}


	/**
	 * @internal
	 * @return list<\Closure>
	 */
	public function getRenderers(): array
	{
		return $this->userRenderers;
	}
}
