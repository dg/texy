<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Parsing;

use JetBrains\PhpStorm\Language;
use Texy\{Nodes, Stage};


/**
 * Minimal parser engine - handles pattern registration and parsing.
 *
 * The pattern maps are a cumulative registry: re-registration under an existing
 * name replaces the handler and pattern but keeps the original position, and
 * nothing is ever removed. The $allowed filter is applied per parse when the
 * parse context is created. The class contains NO security logic (UrlPolicy,
 * allowedClasses, etc.) and NO lifecycle management; those responsibilities
 * belong to the Texy class which contains this engine.
 *
 * Plain text between matches becomes TextNode holding the source text
 * verbatim; decoding (e.g. HTML entities) is a language concern expressed as
 * a registered syntax, never engine mechanics.
 *
 * Usage:
 *   $engine = new Engine();
 *   $engine->registerLinePattern($handler, $pattern, 'syntax/name');
 *   $engine->registerBlockPattern($handler, $pattern, 'syntax/name');
 *   $doc = $engine->parse($text, ['syntax/name' => true]);
 */
class Engine
{
	/**
	 * Registered regexps and associated handlers for inline parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\InlineNode, pattern: string}>
	 */
	private array $linePatterns = [];

	/**
	 * Registered regexps and associated handlers for block parsing.
	 * @var array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\BlockNode, pattern: string}>
	 */
	private array $blockPatterns = [];

	/**
	 * Gap handler for content between block patterns.
	 * @var \Closure(ParseContext, string, int): array<Nodes\BlockNode>
	 */
	private \Closure $gapHandler;

	/** @var array<string, list<\Closure(Nodes\DocumentNode): void>>  stage name => passes in registration order */
	private array $passes = [];


	public function __construct()
	{
		// Default gap handler - creates empty array (no paragraphs by default)
		$this->gapHandler = fn(ParseContext $context, string $text, int $offset) => [];
	}


	/**
	 * Register an inline pattern handler.
	 *
	 * @param  \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\InlineNode  $handler
	 */
	public function registerLinePattern(
		\Closure $handler,
		#[Language('RegExp')]
		string $pattern,
		string $name,
	): void
	{
		$this->linePatterns[$name] = [
			'handler' => $handler,
			'pattern' => $pattern,
		];
	}


	/**
	 * Register a block pattern handler.
	 *
	 * @param  \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\BlockNode  $handler
	 */
	public function registerBlockPattern(
		\Closure $handler,
		#[Language('RegExp')]
		string $pattern,
		string $name,
	): void
	{
		$this->blockPatterns[$name] = [
			'handler' => $handler,
			'pattern' => $pattern . 'm', // force multiline
		];
	}


	/**
	 * Set custom gap handler for content between block patterns.
	 *
	 * @param  \Closure(ParseContext, string, int): array<Nodes\BlockNode>  $handler
	 */
	public function setGapHandler(\Closure $handler): void
	{
		$this->gapHandler = $handler;
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
	 */
	public function parse(string $text, array $allowedSyntaxes, bool $singleLine = false): Nodes\DocumentNode
	{
		$context = $this->createParseContext($allowedSyntaxes);
		$content = $singleLine
			? $context->parseInline($text)
			: $context->parseBlock($text);

		$document = new Nodes\DocumentNode($content);
		$this->runPasses($document, Stage::Syntax);
		return $document;
	}


	/**
	 * Runs the Transform and Finalize passes over a parsed document, changing it in place.
	 * A document is transformed once; further calls do nothing.
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
	 * Create parse context with filtered patterns.
	 *
	 * @param  array<string, bool>  $allowedSyntaxes
	 */
	public function createParseContext(array $allowedSyntaxes): ParseContext
	{
		return new ParseContext(
			$this->createInlineParser($allowedSyntaxes),
			$this->createBlockParser($allowedSyntaxes),
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
			$this->blockPatterns,
			fn($_, $name) => !empty($allowedSyntaxes[$name]),
			ARRAY_FILTER_USE_BOTH,
		);

		return new BlockParser($filteredPatterns, $this->gapHandler);
	}


	/**
	 * Create inline parser with filtered patterns.
	 *
	 * @param  array<string, bool>  $allowedSyntaxes
	 */
	private function createInlineParser(array $allowedSyntaxes): InlineParser
	{
		$filteredPatterns = array_filter(
			$this->linePatterns,
			fn($_, $name) => !empty($allowedSyntaxes[$name]),
			ARRAY_FILTER_USE_BOTH,
		);

		return new InlineParser($filteredPatterns);
	}
}
