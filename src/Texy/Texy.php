<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use JetBrains\PhpStorm\Language;
use Texy\Parsing\{BlockParser, InlineParser, ParseContext};
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

	/** @deprecated */
	public bool $removeSoftHyphens = true;

	public Modules\ParagraphModule $paragraphModule;
	public Modules\ImageModule $imageModule;
	public Modules\AutolinkModule $autolinkModule;
	public Modules\LinkReferenceModule $linkModule;
	public Modules\PhraseModule $phraseModule;
	public Modules\EmoticonModule $emoticonModule;
	public Modules\HeadingModule $headingModule;
	public Modules\ListModule $listModule;
	public Modules\FigureModule $figureModule;
	public Modules\HorizontalRuleModule $horizLineModule;
	public Modules\TypographyModule $typographyModule;
	public Modules\HyphenationModule $longWordsModule;

	/** HTML security policy: allowed tags, classes and inline styles */
	public readonly HtmlPolicy $htmlPolicy;

	public Output\Html\Config $htmlOutput;

	/** @var array<string, \Closure(string): string> @internal */
	public array $postHandlers = [];

	/** @var Module[] */
	private array $modules;

	/**
	 * Registered regexps and associated handlers for inline parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\InlineNode, pattern: string}>
	 */
	private array $linePatterns = [];

	/**
	 * Inline patterns the configuration allows, cached for parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\InlineNode, pattern: string}>
	 */
	private array $activeLinePatterns;

	/**
	 * Registered regexps and associated handlers for block parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\BlockNode, pattern: string}>
	 */
	private array $blockPatterns = [];

	/**
	 * Block patterns the configuration allows, cached for parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\BlockNode, pattern: string}>
	 */
	private array $activeBlockPatterns;

	/** @var array<string, list<\Closure(Nodes\DocumentNode): void>>  stage name => passes in registration order */
	private array $passes = [];

	/** @var array{line: array<string, true>, block: array<string, true>}  syntax names registered by the consumer */
	private array $consumerSyntaxes = ['line' => [], 'block' => []];

	private bool $inModulePrepare = false;


	public function __construct()
	{
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
		$this->addModule($this->autolinkModule = new Modules\AutolinkModule($this));
		$this->addModule($this->emoticonModule = new Modules\EmoticonModule($this));

		// block parsing
		$this->addModule($this->paragraphModule = new Modules\ParagraphModule($this));
		$this->addModule(new Modules\BlockModule($this));
		$this->addModule($this->figureModule = new Modules\FigureModule($this));
		$this->addModule($this->horizLineModule = new Modules\HorizontalRuleModule($this));
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

		$this->linePatterns[$name] = [
			'handler' => $handler,
			'pattern' => $pattern,
		];
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
		$this->htmlPolicy->resetCache();
		$document = $this->parse($text, $singleLine);
		$this->transform($document);
		return (new Output\Html\Renderer($this->htmlOutput, $this))->render($document);
	}


	/**
	 * Parses text to the syntax tree: every node records where it is written, and
	 * nothing of its meaning is applied yet (see transform()). The text is preprocessed
	 * first (idempotent), so node ranges always refer to the preprocessed form
	 * obtainable via preprocess().
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

		unset($this->activeLinePatterns, $this->activeBlockPatterns);
		$context = $this->createParseContext();
		$content = $singleLine
			? $context->parseInline($text)
			: $context->parseBlock($text);

		$document = new Nodes\DocumentNode($content);
		$this->runPasses($document, Stage::Syntax);
		return $document;
	}


	/**
	 * Turns the syntax tree into the document: resolves references, balances
	 * headings and applies the consumer's passes.
	 * Changes the tree in place; a document is transformed only once.
	 */
	public function transform(Nodes\DocumentNode $document): void
	{
		if (empty($document->meta['transformed'])) {
			$document->meta['transformed'] = true;
			$this->runPasses($document, Stage::Transform);
			$this->runPasses($document, Stage::Finalize);
		}
	}


	private function runPasses(Nodes\DocumentNode $document, Stage $stage): void
	{
		foreach ($this->passes[$stage->name] ?? [] as $pass) {
			$pass($document);
		}
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
	 * Converts DOM structure to pure text.
	 */
	public function toText(): never
	{
		throw new \LogicException('Not implemented yet.');
	}


	/**
	 * Registers a pass: fn(Nodes\DocumentNode): void, called with the whole document.
	 * The AST may be freely modified there; return values are ignored. A Transform pass
	 * (the default) runs in transform(), a Syntax pass already in parse(), where it may
	 * only finish recognizing the source and keeps ranges exact.
	 *
	 * Passes of a stage run in registration order, so the ones registered by modules
	 * run first and a consumer cannot get in front of them. Where that matters, call
	 * parse(), do the work, and then transform().
	 * @param  callable(Nodes\DocumentNode): void  $pass
	 */
	final public function addHandler(callable $pass, Stage $stage = Stage::Transform): void
	{
		$this->passes[$stage->name][] = $pass(...);
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
		return new ParseContext(
			$this->createInlineParser(),
			$this->createBlockParser(),
		);
	}


	private function createBlockParser(): BlockParser
	{
		$this->activeBlockPatterns ??= array_filter(
			$this->blockPatterns,
			fn($pattern, $name) => !empty($this->allowed[$name]),
			ARRAY_FILTER_USE_BOTH,
		);
		return new BlockParser(
			$this->activeBlockPatterns,
			fn(ParseContext $context, string $text, int $offset) => $this->paragraphModule->parseText($context, $text, $offset),
		);
	}


	private function createInlineParser(): InlineParser
	{
		$this->activeLinePatterns ??= array_filter(
			$this->linePatterns,
			fn($pattern, $name) => !empty($this->allowed[$name]),
			ARRAY_FILTER_USE_BOTH,
		);
		return new InlineParser($this->activeLinePatterns);
	}


	final public function __clone()
	{
		throw new \LogicException('Clone is not supported.');
	}
}


class_exists(\Texy::class);
