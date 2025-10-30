<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use JetBrains\PhpStorm\Language;
use Texy\Parsing\{Engine, ParseContext};
use function strlen;


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
	public const VERSION = '4.0.0-dev';

	// configuration directives
	public const
		ALL = true,
		NONE = false;

	/** @var array<string, bool>  syntax name => enabled */
	public array $allowedSyntax = [];

	/** what the document means */
	public readonly TexyConfig $config;

	/** how its HTML looks */
	public readonly Output\Html\Config $htmlOutput;

	/** HTML security policy: allowed tags, classes and inline styles */
	public readonly HtmlPolicy $htmlPolicy;

	/** TAB width (for converting tabs to spaces) */
	public int $tabWidth = 8;

	/** @deprecated */
	public bool $removeSoftHyphens = true;

	/** URL scheme security policy for links and images */
	public readonly UrlPolicy $urlPolicy;

	private Engine $engine;

	/** @var list<Module>  in the order that decides ties between syntaxes */
	private array $modules;


	public function __construct()
	{
		$this->engine = new Engine;
		$this->config = new TexyConfig;
		$this->urlPolicy = new UrlPolicy;
		$this->htmlPolicy = new HtmlPolicy($this->urlPolicy);
		$this->htmlOutput = new Output\Html\Config;
		$this->createModules();
		$this->registerModules();
	}


	private function createModules(): void
	{
		$directive = new Modules\DirectiveModule;
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
			new Modules\BlockModule,
			new Modules\FigureModule($image),
			new Modules\HorizontalRuleModule,
			new Modules\BlockQuoteModule,
			new Modules\TableModule,
			$heading,
			new Modules\ListModule($this->config),
		];

		$this->engine->addPass($link->recognizeReferences(...), Stage::Syntax);

		$this->engine->addPass($directive->processDirectives(...));
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
	 * @param  callable  $handler  fn(ParseContext, array $matches, array $offsets, string $name): ?Nodes\InlineNode
	 */
	final public function registerLinePattern(
		callable $handler,
		#[Language('RegExp')]
		string $pattern,
		string $name,
	): void
	{
		$this->engine->addInlineSyntax($name, $pattern, $handler(...));
		$this->allowedSyntax[$name] ??= true;
	}


	/**
	 * @param  callable  $handler  fn(ParseContext, array $matches, array $offsets, string $name): ?Nodes\BlockNode
	 */
	final public function registerBlockPattern(
		callable $handler,
		#[Language('RegExp')]
		string $pattern,
		string $name,
	): void
	{
		$this->engine->addBlockSyntax($name, $pattern, $handler(...));
		$this->allowedSyntax[$name] ??= true;
	}


	/**
	 * Converts Texy text to HTML.
	 */
	public function toHtml(string $text): string
	{
		return $this->createHtmlRenderer()->render($this->transform($this->parse($text)));
	}


	/**
	 * Converts one line of Texy text to HTML: inline markup only, no paragraph around it.
	 */
	public function toInlineHtml(string $text): string
	{
		return $this->createHtmlRenderer()->render($this->transform($this->parseInline($text)));
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
	 * nothing of its meaning is applied yet (see transform()). The text is preprocessed
	 * first (idempotent), so node ranges always refer to the preprocessed form
	 * obtainable via preprocess().
	 */
	public function parse(string $text): Nodes\DocumentNode
	{
		$this->registerModules();
		return $this->engine->parse($this->preprocess($text), $this->allowedSyntax);
	}


	/**
	 * Parses one line of text to the syntax tree: inline syntaxes only, no blocks.
	 */
	public function parseInline(string $text): Nodes\DocumentNode
	{
		$this->registerModules();
		return $this->engine->parse($this->preprocess($text), $this->allowedSyntax, singleLine: true);
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

		$this->config->validate();
		$text = new Typography\Typographer($this->config)->correct($text, preserveSpaces: true);

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
	final public function addHandler(callable $pass, Stage $stage = Stage::Transform): void
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
