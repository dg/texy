<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use JetBrains\PhpStorm\Language;
use Texy\Parsing\{Engine, ParseContext};
use Texy\Passes\TextRunPass;


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

	/** @var array<string, bool>  Texy! syntax configuration */
	public array $allowed = [];

	/** URL scheme security policy for links and images */
	public readonly UrlPolicy $urlPolicy;

	/** Paragraph merging mode */
	public bool $mergeLines = true;

	public Modules\ParagraphModule $paragraphModule;
	public Modules\ImageModule $imageModule;
	public Modules\LinkReferenceModule $linkModule;
	public Modules\PhraseModule $phraseModule;
	public Modules\EmoticonModule $emoticonModule;
	public Modules\HeadingModule $headingModule;
	public Modules\ListModule $listModule;
	public Modules\TypographyModule $typographyModule;
	public Modules\HyphenationModule $hyphenationModule;

	/** HTML security policy: allowed tags, classes and inline styles */
	public readonly HtmlPolicy $htmlPolicy;

	public Output\Html\Config $htmlOutput;

	public Modules\DirectiveModule $directiveModule;

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

	/** @var array<string, Compat\LegacyModuleProxy>  v3 compatibility */
	private array $legacyProxies = [];


	public function __construct()
	{
		$this->engine = new Engine;
		$this->urlPolicy = new UrlPolicy;
		$this->htmlPolicy = new HtmlPolicy($this->urlPolicy);
		$this->htmlOutput = new Output\Html\Config;
		$this->loadModules();
		$this->engine->addPass($this->applyAstTypography(...), Stage::Finalize);
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
		$this->addModule($this->directiveModule = new Modules\DirectiveModule($this));
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
		$this->addModule($this->hyphenationModule = new Modules\HyphenationModule($this));
	}


	public function addModule(Module $module): void
	{
		$this->modules[] = $module;
	}


	/**
	 * @return Module[]
	 * @internal
	 */
	public function getModules(): array
	{
		return $this->modules;
	}


	/** @param  callable  $parser  fn(ParseContext, array $matches, array $offsets, string $name): ?Nodes\InlineNode */
	final public function addLineSyntax(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $parser,
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

		$this->engine->addLineSyntax($name, $pattern, $parser(...));
	}


	/** @param  callable  $parser  fn(ParseContext, array $matches, array $offsets, string $name): ?Nodes\BlockNode */
	final public function addBlockSyntax(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $parser,
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

		$this->engine->addBlockSyntax($name, $pattern, $parser(...));
	}


	/**
	 * Converts Texy text to HTML.
	 */
	public function process(string $text, bool $singleLine = false): string
	{
		$document = $this->parse($text, $singleLine);
		$this->transform($document);
		return $this->renderHtml($document);
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
		return new Output\Html\Renderer(
			$this->htmlOutput,
			$this->htmlPolicy,
			$this->urlPolicy,
			!empty($this->allowed[Syntax::Typography]) ? $this->typographyModule->postLine(...) : null,
		);
	}


	/**
	 * Parses text to the syntax tree: every node records where it is written, and
	 * nothing of its meaning is applied yet (see transform()). The text is normalized
	 * first (idempotent), so node ranges are byte offsets in normalize($text).
	 */
	public function parse(string $text, bool $singleLine = false): Nodes\DocumentNode
	{
		$text = self::normalize($text);

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
		return $this->engine->parse($text, $this->allowed, $singleLine);
	}


	/**
	 * Turns the syntax tree into the document: resolves references, balances
	 * headings, sanitizes HTML, applies typography and the consumer's passes.
	 * Changes the tree in place; a document is transformed only once.
	 */
	public function transform(Nodes\DocumentNode $document): void
	{
		$this->engine->transform($document);
	}


	/**
	 * Runs typography/hyphenation over the AST, honouring the allowed[] configuration,
	 * and marks the document so generators skip their string post-processing.
	 *
	 * transform() does this itself as its last step. It is public for the other case: a
	 * document built by a different parser, which is still an ordinary Texy AST and
	 * still wants the typography. Calling it on an already typographed document does
	 * nothing.
	 */
	public function applyAstTypography(Nodes\DocumentNode $document): void
	{
		if (!empty($document->meta['typographed'])) {
			return;
		}

		$transformers = [];
		if (!empty($this->allowed[Syntax::Typography])) {
			$transformers[] = fn(string $s): string => $this->typographyModule->postLine($s);
		}
		if (!empty($this->allowed[Syntax::Hyphenation])) {
			$transformers[] = fn(string $s): string => $this->hyphenationModule->postLine($s);
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
	 * Normalizes text to the canonical form that parsing operates on: removes the leading
	 * byte order mark, converts line endings to \n, strips control characters,
	 * right-trims lines and drops blank lines at both ends. Idempotent,
	 * so it is safe to apply before storing text and again before parsing.
	 * @throws InvalidArgumentException when the input is not valid UTF-8
	 */
	public static function normalize(string $text): string
	{
		if (!preg_match('##u', $text)) {
			throw new InvalidArgumentException('Input is not valid UTF-8 text.');
		}

		// standardize line endings to unix-like
		$text = str_replace("\r\n", "\n", $text);
		$text = strtr($text, "\r", "\n");

		// remove control characters; leave \t + \n
		$text = Regexp::replace($text, '~[\x00-\x08\x0B-\x1F]+~');

		// right trim line ends
		$text = Regexp::replace($text, "~[\t ]+$~m");

		// drop blank lines at both ends and byte order marks at the start;
		// a mark hidden behind blank lines would surface after the trim otherwise
		$text = Regexp::replace($text, '~^[\n\x{FEFF}]+~u');
		return rtrim($text, "\n");
	}


	/**
	 * Makes only typographic corrections, honouring the allowed[] configuration.
	 */
	public function applyTypography(string $text): string
	{
		$text = self::normalize($text);

		if (!empty($this->allowed[Syntax::Typography])) {
			$text = $this->typographyModule->postLine($text, preserveSpaces: true);
		}

		if (!empty($this->allowed[Syntax::Hyphenation])) {
			$text = $this->hyphenationModule->postLine($text);
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
	 * Passes of a stage run in registration order, so the ones registered by modules
	 * run first and a consumer cannot get in front of them. Where that matters, call
	 * parse(), do the work, and then transform().
	 * @param  callable(Nodes\DocumentNode): void  $pass
	 */
	final public function addPass(callable $pass, Stage $stage = Stage::Transform): void
	{
		$this->engine->addPass($pass(...), $stage);
	}


	/**
	 * Creates parse context. Modules must be initialized via parse() first
	 * for patterns to be available.
	 * @internal
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
		if (isset(Compat\Legacy::RenamedModules[$name])) {
			$new = Compat\Legacy::RenamedModules[$name];
			trigger_error("Property \$texy->$name is deprecated, use \$texy->$new instead.", E_USER_DEPRECATED);
			return $this->$new;
		}

		if (isset(Compat\Legacy::OfModule[$name])) {
			$proxy = $this->legacyProxies[$name] ??= new Compat\LegacyModuleProxy($this, $name);
			return $proxy;
		}

		return Compat\Legacy::ref($this, Compat\Legacy::OfTexy, '$texy', $name, 'read');
	}


	public function __set(string $name, mixed $value): void
	{
		if (isset(Compat\Legacy::RenamedModules[$name])) {
			$new = Compat\Legacy::RenamedModules[$name];
			trigger_error("Property \$texy->$name is deprecated, use \$texy->$new instead.", E_USER_DEPRECATED);
			$this->$new = $value;
			return;
		}

		Compat\Legacy::set($this, Compat\Legacy::OfTexy, '$texy', $name, $value);
	}


	public function __isset(string $name): bool
	{
		return isset(Compat\Legacy::RenamedModules[$name])
			|| isset(Compat\Legacy::OfModule[$name])
			|| Compat\Legacy::isSet($this, Compat\Legacy::OfTexy, $name);
	}


	/**
	 * @deprecated v3 compatibility: emulates renamed methods, navigates away from removed ones
	 * @param  array<mixed>  $args
	 */
	public function __call(string $name, array $args): mixed
	{
		return Compat\Legacy::call($this, $name, $args);
	}
}


class_exists(\Texy::class);
