<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Bridges\Latte;

use Latte;
use Texy\Texy;


/**
 * Macro {texy} ... {/texy} for Latte v2
 */
class TexyMacro extends Latte\Macros\MacroSet
{
	private Texy $texy;


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
			$module = $tokens->consumeValue($tokens::T_SYMBOL) . 'Module';
			$tokens->consumeValue('.');
			$prop = $tokens->consumeValue($tokens::T_SYMBOL);
			$tokens->consumeValue('=');
			$value = $tokens->consumeValue($tokens::T_SYMBOL, $tokens::T_NUMBER);
			if ($tokens->isNext()) {
				$tokens->consumeValue(',');
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
