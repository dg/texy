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

	/** @var bool|array<string, bool|array<int, string>>  allowed HTML tags and their attributes */
	public bool|array $allowedTags;

	/** @var bool|array<int, string>  allowed classes and IDs */
	public bool|array $allowedClasses = self::ALL;

	/** @var bool|array<int, string>  allowed inline CSS styles */
	public bool|array $allowedStyles = self::ALL;

	public Output\Html\Config $htmlOutput;

	/** TAB width (for converting tabs to spaces) */
	public int $tabWidth = 8;

	/** join consecutive lines into one paragraph? false makes every newline a line break */
	public bool $mergeLines = true;

	/** Do obfuscate e-mail addresses? */
	public bool $obfuscateEmail = true;

	/** @var array<string, ?string>  classes in place of style="text-align" and "vertical-align", null keeps the style */
	public array $alignClasses = [
		'left' => null,
		'right' => null,
		'center' => null,
		'justify' => null,
		'top' => null,
		'middle' => null,
		'bottom' => null,
	];

	/** element in place of <p> for a paragraph holding images only */
	public string $nontextParagraph = 'div';

	/** @deprecated */
	public bool $removeSoftHyphens = true;

	/** @var array<string, string>  regexps checking URL schemes, keyed by FILTER_ANCHOR and FILTER_IMAGE */
	public array $urlSchemeFilters = [];

	public Modules\HtmlModule $htmlModule;
	public Modules\ImageModule $imageModule;
	public Modules\LinkReferenceModule $linkModule;
	public Modules\AutolinkModule $autolinkModule;
	public Modules\PhraseModule $phraseModule;
	public Modules\EmoticonModule $emoticonModule;
	public Modules\FigureModule $figureModule;
	public Modules\HorizontalRuleModule $horizLineModule;
	public Modules\HeadingModule $headingModule;
	public Modules\ListModule $listModule;
	public Modules\TypographyModule $typographyModule;
	public Modules\HyphenationModule $longWordsModule;

	/**
	 * Registered regexps and associated handlers for inline parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<?string>, string): ?Nodes\InlineNode, pattern: string}>
	 */
	private array $inlinePatterns = [];

	/**
	 * Registered regexps and associated handlers for block parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<?string>, string): ?Nodes\BlockNode, pattern: string}>
	 */
	private array $blockPatterns = [];

	/** @var array<string, true>  names of the syntaxes registered by the application */
	private array $applicationSyntaxes = [];

	/** @var \Closure(ParseContext, string): array<Nodes\BlockNode>  takes the text between block syntaxes */
	private \Closure $gapHandler;

	/** @var array<string, list<\Closure(Nodes\DocumentNode): void>>  stage name => passes in registration order */
	private array $passes = [];

	/** @var list<Module>  in the order that decides ties between syntaxes */
	private array $modules;

	/** @var array<string, \Closure(string): string> */
	private array $postHandlers = [];


	public function __construct()
	{
		$this->allowedTags = Output\Html\Schema::defaultAllowedTags(); // accept all valid HTML tags and attributes by default
		$this->htmlOutput = new Output\Html\Config;
		$this->createModules();
	}


	private function createModules(): void
	{
		$this->imageModule = new Modules\ImageModule($this);
		$this->linkModule = new Modules\LinkReferenceModule($this);
		$this->headingModule = new Modules\HeadingModule($this);

		$this->modules = [
			// inline
			new Modules\DirectiveModule($this),
			$this->htmlModule = new Modules\HtmlModule($this),
			$this->imageModule,
			$this->phraseModule = new Modules\PhraseModule($this),
			$this->linkModule,
			$this->autolinkModule = new Modules\AutolinkModule($this),
			$this->emoticonModule = new Modules\EmoticonModule($this),

			// block
			new Modules\ParagraphModule($this),
			new Modules\BlockModule($this),
			$this->figureModule = new Modules\FigureModule($this),
			$this->horizLineModule = new Modules\HorizontalRuleModule($this),
			new Modules\BlockQuoteModule($this),
			new Modules\TableModule($this),
			$this->headingModule,
			$this->listModule = new Modules\ListModule($this),
		];

		$this->typographyModule = new Modules\TypographyModule($this);
		$this->longWordsModule = new Modules\HyphenationModule($this);

		$this->addHandler($this->linkModule->recognizeReferences(...), Stage::Syntax);

		$this->addHandler($this->imageModule->resolveReferences(...));
		$this->addHandler($this->linkModule->resolveReferences(...));
		$this->addHandler($this->headingModule->processHeadings(...));
	}


	/** Lets modules register their patterns, which depend on the current configuration. */
	private function registerModules(): void
	{
		foreach ($this->modules as $module) {
			$module->beforeParse();
		}
	}


	/**
	 * @param  callable  $handler  fn(ParseContext, array $matches, string $name): ?Nodes\InlineNode
	 */
	public function addInline(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $handler,
	): void
	{
		if (!isset($this->applicationSyntaxes[$name])) { // a syntax of the application is never replaced by a module
			$this->inlinePatterns[$name] = ['handler' => $handler(...), 'pattern' => $pattern];
		}

		$this->declare($name);
	}


	/**
	 * @param  callable  $handler  fn(ParseContext, array $matches, string $name): ?Nodes\BlockNode
	 */
	public function addBlock(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $handler,
	): void
	{
		if (!isset($this->applicationSyntaxes[$name])) { // a syntax of the application is never replaced by a module
			$this->blockPatterns[$name] = ['handler' => $handler(...), 'pattern' => $pattern . 'm'];
		}

		$this->declare($name);
	}


	/** Declares a syntax name, also one without a pattern of its own. */
	public function declare(string $name, bool $enabled = true): void
	{
		$this->allowed[$name] ??= $enabled;
	}


	/** @param  callable(ParseContext, string): array<Nodes\BlockNode>  $handler */
	public function setGapHandler(callable $handler): void
	{
		$this->gapHandler = $handler(...);
	}


	/**
	 * @param  callable  $handler  fn(ParseContext, array $matches, string $name): ?Nodes\InlineNode
	 */
	final public function registerLinePattern(
		callable $handler,
		#[Language('RegExp')]
		string $pattern,
		string $name,
	): void
	{
		$this->applicationSyntaxes[$name] = true;
		$this->inlinePatterns[$name] = ['handler' => $handler(...), 'pattern' => $pattern];
		$this->allowed[$name] ??= true;
	}


	/**
	 * @param  callable  $handler  fn(ParseContext, array $matches, string $name): ?Nodes\BlockNode
	 */
	final public function registerBlockPattern(
		callable $handler,
		#[Language('RegExp')]
		string $pattern,
		string $name,
	): void
	{
		$this->applicationSyntaxes[$name] = true;
		$this->blockPatterns[$name] = ['handler' => $handler(...), 'pattern' => $pattern . 'm'];
		$this->allowed[$name] ??= true;
	}


	/** @param  callable(string): string  $handler */
	final public function registerPostLine(callable $handler, string $name): void
	{
		$this->postHandlers[$name] = $handler(...);
		$this->allowed[$name] ??= true;
	}


	/**
	 * Converts Texy text to HTML.
	 */
	public function process(string $text): string
	{
		return $this->createHtmlRenderer()->render($this->transform($this->parse($text)));
	}


	/**
	 * Converts one line of Texy text to HTML: inline markup only, no paragraph around it.
	 */
	public function processLine(string $text): string
	{
		return $this->createHtmlRenderer()->render($this->transform($this->parseInline($text)));
	}


	/**
	 * Creates an HTML renderer wired to the current configuration.
	 */
	public function createHtmlRenderer(): Output\Html\Renderer
	{
		$typography = !empty($this->allowed[Syntax::Typography]) ? $this->typographyModule->postLine(...) : null;
		$hyphenation = !empty($this->allowed[Syntax::Hyphenation]) ? $this->longWordsModule->postLine(...) : null;
		$postHandlers = array_filter($this->postHandlers, fn(string $name): bool => !empty($this->allowed[$name]), ARRAY_FILTER_USE_KEY);
		return new Output\Html\Renderer(
			$this->htmlOutput,
			$this,
			$typography,
			[...array_filter([$typography, $hyphenation]), ...array_values($postHandlers)],
		);
	}


	/**
	 * Parses text to the syntax tree: nothing of its meaning is applied yet (see
	 * transform()). The text is preprocessed first (idempotent).
	 */
	public function parse(string $text): Nodes\DocumentNode
	{
		$this->registerModules();
		$document = new Nodes\DocumentNode($this->createParseContext()->parseBlock($this->preprocess($text)));
		$this->runPasses($document, Stage::Syntax);
		return $document;
	}


	/**
	 * Parses one line of text to the syntax tree: inline syntaxes only, no blocks.
	 */
	public function parseInline(string $text): Nodes\DocumentNode
	{
		$this->registerModules();
		$document = new Nodes\DocumentNode($this->createParseContext()->parseInline($this->preprocess($text)));
		$this->runPasses($document, Stage::Syntax);
		return $document;
	}


	/**
	 * Turns the syntax tree into the document: resolves references, balances
	 * headings and applies the consumer's passes.
	 * Changes the tree in place and returns it; a second call for one document throws.
	 */
	public function transform(Nodes\DocumentNode $document): Nodes\DocumentNode
	{
		if ($document->transformState !== Nodes\TransformState::Pending) {
			throw new \LogicException("A document is transformed only once, this one is {$document->transformState->name}.");
		}

		$document->transformState = Nodes\TransformState::Running;
		try {
			$this->runPasses($document, Stage::Transform);
			$this->runPasses($document, Stage::Finalize);
		} catch (\Throwable $e) {
			$document->transformState = Nodes\TransformState::Failed;
			throw $e;
		}

		$document->transformState = Nodes\TransformState::Done;
		return $document;
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
					fn($m) => $m[1] . str_repeat(' ', $this->tabWidth - strlen($m[1] ?? '') % $this->tabWidth),
				);
			}
		}

		return $text;
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
	 * Registers a pass: fn(Nodes\DocumentNode): void, called with the whole document.
	 * The AST may be freely modified there; return values are ignored. A Transform pass
	 * (the default) runs in transform(), a Syntax pass already in parse(), where it may
	 * only finish recognizing the source.
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
	 * Filters bad URLs: a URL with a scheme must match the filter of its type; relative URLs always pass.
	 * @param  string  $type  Texy::FILTER_ANCHOR | Texy::FILTER_IMAGE
	 */
	final public function checkURL(string $url, string $type): bool
	{
		$url = str_replace(["\t", "\n", "\r"], '', $url); // a browser drops them from a URL before reading its scheme
		return empty($this->urlSchemeFilters[$type])
			|| !Regexp::match($url, '~\s*[a-z][a-z0-9+.-]{0,20}:~Ai') // no scheme (relative URL)
			|| (bool) Regexp::match($url, $this->urlSchemeFilters[$type]);
	}


	/**
	 * Creates parse context. Modules must be initialized via parse() first
	 * for patterns to be available.
	 * @internal
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
		$patterns = array_filter(
			$this->blockPatterns,
			fn($_, $name) => !empty($this->allowed[$name]),
			ARRAY_FILTER_USE_BOTH,
		);

		return new BlockParser($patterns, $this->gapHandler);
	}


	private function createInlineParser(): InlineParser
	{
		$patterns = array_filter(
			$this->inlinePatterns,
			fn($_, $name) => !empty($this->allowed[$name]),
			ARRAY_FILTER_USE_BOTH,
		);

		return new InlineParser($patterns);
	}


	final public function __clone()
	{
		throw new \LogicException('Clone is not supported.');
	}
}
