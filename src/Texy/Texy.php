<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use JetBrains\PhpStorm\Language;
use function strlen;


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

	/** TAB width (for converting tabs to spaces) */
	public int $tabWidth = 8;

	/** @var array<string, string>  regexps to check URL schemes */
	public array $urlSchemeFilters; // disable URL scheme filter

	/** Paragraph merging mode */
	public bool $mergeLines = true;

	public bool $removeSoftHyphens = true;

	// Modules with runtime state (kept as-is)
	public Modules\ParagraphModule $paragraphModule;
	public Modules\ImageModule $imageModule;
	public Modules\LinkReferenceModule $linkModule;
	public Modules\PhraseModule $phraseModule;
	public Modules\EmoticonModule $emoticonModule;
	public Modules\HeadingModule $headingModule;
	public Modules\ListModule $listModule;
	public Modules\TypographyModule $typographyModule;
	public Modules\HyphenationModule $longWordsModule;

	/** HTML security policy: allowed tags, classes and inline styles */
	public readonly HtmlPolicy $htmlPolicy;

	public Output\Html\Config $htmlOutput;

	/** @var array<string, \Closure(string): string> @internal */
	public array $postHandlers = [];

	/** @var Module[] */
	private array $modules = [];

	/**
	 * The parsing engine - configured once, reused for multiple parse operations.
	 */
	private Engine $engine;

	/** @var array<string, list<\Closure(mixed...): mixed>> of events and registered handlers */
	private array $handlers = [];

	/** @var array<string, Compat\LegacyModuleProxy>  v3 compatibility */
	private array $legacyProxies = [];


	public function __construct()
	{
		$this->engine = new Engine;
		$this->htmlPolicy = new HtmlPolicy($this);
		$this->htmlOutput = new Output\Html\Config;
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
		$this->addModule($this->linkModule = new Modules\LinkReferenceModule($this));
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
		$this->addModule($this->listModule = new Modules\ListModule($this));

		// post process
		$this->addModule($this->typographyModule = new Modules\TypographyModule($this));
		$this->addModule($this->longWordsModule = new Modules\HyphenationModule($this));
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


	/** @param  \Closure  $handler  fn(ParseContext, array $matches, array $offsets, string $name): ?Nodes\InlineNode */
	final public function registerLinePattern(
		\Closure $handler,
		#[Language('RegExp')]
		string $pattern,
		string $name,
	): void
	{
		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->engine->registerLinePattern($handler, $pattern, $name);
	}


	/** @param  \Closure  $handler  fn(ParseContext, array $matches, array $offsets, string $name): ?Nodes\BlockNode */
	final public function registerBlockPattern(
		\Closure $handler,
		#[Language('RegExp')]
		string $pattern,
		string $name,
	): void
	{
		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->engine->registerBlockPattern($handler, $pattern, $name);
	}


	/** @param  \Closure(string): string  $handler */
	final public function registerPostLine(\Closure $handler, string $name): void
	{
		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->postHandlers[$name] = $handler;
		$this->engine->registerPostLine($handler, $name);
	}


	/**
	 * Converts Texy text to HTML.
	 */
	public function process(string $text, bool $singleLine = false): string
	{
		$this->htmlPolicy->resetCache();
		$text = $this->preprocess($text);
		$node = $this->parse($text, $singleLine);
		$html = (new Output\Html\Renderer($this->htmlOutput, $this))->render($node);
		return $html;
	}


	/**
	 * Parses text to AST.
	 */
	public function parse(string $text, bool $singleLine = false): Nodes\DocumentNode
	{
		// Let modules do per-parse initialization (pattern registration, state reset, text preprocessing)
		foreach ($this->modules as $module) {
			$module->beforeParse($text);
		}

		// Set gap handler for paragraphs
		$this->engine->setGapHandler(
			fn(ParseContext $context, string $gapText, int $offset) => $this->paragraphModule->parseText($context, $gapText, $offset),
		);

		// Parse using the engine with current allowed settings
		$document = $this->engine->parse($text, $this->allowed, $singleLine);

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

		// Initialize modules (pattern registration, state reset)
		foreach ($this->modules as $module) {
			$module->beforeParse($text);
		}

		// Create context using engine
		$context = $this->engine->createParseContext($this->allowed);
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


	/**
	 * Creates parse context (for advanced usage).
	 * Note: Modules must be initialized via parse() first for patterns to be available.
	 */
	public function createParseContext(): ParseContext
	{
		return $this->engine->createParseContext($this->allowed);
	}


	/**
	 * Get the underlying parsing engine.
	 * @internal
	 */
	public function getEngine(): Engine
	{
		return $this->engine;
	}


	final public function __clone()
	{
		throw new \LogicException('Clone is not supported.');
	}


	/**
	 * @deprecated v3 compatibility: serves properties that moved elsewhere and modules that no longer exist
	 */
	public function &__get(string $name): mixed
	{
		if (isset(Compat\Legacy::OfModule[$name])) {
			$proxy = $this->legacyProxies[$name] ??= new Compat\LegacyModuleProxy($this, $name);
			return $proxy;
		}

		return Compat\Legacy::ref($this, Compat\Legacy::OfTexy, '$texy', $name, 'read');
	}


	public function __set(string $name, mixed $value): void
	{
		Compat\Legacy::set($this, Compat\Legacy::OfTexy, '$texy', $name, $value);
	}


	public function __isset(string $name): bool
	{
		return isset(Compat\Legacy::OfModule[$name])
			|| Compat\Legacy::isSet($this, Compat\Legacy::OfTexy, $name);
	}
}


class_exists(\Texy::class);
