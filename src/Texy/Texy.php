<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use JetBrains\PhpStorm\Language;
use Texy\Output\Html;
use Texy\Parsing\{BlockParser, InlineParser, ParseContext};
use function count, is_array, strlen;


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

	/** @deprecated */
	public bool $removeSoftHyphens = true;
	public string|Html\Element $nontextParagraph = 'div';

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

	/** HTML output configuration */
	public Output\Html\Config $htmlOutput;

	/** @var Module[] */
	private array $modules;

	/**
	 * Registered regexps and associated handlers for inline parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<?string>, string): ?Nodes\InlineNode, pattern: string}>
	 */
	private array $linePatterns = [];

	/**
	 * Inline patterns the configuration allows, cached for parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<?string>, string): ?Nodes\InlineNode, pattern: string}>
	 */
	private array $activeLinePatterns;

	/**
	 * Registered regexps and associated handlers for block parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<?string>, string): ?Nodes\BlockNode, pattern: string}>
	 */
	private array $blockPatterns = [];

	/**
	 * Block patterns the configuration allows, cached for parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<?string>, string): ?Nodes\BlockNode, pattern: string}>
	 */
	private array $activeBlockPatterns;

	/** @var array<string, list<\Closure(Nodes\DocumentNode): void>>  stage name => passes in registration order */
	private array $passes = [];

	/** @var array{line: array<string, true>, block: array<string, true>}  syntax names registered by the consumer */
	private array $consumerSyntaxes = ['line' => [], 'block' => []];

	private bool $inModulePrepare = false;

	/** @var array<string, \Closure(string): string> */
	private array $postHandlers = [];

	/** @var array<string, string>  Texy protect markup table */
	private array $marks = [];

	/** @var array<string, int>|bool  for internal usage */
	private bool|array $_classes;

	/** @var array<string, int>|bool  for internal usage */
	private bool|array $_styles;


	public function __construct()
	{
		$this->htmlOutput = new Output\Html\Config;
		$this->loadModules();
		$this->initAllowedTags();
	}


	private function initAllowedTags(): void
	{
		// accept all valid HTML tags and attributes by default
		$this->allowedTags = Html\Schema::defaultAllowedTags();
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


	/** @param  \Closure(ParseContext, array<?string>, string): ?Nodes\InlineNode  $handler */
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


	/** @param  \Closure(ParseContext, array<?string>, string): ?Nodes\BlockNode  $handler */
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
		$this->marks = [];
		unset($this->_classes, $this->_styles);
		$document = $this->parse($text, $singleLine);
		$this->transform($document);
		return (new Output\Html\Renderer($this->htmlOutput, $this))->render($document);
	}


	/**
	 * Parses text to the syntax tree: nothing of its meaning is applied yet (see
	 * transform()). The text is preprocessed first (idempotent).
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
	 * Converts internal string representation to final HTML code.
	 */
	final public function stringToHtml(string $s): string
	{
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
		$wellFormer = new Output\Html\WellFormer($this->htmlOutput);
		$wellFormer->raw($s);
		return ltrim($wellFormer->finish(), "\n");
	}


	/**
	 * Converts internal string representation to final HTML code.
	 */
	final public function stringToText(string $s): string
	{
		$s = $this->stringToHtml($s);

		// remove tags
		$s = Regexp::replace($s, '~<(script|style)(.*)</\1>~Uis');
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
			fn(ParseContext $context, string $text) => $this->paragraphModule->parseText($context, $text),
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
