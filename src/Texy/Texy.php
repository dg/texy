<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;

use JetBrains\PhpStorm\Language;
use function array_flip, base_convert, class_exists, count, explode, htmlspecialchars, implode, is_array, ltrim, str_contains, str_repeat, str_replace, strip_tags, strlen, strtr;


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

	// url filters
	public const
		FILTER_ANCHOR = 'anchor',
		FILTER_IMAGE = 'image';

	/** @var array<string, bool>  Texy! syntax configuration */
	public array $allowed = [];

	/** @var bool|array<int, string>  Allowed classes */
	public bool|array $allowedClasses = self::ALL; // all classes and id are allowed

	/** @var bool|array<int, string>  Allowed inline CSS style */
	public bool|array $allowedStyles = self::ALL;  // all inline styles are allowed

	/** TAB width (for converting tabs to spaces) */
	public int $tabWidth = 8;

	/** @var array<string, string>  regexps to check URL schemes */
	public array $urlSchemeFilters; // disable URL scheme filter

	/** Paragraph merging mode */
	public bool $mergeLines = true;

	public bool $removeSoftHyphens = true;
	public Modules\ParagraphModule $paragraphModule;
	public Modules\ImageModule $imageModule;
	public Modules\LinkModule $linkModule;
	public Modules\PhraseModule $phraseModule;
	public Modules\EmoticonModule $emoticonModule;
	public Modules\HeadingModule $headingModule;
	public Modules\TypographyModule $typographyModule;
	public Modules\LongWordsModule $longWordsModule;
	public Output\Html\Formatter $htmlOutputModule;
	public Output\Html\Generator $htmlGenerator;

	/** @var array<string, \Closure(string): string> @internal */
	public array $postHandlers = [];

	/** @var Module[] */
	private array $modules;

	/**
	 * Registered regexps and associated handlers for inline parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<int|string, string>, array<int|string, int|null>, string): ?Nodes\InlineNode, pattern: string, again?: ?string}>
	 */
	private array $linePatterns = [];

	/** @var array<string, array{handler: \Closure(ParseContext, array<int|string, string>, array<int|string, int|null>, string): ?Nodes\InlineNode, pattern: string, again?: ?string}> */
	private array $_linePatterns;

	/**
	 * Registered regexps and associated handlers for block parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<int|string, string>, array<int|string, int|null>, string): ?Nodes\BlockNode, pattern: string}>
	 */
	private array $blockPatterns = [];

	/** @var array<string, array{handler: \Closure(ParseContext, array<int|string, string>, array<int|string, int|null>, string): ?Nodes\BlockNode, pattern: string}> */
	private array $_blockPatterns;

	/** @var array<string, int>|bool  for internal usage */
	private bool|array $_classes;

	/** @var array<string, int>|bool  for internal usage */
	private bool|array $_styles;

	/** @var array<string, list<\Closure(mixed...): mixed>> of events and registered handlers */
	private array $handlers = [];


	public function __construct()
	{
		$this->htmlGenerator = new Output\Html\Generator($this);
		$this->loadModules();
	}


	/**
	 * Create array of all used modules ($this->modules).
	 * This array can be changed by overriding this method (by subclasses)
	 */
	protected function loadModules(): void
	{
		// line parsing
		$this->addModule(new Modules\DirectiveModule($this));
		$this->addModule(new Modules\HtmlModule($this));
		$this->addModule($this->imageModule = new Modules\ImageModule($this));
		$this->addModule($this->phraseModule = new Modules\PhraseModule($this));
		$this->addModule($this->linkModule = new Modules\LinkModule($this));
		$this->addModule(new Modules\AutolinkModule($this));
		$this->addModule($this->emoticonModule = new Modules\EmoticonModule($this));

		// block parsing
		$this->addModule($this->paragraphModule = new Modules\ParagraphModule($this));
		$this->addModule(new Modules\BlockModule($this));
		$this->addModule(new Modules\FigureModule($this));
		$this->addModule(new Modules\HorizontalRuleModule($this));
		$this->addModule(new Modules\BlockQuoteModule($this));
		$this->addModule(new Modules\TableModule($this));
		$this->addModule($this->headingModule = new Modules\HeadingModule($this));
		$this->addModule(new Modules\ListModule($this));

		// post process
		$this->addModule($this->typographyModule = new Modules\TypographyModule($this));
		$this->addModule($this->longWordsModule = new Modules\LongWordsModule($this));
		$this->htmlOutputModule = new Output\Html\Formatter;
	}


	public function addModule(Module $module): static
	{
		$this->modules[] = $module;
		return $this;
	}


	/** @return Module[] */
	public function getModules(): array
	{
		return $this->modules;
	}


	/**
	 * @param  \Closure(ParseContext, array<int|string, string>, array<int|string, int|null>, string): ?Nodes\InlineNode  $handler
	 */
	final public function registerLinePattern(
		\Closure $handler,
		#[Language('PhpRegExpXTCommentMode')]
		string $pattern,
		string $name,
	): void
	{
		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->linePatterns[$name] = [
			'handler' => $handler,
			'pattern' => $pattern,
		];
	}


	/**
	 * @param  \Closure(ParseContext, array<int|string, string>, array<int|string, int|null>, string): ?Nodes\BlockNode  $handler
	 */
	final public function registerBlockPattern(
		\Closure $handler,
		#[Language('PhpRegExpXTCommentMode')]
		string $pattern,
		string $name,
	): void
	{
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
	 * Converts Texy text to HTML.
	 */
	public function process(string $text, bool $singleLine = false): string
	{
		unset($this->_classes, $this->_styles, $this->_linePatterns, $this->_blockPatterns);
		$text = $this->preprocess($text);
		$node = $this->parse($text, $singleLine);
		$html = $this->htmlGenerator->render($node);
		return $html;
	}


	/**
	 * Parses text to AST.
	 */
	public function parse(string $text, bool $singleLine = false): Nodes\DocumentNode
	{
		foreach ($this->modules as $module) {
			$module->beforeParse($text);
		}

		$context = $this->createParseContext();
		$content = $singleLine
			? $context->parseInline($text)
			: $context->parseBlock($text);
		$document = new Nodes\DocumentNode($content);

		$this->invokeHandlers('afterParse', [$document]);
		return $document;
	}


	/**
	 * Preprocesses text (normalizes line endings, replaces tabs, removes soft hyphens).
	 */
	public function preprocess(string $text): string
	{
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

		return $text;
	}


	/**
	 * Converts single line in Texy! to HTML code.
	 */
	public function processLine(string $text): string
	{
		return $this->process($text, singleLine: true);
	}


	/**
	 * Parses single line to AST.
	 */
	public function parseLine(string $text): Nodes\ParagraphNode
	{
		$text = $this->preprocess($text);
		$context = $this->createParseContext();
		$inlineNodes = $context->parseInline($text);
		return new Nodes\ParagraphNode($inlineNodes);
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

		if (!empty($this->allowed[Syntax::Hyphenation])) {
			$text = $this->longWordsModule->postLine($text);
		}

		return $text;
	}


	/**
	 * Converts DOM structure to pure text.
	 */
	public function toText(): never
	{
		throw new \LogicException('Not implemented yet.');
	}


	/**
	 * Add new event handler.
	 */
	final public function addHandler(string $event, callable $callback): void
	{
		$this->handlers[$event][] = $callback(...);
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


	public function createParseContext(): ParseContext
	{
		return new ParseContext(
			$this->createInlineParser(),
			$this->createBlockParser(),
		);
	}


	private function createBlockParser(): BlockParser
	{
		$this->_blockPatterns ??= array_filter(
			$this->blockPatterns,
			fn($pattern, $name) => !empty($this->allowed[$name]),
			ARRAY_FILTER_USE_BOTH,
		);
		return new BlockParser(
			$this->_blockPatterns,
			fn(ParseContext $context, string $text, int $offset) => $this->paragraphModule->parseText($context, $text, $offset),
		);
	}


	private function createInlineParser(): InlineParser
	{
		$this->_linePatterns ??= array_filter(
			$this->linePatterns,
			fn($pattern, $name) => !empty($this->allowed[$name]),
			ARRAY_FILTER_USE_BOTH,
		);
		return new InlineParser($this->_linePatterns);
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
