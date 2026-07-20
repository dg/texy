<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

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

	/** @var array<string, ?string>  CSS classes for align modifiers */
	public array $alignClasses = [
		'left' => null,
		'right' => null,
		'center' => null,
		'justify' => null,
		'top' => null,
		'middle' => null,
		'bottom' => null,
	];

	/** Do obfuscate e-mail addresses? */
	public bool $obfuscateEmail = true;

	/** Element for image-only paragraphs */
	public string|Element $nontextParagraph = 'div';

	// AutolinkModule options
	/** Shorten URLs to more readable form? */
	public bool $shortenUrls = true;

	/** CSS class for emoticons */
	public ?string $emoticonClass = null;

	// FigureModule options
	/** Figure wrapper tag name */
	public string $figureTagName = 'div';

	/** Non-floated figure CSS class */
	public ?string $figureClass = 'figure';

	/** Left-floated figure CSS class */
	public ?string $figureLeftClass = null;

	/** Right-floated figure CSS class */
	public ?string $figureRightClass = null;

	// HorizontalRuleModule options
	/** @var array<string, ?string>  default CSS class for HR types */
	public array $horizontalRuleClasses = [
		'-' => null,
		'*' => null,
	];

	// HtmlModule options
	/** Pass HTML comments to output? */
	public bool $passHtmlComments = true;

	// ImageModule options
	/** Left-floated images CSS class */
	public ?string $imageLeftClass = null;

	/** Right-floated images CSS class */
	public ?string $imageRightClass = null;

	/** Root path for image URLs */
	public ?string $imageRoot = 'images/';

	/** File system root for image dimension detection */
	public ?string $imageFileRoot = null;

	// LinkReferenceModule options
	/** Always use rel="nofollow" for absolute links? */
	public bool $linkNoFollow = false;

	/** Root path for link URLs */
	public ?string $linkRoot = null;

	// PhraseModule options
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

	/** @var list<\Closure>  user node handlers (see registerHandler) */
	private array $userHandlers = [];


	/**
	 * Register a handler for a node class (determined by the type of its
	 * first parameter). Return null to delegate to the previous handler.
	 */
	public function registerHandler(\Closure $handler): void
	{
		$this->userHandlers[] = $handler;
	}


	/**
	 * @internal
	 * @return list<\Closure>
	 */
	public function getHandlers(): array
	{
		return $this->userHandlers;
	}
}
