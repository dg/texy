<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Parsing;

use JetBrains\PhpStorm\Language;
use Texy\Nodes;


/**
 * Where a language declares its syntaxes.
 *
 * A name is enabled unless declared otherwise; the state given first holds, so registering
 * again before every parse keeps it, and a name stays valid when its pattern is gone.
 */
interface Registry
{
	/**
	 * @param  callable  $handler  fn(ParseContext, array $matches, array $offsets, string $name): Nodes\InlineNode|Nodes\ContentNode|null
	 * @throws \LogicException  when the name belongs to a block syntax
	 */
	function addInline(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $handler,
	): void;

	/**
	 * @param  callable  $handler  fn(ParseContext, array $matches, array $offsets, string $name): Nodes\BlockNode|Nodes\ContentNode|null
	 * @throws \LogicException  when the name belongs to an inline syntax
	 */
	function addBlock(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $handler,
	): void;

	/** Declares a syntax name, also one without a pattern of its own. */
	function declare(string $name, bool $enabled = true): void;

	/** @param  callable(ParseContext, string, int): list<Nodes\BlockNode>  $handler */
	function setGapHandler(callable $handler): void;
}
