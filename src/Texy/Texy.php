<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use JetBrains\PhpStorm\Language;
use function array_flip, base_convert, class_exists, count, explode, htmlspecialchars, implode, is_array, ltrim, str_contains, str_repeat, str_replace, strip_tags, strlen, strtr;
use const ARRAY_FILTER_USE_BOTH, ENT_NOQUOTES;


/**
 * Texy! - Convert plain text to HTML format using {@link process()}.
 *
 * <code>
 * $texy = new Texy();
 * $html = $texy->process($text);
 * </code>
 */
class Texy
{
	// Texy version
	public const VERSION = '4.0.0-dev';

	// configuration directives
	public const
		ALL = true,
		NONE = false;

	// types of protection marks
	public const
		CONTENT_MARKUP = "\x17",
		CONTENT_REPLACED = "\x16",
		CONTENT_TEXTUAL = "\x15",
		CONTENT_BLOCK = "\x14";

	// url filters
	public const
		FILTER_ANCHOR = 'anchor',
		FILTER_IMAGE = 'image';

	/** @var array<string, bool>  Texy! syntax configuration */
	public array $allowed = [];

	/** @var bool|array<string, bool|array<int, string>>  Allowed HTML tags */
	public bool|array $allowedTags;

	/** @var bool|array<int, string>  Allowed classes */
	public bool|array $allowedClasses = self::ALL; // all classes and id are allowed

	/** @var bool|array<int, string>  Allowed inline CSS style */
	public bool|array $allowedStyles = self::ALL;  // all inline styles are allowed

	/** TAB width (for converting tabs to spaces) */
	public int $tabWidth = 8;

	/** Do obfuscate e-mail addresses? */
	public bool $obfuscateEmail = true;

	/** @var array<string, string>  regexps to check URL schemes */
	public array $urlSchemeFilters; // disable URL scheme filter

	/** Paragraph merging mode */
	public bool $mergeLines = true;

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

	public bool $removeSoftHyphens = true;
	public string|HtmlElement $nontextParagraph = 'div';
	public Modules\DirectiveModule $scriptModule;
	public Modules\ParagraphModule $paragraphModule;
	public Modules\HtmlModule $htmlModule;
	public Modules\ImageModule $imageModule;
	public Modules\LinkModule $linkModule;
	public Modules\AutolinkModule $autolinkModule;
	public Modules\PhraseModule $phraseModule;
	public Modules\EmoticonModule $emoticonModule;
	public Modules\BlockModule $blockModule;
	public Modules\HeadingModule $headingModule;
	public Modules\HorizontalRuleModule $horizLineModule;
	public Modules\BlockQuoteModule $blockQuoteModule;
	public Modules\ListModule $listModule;
	public Modules\TableModule $tableModule;
	public Modules\FigureModule $figureModule;
	public Modules\TypographyModule $typographyModule;
	public Modules\LongWordsModule $longWordsModule;
	public Modules\HtmlOutputModule $htmlOutputModule;

	/** @var Module[] */
	private array $modules;

	/**
	 * Registered regexps and associated handlers for inline parsing.
	 * @var array<string, array{handler: \Closure(InlineParser, array<?string>, string): (HtmlElement|string|null), pattern: string, again: ?string}>
	 */
	private array $linePatterns = [];

	/** @var array<string, array{handler: \Closure(InlineParser, array<?string>, string): (HtmlElement|string|null), pattern: string, again: ?string}> */
	private array $_linePatterns;

	/**
	 * Registered regexps and associated handlers for block parsing.
	 * @var array<string, array{handler: \Closure(BlockParser, array<?string>, string): (HtmlElement|string|null), pattern: string}>
	 */
	private array $blockPatterns = [];

	/** @var array<string, array{handler: \Closure(BlockParser, array<?string>, string): (HtmlElement|string|null), pattern: string}> */
	private array $_blockPatterns;

	/** @var array<string, \Closure(string): string> */
	private array $postHandlers = [];

	/** DOM structure for parsed text */
	private HtmlElement $DOM;

	/** @var array<string, string>  Texy protect markup table */
	private array $marks = [];

	/** @var array<string, int>|bool  for internal usage */
	private bool|array $_classes;

	/** @var array<string, int>|bool  for internal usage */
	private bool|array $_styles;

	private bool $processing = false;

	/** @var array<string, list<\Closure(mixed...): mixed>> of events and registered handlers */
	private array $handlers = [];


	public function __construct()
	{
		$this->loadModules();
		$this->initAllowedTags();
	}


	private function initAllowedTags(): void
	{
		// accept all valid HTML tags and attributes by default
		$this->allowedTags = [];
		foreach (Modules\HtmlOutputModule::$inlineElements as $tag => $_) {
			$this->allowedTags[$tag] = self::ALL;
		}
		foreach (Modules\HtmlOutputModule::$emptyElements as $tag => $_) {
			$this->allowedTags[$tag] = self::ALL;
		}
		// common block elements
		foreach (['div', 'p', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'table', 'thead', 'tbody',
			'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col', 'blockquote',
			'pre', 'figure', 'figcaption', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
			'header', 'footer', 'main', 'article', 'section', 'nav', 'aside', 'address',
			'form', 'fieldset', 'legend'] as $tag) {
			$this->allowedTags[$tag] = self::ALL;
		}
	}


	/**
	 * Create array of all used modules ($this->modules).
	 * This array can be changed by overriding this method (by subclasses)
	 */
	protected function loadModules(): void
	{
		// line parsing
		$this->addModule($this->scriptModule = new Modules\DirectiveModule($this));
		$this->addModule($this->htmlModule = new Modules\HtmlModule($this));
		$this->addModule($this->imageModule = new Modules\ImageModule($this));
		$this->addModule($this->phraseModule = new Modules\PhraseModule($this));
		$this->addModule($this->linkModule = new Modules\LinkModule($this));
		$this->addModule($this->autolinkModule = new Modules\AutolinkModule($this));
		$this->addModule($this->emoticonModule = new Modules\EmoticonModule($this));

		// block parsing
		$this->addModule($this->paragraphModule = new Modules\ParagraphModule($this));
		$this->addModule($this->blockModule = new Modules\BlockModule($this));
		$this->addModule($this->figureModule = new Modules\FigureModule($this));
		$this->addModule($this->horizLineModule = new Modules\HorizontalRuleModule($this));
		$this->addModule($this->blockQuoteModule = new Modules\BlockQuoteModule($this));
		$this->addModule($this->tableModule = new Modules\TableModule($this));
		$this->addModule($this->headingModule = new Modules\HeadingModule($this));
		$this->addModule($this->listModule = new Modules\ListModule($this));

		// post process
		$this->addModule($this->typographyModule = new Modules\TypographyModule($this));
		$this->addModule($this->longWordsModule = new Modules\LongWordsModule($this));
		$this->addModule($this->htmlOutputModule = new Modules\HtmlOutputModule($this));
	}


	public function addModule(Module $module): static
	{
		$this->modules[] = $module;
		return $this;
	}


	/**
	 * @param  \Closure(InlineParser, array<?string>, string): (HtmlElement|string|null)  $handler
	 */
	final public function registerLinePattern(
		\Closure $handler,
		#[Language('PhpRegExpXTCommentMode')]
		string $pattern,
		string $name,
		?string $againTest = null,
	): void
	{
		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->linePatterns[$name] = [
			'handler' => $handler,
			'pattern' => $pattern,
			'again' => $againTest,
		];
	}


	/**
	 * @param  \Closure(BlockParser, array<?string>, string): (HtmlElement|string|null)  $handler
	 */
	final public function registerBlockPattern(
		\Closure $handler,
		#[Language('PhpRegExpXTCommentMode')]
		string $pattern,
		string $name,
	): void
	{
		// if (!Regexp::match($pattern, '~(.)\^.*\$\1[a-z]*~is')) die("Texy: Not a block pattern $name");
		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->blockPatterns[$name] = [
			'handler' => $handler,
			'pattern' => $pattern . 'm', // force multiline
		];
	}


	/** @param  \Closure(string): string  $handler */
	final public function registerPostLine(\Closure $handler, string $name): void
	{
		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->postHandlers[$name] = $handler;
	}


	/**
	 * Converts document in Texy! to (X)HTML code.
	 */
	public function process(string $text, bool $singleLine = false): string
	{
		if ($this->processing) {
			throw new Exception('Processing is in progress yet.');
		}

		// initialization
		$this->marks = [];
		$this->processing = true;
		unset($this->_classes, $this->_styles, $this->_linePatterns,$this->_blockPatterns);

		if ($this->removeSoftHyphens) {
			$text = str_replace("\u{AD}", '', $text);
		}

		// standardize line endings and spaces
		$text = Helpers::normalize($text);

		// replace tabs with spaces
		if ($this->tabWidth) {
			while (str_contains($text, "\t")) {
				$text = Regexp::replace(
					$text,
					'~^([^\t\n]*+)\t~mU',
					fn($m) => $m[1] . str_repeat(' ', $this->tabWidth - strlen($m[1]) % $this->tabWidth),
				);
			}
		}

		foreach ($this->modules as $module) {
			$module->beforeParse($text);
		}

		// parse Texy! document into internal DOM structure
		$this->DOM = new HtmlElement;
		if ($singleLine) {
			$this->DOM->parseLine($this, $text);
		} else {
			$this->DOM->parseBlock($this, $text);
		}

		$this->invokeHandlers('afterParse', [$this->DOM, $singleLine]);

		// converts internal DOM structure to final HTML code
		$html = $this->DOM->toHtml($this);

		// created by ParagraphModule and then protected
		$html = str_replace("\r", "\n", $html);

		$this->processing = false;
		return $html;
	}


	/**
	 * Converts single line in Texy! to (X)HTML code.
	 */
	public function processLine(string $text): string
	{
		return $this->process($text, singleLine: true);
	}


	/**
	 * Makes only typographic corrections.
	 */
	public function processTypo(string $text): string
	{
		// standardize line endings and spaces
		$text = Helpers::normalize($text);

		$this->typographyModule->beforeParse($text);
		$text = $this->typographyModule->postLine($text, preserveSpaces: true);

		if (!empty($this->allowed['longwords'])) {
			$text = $this->longWordsModule->postLine($text);
		}

		return $text;
	}


	/**
	 * Converts DOM structure to pure text.
	 */
	public function toText(): string
	{
		if (!isset($this->DOM)) {
			throw new Exception('Call $texy->process() first.');
		}

		return $this->DOM->toText($this);
	}


	/**
	 * Converts internal string representation to final HTML code.
	 */
	final public function stringToHtml(string $s): string
	{
		// decode HTML entities to UTF-8
		$s = Helpers::unescapeHtml($s);

		// line-postprocessing
		$blocks = explode(self::CONTENT_BLOCK, $s);
		foreach ($this->postHandlers as $name => $handler) {
			if (empty($this->allowed[$name])) {
				continue;
			}

			foreach ($blocks as $n => $s) {
				if ($n % 2 === 0 && $s !== '') {
					$blocks[$n] = $handler($s);
				}
			}
		}

		$s = implode(self::CONTENT_BLOCK, $blocks);

		// encode < > &
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');

		// replace protected marks
		$s = $this->unprotect($s);

		// wellform and reformat HTML
		$this->invokeHandlers('postProcess', [&$s]);

		// unfreeze spaces
		$s = Helpers::unfreezeSpaces($s);
		$s = ltrim($s, "\n");

		return $s;
	}


	/**
	 * Converts internal string representation to final HTML code.
	 */
	final public function stringToText(string $s): string
	{
		$s = $this->stringToHtml($s);

		// remove tags
		$s = Regexp::replace($s, '~<(script|style)(.*)</\1>~Uis', '');
		$s = strip_tags($s);
		$s = Regexp::replace($s, '~\n\s*\n\s*\n[\n\s]*\n~', "\n\n");

		// entities -> chars
		$s = Helpers::unescapeHtml($s);

		// convert nbsp to normal space and remove shy
		$s = strtr($s, [
			"\u{AD}" => '', // shy
			"\u{A0}" => ' ', // nbsp
		]);

		return $s;
	}


	/**
	 * Add new event handler.
	 */
	final public function addHandler(string $event, \Closure $callback): void
	{
		$this->handlers[$event][] = $callback;
	}


	/**
	 * Invoke registered around-handlers.
	 * @param  mixed[]  $args
	 */
	final public function invokeAroundHandlers(string $event, Parser $parser, array $args): mixed
	{
		if (!isset($this->handlers[$event])) {
			return null;
		}

		$invocation = new HandlerInvocation($this->handlers[$event], $parser, $args);
		return $invocation->proceed();
	}


	/**
	 * Invoke registered after-handlers.
	 * @param  mixed[]  $args
	 */
	final public function invokeHandlers(string $event, array $args): void
	{
		if (!isset($this->handlers[$event])) {
			return;
		}

		foreach ($this->handlers[$event] as $handler) {
			$handler(...$args);
		}
	}


	/**
	 * Generate unique mark - useful for freezing (folding) some substrings.
	 */
	final public function protect(string $child, string $contentType): string
	{
		if ($child === '') {
			return '';
		}

		$key = $contentType
			. strtr(base_convert((string) count($this->marks), 10, 8), '01234567', "\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F")
			. $contentType;

		$this->marks[$key] = $child;

		return $key;
	}


	/**
	 * Filters bad URLs.
	 * @param  string  $type  Texy::FILTER_ANCHOR | Texy::FILTER_IMAGE
	 */
	final public function checkURL(string $URL, string $type): bool
	{
		// absolute URL with scheme? check scheme!
		return empty($this->urlSchemeFilters[$type])
			|| !Regexp::match($URL, '~\s*[a-z][a-z0-9+.-]{0,20}:~Ai') // http: | mailto:
			|| Regexp::match($URL, $this->urlSchemeFilters[$type]);
	}


	final public function unprotect(string $html): string
	{
		return strtr($html, $this->marks);
	}


	public function createBlockParser(bool $indented): BlockParser
	{
		$this->_blockPatterns ??= array_filter(
			$this->blockPatterns,
			fn($pattern, $name) => !empty($this->allowed[$name]),
			ARRAY_FILTER_USE_BOTH,
		);
		return new BlockParser($this, $indented, $this->_blockPatterns);
	}


	public function createInlineParser(): InlineParser
	{
		$this->_linePatterns ??= array_filter(
			$this->linePatterns,
			fn($pattern, $name) => !empty($this->allowed[$name]),
			ARRAY_FILTER_USE_BOTH,
		);
		return new InlineParser($this, $this->_linePatterns);
	}


	final public function getDOM(): HtmlElement
	{
		return $this->DOM;
	}


	/**
	 * @internal
	 * @return array{array<string, int>|bool, array<string, int>|bool}
	 */
	final public function getAllowedProps(): array
	{
		$this->_classes ??= is_array($this->allowedClasses)
			? array_flip($this->allowedClasses)
			: $this->allowedClasses;
		$this->_styles ??= is_array($this->allowedStyles)
			? array_flip($this->allowedStyles)
			: $this->allowedStyles;
		return [$this->_classes, $this->_styles];
	}


	final public function __clone()
	{
		throw new \LogicException('Clone is not supported.');
	}
}


class_exists(\Texy::class);
