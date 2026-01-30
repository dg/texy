<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use JetBrains\PhpStorm\Language;


/**
 * Minimal parser engine - handles pattern registration and parsing.
 *
 * This class is designed to be configured once and reused for multiple parse operations.
 * It contains NO security logic (checkURL, allowedClasses, etc.) and NO lifecycle management.
 * Those responsibilities belong to the Texy class which contains this engine.
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
	 * Post-line processing handlers.
	 * @var array<string, \Closure(string): string>
	 */
	private array $postHandlers = [];

	/**
	 * Gap handler for content between block patterns.
	 * @var \Closure(ParseContext, string, int): array<Nodes\BlockNode>
	 */
	private \Closure $gapHandler;


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
	 * Register a post-line processing handler.
	 *
	 * @param  \Closure(string): string  $handler
	 */
	public function registerPostLine(\Closure $handler, string $name): void
	{
		$this->postHandlers[$name] = $handler;
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
	 * Get all registered pattern names.
	 *
	 * @return list<string>
	 */
	public function getPatternNames(): array
	{
		return array_keys(array_merge($this->linePatterns, $this->blockPatterns, $this->postHandlers));
	}


	/**
	 * Get post-line handlers.
	 *
	 * @return array<string, \Closure(string): string>
	 */
	public function getPostHandlers(): array
	{
		return $this->postHandlers;
	}


	/**
	 * Parse text into AST.
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

		return new Nodes\DocumentNode($content);
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
