<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use JetBrains\PhpStorm\Language;
use Texy\Parsing\Engine;
use Texy\Parsing\ParseContext;
use Texy\Passes\TextRunPass;
use function strlen;
use const ENT_NOQUOTES;


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
	public const Version = '4.0.0-dev';

	// configuration directives
	public const
		All = true,
		None = false;

	#[\Deprecated('use Texy::Version')]
	public const VERSION = self::Version;

	#[\Deprecated('use Texy::All')]
	public const ALL = self::All;

	#[\Deprecated('use Texy::None')]
	public const NONE = self::None;

	/** @var array<string, bool>  Texy! syntax configuration */
	public array $allowed = [];

	/** TAB width (for converting tabs to spaces) */
	public int $tabWidth = 8;

	/** URL scheme security policy for links and images */
	public readonly UrlPolicy $urlPolicy;

	/** Paragraph merging mode */
	public bool $mergeLines = true;

	/** @deprecated */
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

	/** @var Module[] */
	private array $modules = [];

	/**
	 * The parsing engine - a cumulative pattern registry. Modules refresh their
	 * patterns before each parse; consumer registrations are never overwritten.
	 */
	private Engine $engine;

	/** @var array{line: array<string, true>, block: array<string, true>}  syntax names registered by the consumer */
	private array $consumerSyntaxes = ['line' => [], 'block' => []];

	private bool $inModulePrepare = false;

	/** @var array<string, list<\Closure(mixed...): mixed>> of events and registered handlers */
	private array $handlers = [];

	/** @var array<string, Compat\LegacyModuleProxy>  v3 compatibility */
	private array $legacyProxies = [];


	public function __construct()
	{
		$this->engine = new Engine;
		$this->urlPolicy = new UrlPolicy;
		$this->htmlPolicy = new HtmlPolicy($this->urlPolicy);
		$this->htmlOutput = new Output\Html\Config;
		$this->loadModules();
		$this->engine->setGapHandler(
			fn(ParseContext $context, string $gapText, int $offset) => $this->paragraphModule->parseText($context, $gapText, $offset),
		);
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
		if ($this->inModulePrepare) {
			if (isset($this->consumerSyntaxes['line'][$name])) {
				return; // a syntax registered by the consumer is never overwritten by a module
			}
		} else {
			$this->consumerSyntaxes['line'][$name] = true;
		}

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
		if ($this->inModulePrepare) {
			if (isset($this->consumerSyntaxes['block'][$name])) {
				return; // a syntax registered by the consumer is never overwritten by a module
			}
		} else {
			$this->consumerSyntaxes['block'][$name] = true;
		}

		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->engine->registerBlockPattern($handler, $pattern, $name);
	}


	/**
	 * Converts Texy text to HTML.
	 */
	public function process(string $text, bool $singleLine = false): string
	{
		$node = $this->parse($text, $singleLine);
		$renderer = new Output\Html\Renderer($this->htmlOutput, $this);
		$html = $renderer->render($node);
		return $html;
	}


	/**
	 * Parses text to AST. The text is preprocessed first (idempotent), so node
	 * ranges always refer to the preprocessed form obtainable via preprocess().
	 */
	public function parse(string $text, bool $singleLine = false): Nodes\DocumentNode
	{
		$text = $this->preprocess($text);

		// Let modules do per-parse initialization (pattern registration, state reset)
		$this->inModulePrepare = true;
		try {
			foreach ($this->modules as $module) {
				$module->beforeParse();
			}
		} finally {
			$this->inModulePrepare = false;
		}

		// Parse using the engine with current allowed settings
		$document = $this->engine->parse($text, $this->allowed, $singleLine);

		$this->invokeHandlers('afterParse', [$document]);
		$this->applyAstTypography($document);
		return $document;
	}


	/**
	 * Runs typography/hyphenation over the AST and marks the document so
	 * generators skip their string post-processing.
	 */
	private function applyAstTypography(Nodes\DocumentNode $document): void
	{
		$transformers = [];
		if (!empty($this->allowed[Syntax::Typography])) {
			$transformers[] = fn(string $s): string => $this->typographyModule->postLine($s);
		}
		if (!empty($this->allowed[Syntax::Hyphenation])) {
			$transformers[] = fn(string $s): string => $this->longWordsModule->postLine($s);
		}

		(new TextRunPass(
			$transformers,
			empty($this->allowed[Syntax::Typography])
				? null
				: fn(string $s): string => $this->typographyModule->postLine($s),
		))->process($document);

		$document->meta['typographed'] = true;
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
	 * Makes only typographic corrections.
	 */
	public function processTypo(string $text): string
	{
		// standardize line endings and spaces
		$text = Helpers::normalize($text);

		$text = $this->typographyModule->postLine($text, preserveSpaces: true);

		if (!empty($this->allowed[Syntax::Hyphenation])) {
			$text = $this->longWordsModule->postLine($text);
		}

		return $text;
	}


	/**
	 * Converts Texy text to a plain-text rendition.
	 */
	public function toText(string $text): string
	{
		$node = $this->parse($this->preprocess($text));
		return (new Output\Text\Renderer)->render($node);
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
	 * Creates parse context (for advanced usage).
	 * Note: Modules must be initialized via parse() first for patterns to be available.
	 */
	public function createParseContext(): ParseContext
	{
		return $this->engine->createParseContext($this->allowed);
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


	/**
	 * @deprecated v3 compatibility: navigates away from removed methods
	 * @param  array<mixed>  $args
	 */
	public function __call(string $name, array $args): mixed
	{
		return Compat\Legacy::call($this, $name, $args);
	}
}


class_exists(\Texy::class);
