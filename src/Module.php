<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * A part of a language: the syntaxes of one topic. Used by the implementations of languages,
 * an application extends a language by registerLinePattern(), registerBlockPattern() and addHandler().
 */
abstract class Module
{
	/**
	 * Declares the syntaxes by addInline(), addBlock(), declare() and setGapHandler(), nothing else;
	 * what the module does over the whole document is a pass the facade adds.
	 */
	abstract public function register(Parsing\Engine $engine): void;
}
