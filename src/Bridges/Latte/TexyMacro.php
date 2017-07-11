<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Bridges\Latte;

use Latte;
use Texy\Texy;


/**
 * Macro {texy} ... {/texy}
 */
class TexyMacro extends Latte\Macros\MacroSet
{
	/** @var Texy */
	private $texy;


	public function __construct(Latte\Engine $engine, Texy $texy)
	{
		$this->texy = $texy;
		parent::__construct($engine->getCompiler());
	}


	public function install()
	{
		$this->addMacro('texy', [$this, 'texyOpened'], [$this, 'texyClosed']);
	}


	public function texyOpened(Latte\MacroNode $node)
	{
		if ($node->modifiers) {
			throw new Latte\CompileException('Modifiers are not allowed in ' . $node->getNotation());
		}
	}


	public function texyClosed(Latte\MacroNode $node)
	{
		$text = $node->content;
		if (preg_match('#^([\t ]*)\S#m', $text, $m)) { // remove & restore indentation
			$text = str_replace(["\r", "\n" . $m[1]], ['', "\n"], $text);
			$this->texy->htmlOutputModule->baseIndent = strlen($m[1]);
		}

		$restore = [];
		$tokens = $node->tokenizer;
		while ($tokens->isNext()) {
			$module = $tokens->expectNextValue($tokens::T_SYMBOL) . 'Module';
			$tokens->expectNextValue('.');
			$prop = $tokens->expectNextValue($tokens::T_SYMBOL);
			$tokens->expectNextValue('=');
			$value = $tokens->expectNextValue($tokens::T_SYMBOL, $tokens::T_NUMBER);
			if ($tokens->isNext()) {
				$tokens->expectNextValue(',');
			}
			$restore[] = [&$this->texy->$module->$prop, $this->texy->$module->$prop];
			$this->texy->$module->$prop = $value;
		}

		$node->content = $this->texy->process($text);

		foreach ($restore as $info) {
			$info[0] = $info[1];
		}
	}
}
