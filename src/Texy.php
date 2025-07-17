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
 * Texy! - Convert plain text to HTML format using {@link toHtml()}.
 *
 * <code>
 * $texy = new Texy();
 * $html = $texy->toHtml($text);
 * </code>
 */
class Texy implements Parsing\Registry
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

	/**
	 * Registered regexps and associated handlers for inline parsing, of the language and of the application.
	 * @var array{language: array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\InlineNode, pattern: string}>, application: array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\InlineNode, pattern: string}>}
	 */
	private array $inlinePatterns = ['language' => [], 'application' => []];

	/**
	 * Registered regexps and associated handlers for block parsing, of the language and of the application.
	 * @var array{language: array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\BlockNode, pattern: string}>, application: array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\BlockNode, pattern: string}>}
	 */
	private array $blockPatterns = ['language' => [], 'application' => []];

	/** @var array<string, bool>  syntax name => enabled by default */
	private array $declared = [];

	/** @var array<string, bool>  name of a syntax with a pattern => is it a block one? */
	private array $kinds = [];

	/** @var \Closure(ParseContext, string, int): array<Nodes\BlockNode>  takes the text between block syntaxes */
	private \Closure $gapHandler;

	/** @var array<string, list<\Closure(Nodes\DocumentNode): void>>  stage name => passes in registration order */
	private array $passes = [];

	/** @var list<Module>  in the order that decides ties between syntaxes */
	private array $modules;

	/** @var array<string, \Closure(string): string> */
	private array $postHandlers = [];


	public function __construct()
	{
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

		$this->addHandler($link->recognizeReferences(...), Stage::Syntax);

		$this->addHandler($directive->processDirectives(...));
		$this->addHandler($image->resolveReferences(...));
		$this->addHandler($link->resolveReferences(...));
		$this->addHandler($heading->processHeadings(...));
	}


	/** Lets modules register their patterns, which depend on the current configuration. */
	private function registerModules(): void
	{
		$this->config->validate();
		foreach ($this->modules as $module) {
			$module->register($this);
		}

		$this->allowedSyntax = $this->completeAllowed($this->allowedSyntax);
	}


	public function addInline(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $handler,
	): void
	{
		$this->introduce($name, block: false);
		$this->inlinePatterns['language'][$name] = ['handler' => $handler(...), 'pattern' => $pattern];
	}


	public function addBlock(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $handler,
	): void
	{
		$this->introduce($name, block: true);
		$this->blockPatterns['language'][$name] = ['handler' => $handler(...), 'pattern' => $pattern . 'm'];
	}


	public function declare(string $name, bool $enabled = true): void
	{
		$this->declared[$name] ??= $enabled;
	}


	public function setGapHandler(callable $handler): void
	{
		$this->gapHandler = $handler(...);
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
		$this->introduce($name, block: false);
		$this->inlinePatterns['application'][$name] = ['handler' => $handler(...), 'pattern' => $pattern];
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
		$this->introduce($name, block: true);
		$this->blockPatterns['application'][$name] = ['handler' => $handler(...), 'pattern' => $pattern . 'm'];
		$this->allowedSyntax[$name] ??= true;
	}


	/** @param  callable(string): string  $handler */
	final public function registerPostLine(callable $handler, string $name): void
	{
		$this->postHandlers[$name] = $handler(...);
		$this->allowedSyntax[$name] ??= true;
	}


	private function introduce(string $name, bool $block): void
	{
		if (($this->kinds[$name] ?? $block) !== $block) {
			throw new \LogicException("Syntax '$name' cannot be both inline and block.");
		}

		$this->kinds[$name] = $block;
		$this->declare($name);
	}


	/**
	 * Completes the map of enabled syntaxes with the default state of the names it lacks.
	 * @param  array<string, bool>  $allowedSyntaxes
	 * @return array<string, bool>
	 * @throws InvalidArgumentException  when the map holds a name nobody declared
	 */
	private function completeAllowed(array $allowedSyntaxes): array
	{
		foreach (array_diff_key($allowedSyntaxes, $this->declared) as $name => $_) {
			$suggestion = $this->suggestName((string) $name);
			throw new InvalidArgumentException("Unknown syntax '$name'" . ($suggestion === null ? '.' : ", did you mean '$suggestion'?"));
		}

		return $allowedSyntaxes + $this->declared;
	}


	private function suggestName(string $name): ?string
	{
		$best = null;
		$min = (strlen($name) / 4 + 1) * 10 + .1;
		foreach ($this->declared as $declared => $_) {
			$distance = levenshtein((string) $declared, $name, 10, 11, 10);
			if ($distance < $min) {
				[$min, $best] = [$distance, (string) $declared];
			}
		}

		return $best;
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
		$typography = $this->config->typography ? new Modules\TypographyModule($this->config)->postLine(...) : null;
		$hyphenation = $this->config->hyphenation ? new Modules\HyphenationModule($this->config)->postLine(...) : null;
		$postHandlers = array_filter(
			$this->postHandlers,
			fn(string $name): bool => !empty($this->allowedSyntax[$name]),
			ARRAY_FILTER_USE_KEY,
		);
		return new Output\Html\Renderer(
			$this->htmlOutput,
			$this->htmlPolicy,
			$this->urlPolicy,
			$typography,
			[...array_filter([$typography, $hyphenation]), ...array_values($postHandlers)],
		);
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
		$this->config->validate();
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

		$this->config->validate();
		$text = new Modules\TypographyModule($this->config)->postLine($text, preserveSpaces: true);

		if ($this->config->hyphenation) {
			$text = new Modules\HyphenationModule($this->config)->postLine($text);
		}

		return $text;
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
		$this->passes[$stage->name][] = $pass(...);
	}


	/**
	 * @internal
	 */
	public function createParseContext(): ParseContext
	{
		return new ParseContext(
			$this->createInlineParser(),
			$this->createBlockParser(),
			$this->allowedSyntax,
		);
	}


	private function createBlockParser(): BlockParser
	{
		$patterns = array_filter(
			$this->blockPatterns['application'] + $this->blockPatterns['language'],
			fn($_, $name) => !empty($this->allowedSyntax[$name]),
			ARRAY_FILTER_USE_BOTH,
		);

		return new BlockParser($patterns, $this->gapHandler);
	}


	private function createInlineParser(): InlineParser
	{
		$patterns = array_filter(
			$this->inlinePatterns['application'] + $this->inlinePatterns['language'],
			fn($_, $name) => !empty($this->allowedSyntax[$name]),
			ARRAY_FILTER_USE_BOTH,
		);

		return new InlineParser($patterns);
	}


	final public function __clone()
	{
		throw new \LogicException('Clone is not supported.');
	}
}
