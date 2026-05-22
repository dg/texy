<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use JetBrains\PhpStorm\Language;
use Texy\Parsing\{Engine, ParseContext};


/**
 * Texy! - Convert plain text to HTML format using {@link toHtml()}.
 *
 * <code>
 * $texy = new Texy();
 * $html = $texy->toHtml($text);
 * </code>
 */
class Texy
{
	// Texy version
	public const Version = '4.0.0-dev';

	#[\Deprecated('use Texy::Version', since: '4.0')]
	public const VERSION = self::Version;

	#[\Deprecated('use true', since: '4.0')]
	public const ALL = true;

	#[\Deprecated('use false', since: '4.0')]
	public const NONE = false;

	/** @var array<string, bool>  syntax name => enabled */
	public array $allowedSyntax = [];

	/** what the document means */
	public readonly TexyConfig $config;

	/** how its HTML looks */
	public readonly Output\Html\Config $htmlOutput;

	/** HTML security policy: allowed tags, classes and inline styles */
	public readonly HtmlPolicy $htmlPolicy;

	/** URL scheme security policy for links and images */
	public readonly UrlPolicy $urlPolicy;

	private Engine $engine;

	/** @var list<Module>  in the order that decides ties between syntaxes */
	private array $modules;


	public function __construct()
	{
		$this->engine = new Engine('Texy');
		$this->config = new TexyConfig;
		$this->urlPolicy = new UrlPolicy;
		$this->htmlPolicy = new HtmlPolicy($this->urlPolicy);
		$this->htmlOutput = new Output\Html\Config;
		$this->createModules();
		$this->registerModules();
	}


	private function createModules(): void
	{
		$directive = new Modules\DirectiveModule($this->config);
		$image = new Modules\ImageModule($this->config, $this->urlPolicy);
		$link = new Modules\LinkReferenceModule($this->config, $image);
		$heading = new Modules\HeadingModule($this->config);

		$this->modules = [
			// inline
			$directive,
			new Modules\HtmlModule,
			$image,
			new Modules\PhraseModule($this->config),
			$link,
			new Modules\AutolinkModule,
			new Modules\EmoticonModule($this->config),

			// block
			new Modules\ParagraphModule($this->config, $this->htmlPolicy),
			new Modules\BlockModule($this->config),
			new Modules\HorizontalRuleModule,
			new Modules\BlockQuoteModule,
			new Modules\TableModule,
			$heading,
			new Modules\ListModule($this->config),
		];

		$this->engine->addPass($link->recognizeReferences(...), Stage::Syntax);

		$this->engine->addPass($directive->processDirectives(...)); // first, a handler may change the configuration
		$this->engine->addPass($image->resolveReferences(...));
		$this->engine->addPass($link->resolveReferences(...));
		$this->engine->addPass($heading->processHeadings(...));

		Passes\DocumentPasses::register($this->engine, $this->config, $this->htmlPolicy);
	}


	/** Lets modules register their patterns, which depend on the current configuration. */
	private function registerModules(): void
	{
		$this->config->validate();
		foreach ($this->modules as $module) {
			$module->register($this->engine);
		}

		$this->allowedSyntax = $this->engine->completeAllowed($this->allowedSyntax);
	}


	/**
	 * @param  callable  $parser  fn(ParseContext, array $matches, array $offsets, string $name): Nodes\InlineNode|Nodes\ContentNode|null
	 */
	final public function addInlineSyntax(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $parser,
	): void
	{
		$this->engine->addInline($name, $pattern, $parser, application: true);
		$this->allowedSyntax[$name] ??= true;
	}


	/**
	 * @param  callable  $parser  fn(ParseContext, array $matches, array $offsets, string $name): Nodes\BlockNode|Nodes\ContentNode|null
	 */
	final public function addBlockSyntax(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $parser,
	): void
	{
		$this->engine->addBlock($name, $pattern, $parser, application: true);
		$this->allowedSyntax[$name] ??= true;
	}


	/**
	 * Converts Texy text to HTML.
	 */
	public function toHtml(string $text): string
	{
		return $this->renderHtml($this->transform($this->parse($text)));
	}


	/**
	 * Converts one line of Texy text to HTML: inline markup only, no paragraph around it.
	 */
	public function toInlineHtml(string $text): string
	{
		return $this->renderHtml($this->transform($this->parseInline($text)));
	}


	/**
	 * Renders a transformed document to HTML. Separate from parse() and transform()
	 * so a consumer can read or change the AST in between.
	 */
	public function renderHtml(Nodes\DocumentNode $document): string
	{
		return $this->createHtmlRenderer()->render($document);
	}


	/**
	 * Creates an HTML renderer wired to the current configuration. The renderer
	 * needs no live Texy instance and is reusable across documents.
	 */
	public function createHtmlRenderer(): Output\Html\Renderer
	{
		return new Output\Html\Renderer($this->htmlOutput, $this->htmlPolicy, $this->urlPolicy);
	}


	/**
	 * Parses text to the syntax tree: every node records where it is written, and
	 * nothing of its meaning is applied yet (see transform()). The text is normalized
	 * first (idempotent), so node ranges are byte offsets in normalize($text).
	 */
	public function parse(string $text): Nodes\DocumentNode
	{
		$this->registerModules();
		return $this->engine->parse(self::normalize($text), $this->allowedSyntax);
	}


	/**
	 * Parses one line of text to the syntax tree: inline syntaxes only, no blocks.
	 */
	public function parseInline(string $text): Nodes\DocumentNode
	{
		$this->registerModules();
		return $this->engine->parse(self::normalize($text), $this->allowedSyntax, singleLine: true);
	}


	/**
	 * Turns the syntax tree into the document: resolves references, balances
	 * headings, sanitizes HTML, applies typography and the consumer's passes.
	 * Changes the tree in place and returns it; a second call for one document throws.
	 */
	public function transform(Nodes\DocumentNode $document): Nodes\DocumentNode
	{
		$this->config->validate();
		$this->engine->transform($document);
		return $document;
	}


	/**
	 * Normalizes text to the canonical form that parsing operates on: removes the leading
	 * byte order mark, converts line endings to \n, strips control characters
	 * and soft hyphens, right-trims lines and drops blank lines at both ends, keeping the newline
	 * that ends the last line, so a file stays a file. Idempotent,
	 * so it is safe to apply before storing text and again before parsing.
	 * @throws InvalidInputException when the input is not valid UTF-8
	 */
	public static function normalize(string $text): string
	{
		if (!preg_match('##u', $text)) {
			throw new InvalidInputException('Input is not valid UTF-8 text.');
		}

		// standardize line endings to unix-like
		$text = str_replace("\r\n", "\n", $text);
		$text = strtr($text, "\r", "\n");

		// remove control characters; leave \t + \n
		$text = Regexp::replace($text, '~[\x00-\x08\x0B-\x1F]+~');

		// remove soft hyphens, which get in by copy-pasting hyphenated text
		$text = str_replace("\u{AD}", '', $text);

		// right trim line ends
		$text = Regexp::replace($text, "~[\t ]+$~m");

		// drop blank lines at both ends and byte order marks at the start;
		// a mark hidden behind blank lines would surface after the trim otherwise
		$text = Regexp::replace($text, '~^[\n\x{FEFF}]+~u');
		$trimmed = rtrim($text, "\n");
		return $trimmed !== $text && $trimmed !== '' ? $trimmed . "\n" : $trimmed; // the last line keeps its newline
	}


	/**
	 * Makes only typographic corrections, honouring the configuration.
	 */
	public function applyTypography(string $text): string
	{
		$text = self::normalize($text);

		$this->config->validate();
		if ($this->config->typography) {
			$text = new Typography\Typographer($this->config)->correct($text, preserveSpaces: true);
		}

		if ($this->config->hyphenation) {
			$text = new Typography\CzechHyphenator($this->config)->hyphenate($text);
		}

		return $text;
	}


	/**
	 * Converts Texy text to a plain-text rendition.
	 */
	public function toText(string $text): string
	{
		$document = $this->parse($text);
		$this->transform($document);
		return $this->renderText($document);
	}


	/**
	 * Renders a transformed document to plain text.
	 */
	public function renderText(Nodes\DocumentNode $document): string
	{
		return (new Output\Text\Renderer)->render($document);
	}


	/**
	 * Registers a pass: fn(Nodes\DocumentNode): void, called with the whole document.
	 * The AST may be freely modified there; return values are ignored. A Transform pass
	 * (the default) runs in transform(), a Syntax pass already in parse(), where it may
	 * only finish recognizing the source and keeps ranges exact.
	 *
	 * Passes of a stage run in registration order, so the ones of the language
	 * run first and a consumer cannot get in front of them. Where that matters, call
	 * parse(), do the work, and then transform().
	 * @param  callable(Nodes\DocumentNode): void  $pass
	 */
	final public function addPass(callable $pass, Stage $stage = Stage::Transform): void
	{
		$this->engine->addPass($pass(...), $stage);
	}


	/**
	 * @internal
	 */
	public function createParseContext(): ParseContext
	{
		return $this->engine->createParseContext($this->allowedSyntax);
	}


	final public function __clone()
	{
		throw new \LogicException('Clone is not supported.');
	}
}
