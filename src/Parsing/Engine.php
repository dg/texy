<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Parsing;

use JetBrains\PhpStorm\Language;
use Texy\{InvalidArgumentException, Nodes, Regexp, Stage};
use function strlen;


/**
 * Minimal parser engine - handles pattern registration and parsing.
 *
 * The pattern maps are a cumulative registry: re-registration under an existing
 * name replaces the handler and pattern but keeps the original position, and
 * nothing is ever removed. There are two of them, one for the syntaxes of the language
 * and one for those of the application, which precede wherever the order of
 * registration decides. The map of enabled syntaxes is applied per parse when the
 * parse context is created. The class contains NO security logic (UrlPolicy,
 * allowedClasses, etc.), which belongs to the language using this engine.
 *
 * Input contract: valid UTF-8, LF line endings, no \r (it is the internal
 * hard-break marker). Plain text between matches becomes TextNode holding the
 * source text verbatim; decoding (e.g. HTML entities) is a language concern
 * expressed as a registered syntax, never engine mechanics.
 *
 * Usage:
 *   $engine = new Engine();
 *   $engine->addInlineSyntax('syntax/name', $pattern, $parser);
 *   $engine->addBlockSyntax('syntax/name', $pattern, $parser);
 *   $engine->setGapHandler($handler);
 *   $doc = $engine->parse($text, ['syntax/name' => true]);
 */
class Engine implements Registry
{
	private const MissingGapHandler = 'What becomes of the text between block syntaxes is up to the language, call setGapHandler().';

	/**
	 * Registered regexps and associated handlers for inline parsing, of the language and of the application.
	 * @var array{language: array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): (Nodes\InlineNode|Nodes\ContentNode|null), pattern: string}>, application: array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): (Nodes\InlineNode|Nodes\ContentNode|null), pattern: string}>}
	 */
	private array $inlinePatterns = ['language' => [], 'application' => []];

	/**
	 * Registered regexps and associated handlers for block parsing, of the language and of the application.
	 * @var array{language: array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): (Nodes\BlockNode|Nodes\ContentNode|null), pattern: string}>, application: array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): (Nodes\BlockNode|Nodes\ContentNode|null), pattern: string}>}
	 */
	private array $blockPatterns = ['language' => [], 'application' => []];

	/** @var array<string, bool>  syntax name => enabled by default */
	private array $declared = [];

	/** @var array<string, bool>  name of a syntax with a pattern => is it a block one? */
	private array $kinds = [];

	/**
	 * Gap handler for content between block patterns.
	 * @var ?\Closure(ParseContext, string, int): list<Nodes\BlockNode>
	 */
	private ?\Closure $gapHandler = null;

	/** @var array<string, list<\Closure(Nodes\DocumentNode): void>>  stage name => passes in registration order */
	private array $passes = [];


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


	/**
	 * Registers an inline syntax of the application, replacing a syntax of the language of that name.
	 * @param  \Closure(ParseContext, array<?string>, array<?int>, string): (Nodes\InlineNode|Nodes\ContentNode|null)  $parser
	 * @throws \LogicException  when the name belongs to a block syntax
	 */
	public function addInlineSyntax(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		\Closure $parser,
	): void
	{
		$this->introduce($name, block: false);
		$this->inlinePatterns['application'][$name] = ['handler' => $parser, 'pattern' => $pattern];
	}


	/**
	 * Registers a block syntax of the application, replacing a syntax of the language of that name.
	 * @param  \Closure(ParseContext, array<?string>, array<?int>, string): (Nodes\BlockNode|Nodes\ContentNode|null)  $parser
	 * @throws \LogicException  when the name belongs to an inline syntax
	 */
	public function addBlockSyntax(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		\Closure $parser,
	): void
	{
		$this->introduce($name, block: true);
		$this->blockPatterns['application'][$name] = ['handler' => $parser, 'pattern' => $pattern . 'm'];
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
	public function completeAllowed(array $allowedSyntaxes): array
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


	public function setGapHandler(callable $handler): void
	{
		$this->gapHandler = $handler(...);
	}


	/**
	 * Registers a pass: fn(Nodes\DocumentNode): void. Passes of a stage run in registration order.
	 * @param  \Closure(Nodes\DocumentNode): void  $pass
	 */
	public function addPass(\Closure $pass, Stage $stage = Stage::Transform): void
	{
		$this->passes[$stage->name][] = $pass;
	}


	/**
	 * Parse text into AST, including the Syntax passes.
	 *
	 * @param  array<string, bool>  $allowedSyntaxes  Map of syntax name => enabled
	 * @param  bool  $singleLine  Parse as single line (inline only)
	 * @param  ?\Closure(string, int): Nodes\ContentNode  $inlineDeferral  see ParseContext
	 * @throws \LogicException  when there is no gap handler to take the text of blocks
	 */
	public function parse(
		string $text,
		array $allowedSyntaxes,
		bool $singleLine = false,
		?\Closure $inlineDeferral = null,
	): Nodes\DocumentNode
	{
		if (!$singleLine && $this->gapHandler === null) {
			throw new \LogicException(self::MissingGapHandler);
		}

		$context = $this->createParseContext($allowedSyntaxes, $inlineDeferral);
		$content = $singleLine
			? $context->parseInline($text)
			: $context->parseBlock($text);

		$document = new Nodes\DocumentNode($content);
		$this->runPasses($document, Stage::Syntax);
		return $document;
	}


	/**
	 * Does the pattern of an allowed block syntax match the text? Its handler could still refuse it.
	 * @param  array<string, bool>  $allowedSyntaxes  Map of syntax name => enabled
	 */
	public function matchesBlockSyntax(string $text, array $allowedSyntaxes): bool
	{
		return array_any(
			$this->blockPatterns['application'] + $this->blockPatterns['language'],
			fn($pattern, $name) => !empty($allowedSyntaxes[$name]) && Regexp::match($text, $pattern['pattern']),
		);
	}


	/**
	 * Runs the Transform and Finalize passes over a parsed document, changing it in place.
	 * A document goes through this once, whichever instance or language asks for it, and a document
	 * whose transformation has failed has to be parsed again.
	 * @throws \LogicException
	 */
	public function transform(Nodes\DocumentNode $document): void
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
	}


	private function runPasses(Nodes\DocumentNode $document, Stage $stage): void
	{
		foreach ($this->passes[$stage->name] ?? [] as $pass) {
			$pass($document);
		}
	}


	/**
	 * Create parse context with filtered patterns.
	 *
	 * @param  array<string, bool>  $allowedSyntaxes
	 * @param  ?\Closure(string, int): Nodes\ContentNode  $inlineDeferral  see ParseContext
	 */
	public function createParseContext(array $allowedSyntaxes, ?\Closure $inlineDeferral = null): ParseContext
	{
		return new ParseContext(
			$this->createInlineParser($allowedSyntaxes),
			$this->createBlockParser($allowedSyntaxes),
			$allowedSyntaxes,
			$inlineDeferral,
		);
	}


	/**
	 * Create block parser with filtered patterns.
	 *
	 * @param  array<string, bool>  $allowedSyntaxes
	 */
	private function createBlockParser(array $allowedSyntaxes): BlockParser
	{
		$filteredPatterns = array_filter(
			$this->blockPatterns['application'] + $this->blockPatterns['language'],
			fn($_, $name) => !empty($allowedSyntaxes[$name]),
			ARRAY_FILTER_USE_BOTH,
		);

		return new BlockParser($filteredPatterns, $this->gapHandler ?? fn() => throw new \LogicException(self::MissingGapHandler));
	}


	/**
	 * Create inline parser with filtered patterns.
	 *
	 * @param  array<string, bool>  $allowedSyntaxes
	 */
	private function createInlineParser(array $allowedSyntaxes): InlineParser
	{
		$filteredPatterns = array_filter(
			$this->inlinePatterns['application'] + $this->inlinePatterns['language'],
			fn($_, $name) => !empty($allowedSyntaxes[$name]),
			ARRAY_FILTER_USE_BOTH,
		);

		return new InlineParser($filteredPatterns);
	}
}
