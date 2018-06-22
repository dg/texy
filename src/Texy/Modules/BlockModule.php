<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\HtmlElement;


/**
 * Special blocks module.
 */
final class BlockModule extends Texy\Module
{
	public function __construct($texy)
	{
		$this->texy = $texy;

		//$texy->allowed['blocks'] = true;
		$texy->allowed['block/default'] = true;
		$texy->allowed['block/pre'] = true;
		$texy->allowed['block/code'] = true;
		$texy->allowed['block/html'] = true;
		$texy->allowed['block/text'] = true;
		$texy->allowed['block/texysource'] = true;
		$texy->allowed['block/comment'] = true;
		$texy->allowed['block/div'] = true;

		$texy->addHandler('block', [$this, 'solve']);
		$texy->addHandler('beforeBlockParse', [$this, 'beforeBlockParse']);

		$texy->registerBlockPattern(
			[$this, 'pattern'],
			'#^/--++ *+(.*)' . Texy\Patterns::MODIFIER_H . '?$((?:\n(?0)|\n.*+)*)(?:\n\\\\--.*$|\z)#mUi',
			'blocks'
		);
	}


	/**
	 * Single block pre-processing.
	 * @return void
	 */
	public function beforeBlockParse(Texy\BlockParser $parser, &$text)
	{
		// autoclose exclusive blocks
		$text = Texy\Regexp::replace(
			$text,
			'#^(/--++ *+(?!div|texysource).*)$((?:\n.*+)*?)(?:\n\\\\--.*$|(?=(\n/--.*$)))#mi',
			"\$1\$2\n\\--"
		);
	}


	/**
	 * Callback for:.
	 * /-----code html .(title)[class]{style}
	 * ....
	 * ....
	 * \----
	 *
	 * @return HtmlElement|string|false
	 */
	public function pattern(Texy\BlockParser $parser, array $matches)
	{
		list(, $mParam, $mMod, $mContent) = $matches;
		// [1] => code | text | ...
		// [2] => ... additional parameters
		// [3] => .(title)[class]{style}<>
		// [4] => ... content

		$mod = new Texy\Modifier($mMod);
		$parts = preg_split('#\s+#u', $mParam, 2);
		$blocktype = empty($parts[0]) ? 'block/default' : 'block/' . $parts[0];
		$param = empty($parts[1]) ? null : $parts[1];

		return $this->texy->invokeAroundHandlers('block', $parser, [$blocktype, $mContent, $param, $mod]);
	}


	// for backward compatibility
	public function outdent($s)
	{
		trigger_error('Use Texy\Helpers::outdent()', E_USER_WARNING);
		return Helpers::outdent($s);
	}


	/**
	 * Finish invocation.
	 * @return HtmlElement|string|false
	 */
	public function solve(Texy\HandlerInvocation $invocation, $blocktype, $s, $param, Texy\Modifier $mod)
	{
		$texy = $this->texy;
		$parser = $invocation->getParser();

		if ($blocktype === 'block/texy') {
			$el = new HtmlElement;
			$el->parseBlock($texy, $s, $parser->isIndented());
			return $el;
		}

		if (empty($texy->allowed[$blocktype])) {
			return false;
		}

		if ($blocktype === 'block/texysource') {
			$s = Helpers::outdent($s);
			if ($s === '') {
				return "\n";
			}
			$el = new HtmlElement;
			if ($param === 'line') {
				$el->parseLine($texy, $s);
			} else {
				$el->parseBlock($texy, $s);
			}
			$s = $el->toHtml($texy);
			$blocktype = 'block/code';
			$param = 'html'; // to be continue (as block/code)
		}

		if ($blocktype === 'block/code') {
			$s = Helpers::outdent($s);
			if ($s === '') {
				return "\n";
			}
			$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
			$s = $texy->protect($s, $texy::CONTENT_BLOCK);
			$el = new HtmlElement('pre');
			$mod->decorate($texy, $el);
			$el->attrs['class'][] = $param; // lang
			$el->create('code', $s);
			return $el;
		}

		if ($blocktype === 'block/default') {
			$s = Helpers::outdent($s);
			if ($s === '') {
				return "\n";
			}
			$el = new HtmlElement('pre');
			$mod->decorate($texy, $el);
			$el->attrs['class'][] = $param; // lang
			$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
			$s = $texy->protect($s, $texy::CONTENT_BLOCK);
			$el->setText($s);
			return $el;
		}

		if ($blocktype === 'block/pre') {
			$s = Helpers::outdent($s);
			if ($s === '') {
				return "\n";
			}
			$el = new HtmlElement('pre');
			$mod->decorate($texy, $el);
			$lineParser = new Texy\LineParser($texy, $el);
			// special mode - parse only html tags
			$tmp = $lineParser->patterns;
			$lineParser->patterns = [];
			if (isset($tmp['html/tag'])) {
				$lineParser->patterns['html/tag'] = $tmp['html/tag'];
			}
			if (isset($tmp['html/comment'])) {
				$lineParser->patterns['html/comment'] = $tmp['html/comment'];
			}
			unset($tmp);

			$lineParser->parse($s);
			$s = $el->getText();
			$s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
			$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
			$s = $texy->unprotect($s);
			$s = $texy->protect($s, $texy::CONTENT_BLOCK);
			$el->setText($s);
			return $el;
		}

		if ($blocktype === 'block/html') {
			$s = trim($s, "\n");
			if ($s === '') {
				return "\n";
			}
			$el = new HtmlElement;
			$lineParser = new Texy\LineParser($texy, $el);
			// special mode - parse only html tags
			$tmp = $lineParser->patterns;
			$lineParser->patterns = [];
			if (isset($tmp['html/tag'])) {
				$lineParser->patterns['html/tag'] = $tmp['html/tag'];
			}
			if (isset($tmp['html/comment'])) {
				$lineParser->patterns['html/comment'] = $tmp['html/comment'];
			}
			unset($tmp);

			$lineParser->parse($s);
			$s = $el->getText();
			$s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
			$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
			$s = $texy->unprotect($s);
			return $texy->protect($s, $texy::CONTENT_BLOCK) . "\n";
		}

		if ($blocktype === 'block/text') {
			$s = trim($s, "\n");
			if ($s === '') {
				return "\n";
			}
			$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
			$s = str_replace("\n", (new HtmlElement('br'))->startTag(), $s); // nl2br
			return $texy->protect($s, $texy::CONTENT_BLOCK) . "\n";
		}

		if ($blocktype === 'block/comment') {
			return "\n";
		}

		if ($blocktype === 'block/div') {
			$s = Helpers::outdent($s, true);
			if ($s === '') {
				return "\n";
			}
			$el = new HtmlElement('div');
			$mod->decorate($texy, $el);
			$el->parseBlock($texy, $s, $parser->isIndented()); // TODO: INDENT or NORMAL ?
			return $el;
		}

		return false;
	}
}
