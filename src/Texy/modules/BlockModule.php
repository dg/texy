<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * Special blocks module.
 *
 * @author     David Grudl
 */
final class BlockModule extends Texy\Module
{

	public function __construct($texy)
	{
		$this->texy = $texy;

		//$texy->allowed['blocks'] = TRUE;
		$texy->allowed['block/default'] = TRUE;
		$texy->allowed['block/pre'] = TRUE;
		$texy->allowed['block/code'] = TRUE;
		$texy->allowed['block/html'] = TRUE;
		$texy->allowed['block/text'] = TRUE;
		$texy->allowed['block/texysource'] = TRUE;
		$texy->allowed['block/comment'] = TRUE;
		$texy->allowed['block/div'] = TRUE;

		$texy->addHandler('block', array($this, 'solve'));
		$texy->addHandler('beforeBlockParse', array($this, 'beforeBlockParse'));

		$texy->registerBlockPattern(
			array($this, 'pattern'),
			'#^/--++ *+(.*)'.Texy\Patterns::MODIFIER_H.'?$((?:\n(?0)|\n.*+)*)(?:\n\\\\--.*$|\z)#mUi',
			'blocks'
		);
	}


	/**
	 * Single block pre-processing.
	 * @param  Texy\BlockParser
	 * @param  string
	 * @return void
	 */
	public function beforeBlockParse($parser, & $text)
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
	 * @param  Texy\BlockParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return Texy\HtmlElement|string|FALSE
	 */
	public function pattern($parser, $matches)
	{
		list(, $mParam, $mMod, $mContent) = $matches;
		// [1] => code | text | ...
		// [2] => ... additional parameters
		// [3] => .(title)[class]{style}<>
		// [4] => ... content

		$mod = new Texy\Modifier($mMod);
		$parts = preg_split('#\s+#u', $mParam, 2);
		$blocktype = empty($parts[0]) ? 'block/default' : 'block/' . $parts[0];
		$param = empty($parts[1]) ? NULL : $parts[1];

		return $this->texy->invokeAroundHandlers('block', $parser, array($blocktype, $mContent, $param, $mod));
	}


	/**
	 * Finish invocation.
	 *
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string   blocktype
	 * @param  string   content
	 * @param  string   additional parameter
	 * @param  Texy\Modifier
	 * @return Texy\HtmlElement|string|FALSE
	 */
	public function solve($invocation, $blocktype, $s, $param, $mod)
	{
		$tx = $this->texy;
		$parser = $invocation->parser;

		if ($blocktype === 'block/texy') {
			$el = Texy\HtmlElement::el();
			$el->parseBlock($tx, $s, $parser->isIndented());
			return $el;
		}

		if (empty($tx->allowed[$blocktype])) {
			return FALSE;
		}

		if ($blocktype === 'block/texysource') {
			$s = Texy\Texy::outdent($s);
			if ($s === '') {
				return "\n";
			}
			$el = Texy\HtmlElement::el();
			if ($param === 'line') {
				$el->parseLine($tx, $s);
			} else {
				$el->parseBlock($tx, $s);
			}
			$s = $el->toHtml($tx);
			$blocktype = 'block/code'; $param = 'html'; // to be continue (as block/code)
		}

		if ($blocktype === 'block/code') {
			$s = Texy\Texy::outdent($s);
			if ($s === '') {
				return "\n";
			}
			$s = Texy\Texy::escapeHtml($s);
			$s = $tx->protect($s, Texy\Texy::CONTENT_BLOCK);
			$el = Texy\HtmlElement::el('pre');
			$mod->decorate($tx, $el);
			$el->attrs['class'][] = $param; // lang
			$el->create('code', $s);
			return $el;
		}

		if ($blocktype === 'block/default') {
			$s = Texy\Texy::outdent($s);
			if ($s === '') {
				return "\n";
			}
			$el = Texy\HtmlElement::el('pre');
			$mod->decorate($tx, $el);
			$el->attrs['class'][] = $param; // lang
			$s = Texy\Texy::escapeHtml($s);
			$s = $tx->protect($s, Texy\Texy::CONTENT_BLOCK);
			$el->setText($s);
			return $el;
		}

		if ($blocktype === 'block/pre') {
			$s = Texy\Texy::outdent($s);
			if ($s === '') {
				return "\n";
			}
			$el = Texy\HtmlElement::el('pre');
			$mod->decorate($tx, $el);
			$lineParser = new Texy\LineParser($tx, $el);
			// special mode - parse only html tags
			$tmp = $lineParser->patterns;
			$lineParser->patterns = array();
			if (isset($tmp['html/tag'])) {
				$lineParser->patterns['html/tag'] = $tmp['html/tag'];
			}
			if (isset($tmp['html/comment'])) {
				$lineParser->patterns['html/comment'] = $tmp['html/comment'];
			}
			unset($tmp);

			$lineParser->parse($s);
			$s = $el->getText();
			$s = Texy\Texy::unescapeHtml($s);
			$s = Texy\Texy::escapeHtml($s);
			$s = $tx->unprotect($s);
			$s = $tx->protect($s, Texy\Texy::CONTENT_BLOCK);
			$el->setText($s);
			return $el;
		}

		if ($blocktype === 'block/html') {
			$s = trim($s, "\n");
			if ($s === '') {
				return "\n";
			}
			$el = Texy\HtmlElement::el();
			$lineParser = new Texy\LineParser($tx, $el);
			// special mode - parse only html tags
			$tmp = $lineParser->patterns;
			$lineParser->patterns = array();
			if (isset($tmp['html/tag'])) {
				$lineParser->patterns['html/tag'] = $tmp['html/tag'];
			}
			if (isset($tmp['html/comment'])) {
				$lineParser->patterns['html/comment'] = $tmp['html/comment'];
			}
			unset($tmp);

			$lineParser->parse($s);
			$s = $el->getText();
			$s = Texy\Texy::unescapeHtml($s);
			$s = Texy\Texy::escapeHtml($s);
			$s = $tx->unprotect($s);
			return $tx->protect($s, Texy\Texy::CONTENT_BLOCK) . "\n";
		}

		if ($blocktype === 'block/text') {
			$s = trim($s, "\n");
			if ($s === '') {
				return "\n";
			}
			$s = Texy\Texy::escapeHtml($s);
			$s = str_replace("\n", Texy\HtmlElement::el('br')->startTag() , $s); // nl2br
			return $tx->protect($s, Texy\Texy::CONTENT_BLOCK) . "\n";
		}

		if ($blocktype === 'block/comment') {
			return "\n";
		}

		if ($blocktype === 'block/div') {
			$s = Texy\Texy::outdent($s);
			if ($s === '') {
				return "\n";
			}
			$el = Texy\HtmlElement::el('div');
			$mod->decorate($tx, $el);
			$el->parseBlock($tx, $s, $parser->isIndented()); // TODO: INDENT or NORMAL ?
			return $el;
		}

		return FALSE;
	}

}
