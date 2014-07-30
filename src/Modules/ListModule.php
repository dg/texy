<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * Ordered / unordered nested list module.
 *
 * @author     David Grudl
 */
final class ListModule extends Texy\Module
{
	public $bullets = array(
		// first-rexexp ordered? list-style-type next-regexp
		'*' => array('\*\ ', 0, ''),
		'-' => array('[\x{2013}-](?![>-])',0, ''),
		'+' => array('\+\ ', 0, ''),
		'1.' => array('1\.\ ',/* not \d !*/ 1, '', '\d{1,3}\.\ '),
		'1)' => array('\d{1,3}\)\ ', 1, ''),
		'I.' => array('I\.\ ', 1, 'upper-roman', '[IVX]{1,4}\.\ '),
		'I)' => array('[IVX]+\)\ ', 1, 'upper-roman'), // before A) !
		'a)' => array('[a-z]\)\ ', 1, 'lower-alpha'),
		'A)' => array('[A-Z]\)\ ', 1, 'upper-alpha'),
	);


	public function __construct($texy)
	{
		$this->texy = $texy;

		$texy->addHandler('beforeParse', array($this, 'beforeParse'));
		$texy->allowed['list'] = TRUE;
		$texy->allowed['list/definition'] = TRUE;
	}


	public function beforeParse()
	{
		$RE = $REul = array();
		foreach ($this->bullets as $desc) {
			$RE[] = $desc[0];
			if (!$desc[1]) {
				$REul[] = $desc[0];
			}
		}

		$this->texy->registerBlockPattern(
			array($this, 'patternList'),
			'#^(?:'.Texy\Patterns::MODIFIER_H.'\n)?' // .{color: red}
			. '('.implode('|', $RE).')\ *+\S.*$#mUu', // item (unmatched)
			'list'
		);

		$this->texy->registerBlockPattern(
			array($this, 'patternDefList'),
			'#^(?:'.Texy\Patterns::MODIFIER_H.'\n)?' // .{color:red}
			. '(\S.{0,2000})\:\ *'.Texy\Patterns::MODIFIER_H.'?\n' // Term:
			. '(\ ++)('.implode('|', $REul).')\ *+\S.*$#mUu', // - description
			'list/definition'
		);
	}


	/**
	 * Callback for:.
	 *
	 * 1) .... .(title)[class]{style}>
	 * 2) ....
	 *   + ...
	 *   + ...
	 * 3) ....
	 *
	 * @param  Texy\BlockParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return Texy\HtmlElement|FALSE
	 */
	public function patternList($parser, $matches)
	{
		list(, $mMod, $mBullet) = $matches;
		// [1] => .(title)[class]{style}<>
		// [2] => bullet * + - 1) a) A) IV)

		$tx = $this->texy;

		$el = Texy\HtmlElement::el();

		$bullet = $min = NULL;
		foreach ($this->bullets as $type => $desc) {
			if (preg_match('#'.$desc[0].'#Au', $mBullet)) {
				$bullet = isset($desc[3]) ? $desc[3] : $desc[0];
				$min = isset($desc[3]) ? 2 : 1;
				$el->setName($desc[1] ? 'ol' : 'ul');
				$el->attrs['style']['list-style-type'] = $desc[2];
				if ($desc[1]) { // ol
					if ($type[0] === '1' && (int) $mBullet > 1) {
						$el->attrs['start'] = (int) $mBullet;
					} elseif ($type[0] === 'a' && $mBullet[0] > 'a') {
						$el->attrs['start'] = ord($mBullet[0]) - 96;
					} elseif ($type[0] === 'A' && $mBullet[0] > 'A') {
						$el->attrs['start'] = ord($mBullet[0]) - 64;
					}
				}
				break;
			}
		}

		$mod = new Texy\Modifier($mMod);
		$mod->decorate($tx, $el);

		$parser->moveBackward(1);

		while ($elItem = $this->patternItem($parser, $bullet, FALSE, 'li')) {
			$el->add($elItem);
		}

		if ($el->count() < $min) {
			return FALSE;
		}

		// event listener
		$tx->invokeHandlers('afterList', array($parser, $el, $mod));

		return $el;
	}


	/**
	 * Callback for:.
	 *
	 * Term: .(title)[class]{style}>
	 * - description 1
	 * - description 2
	 * - description 3
	 *
	 * @param  Texy\BlockParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return Texy\HtmlElement
	 */
	public function patternDefList($parser, $matches)
	{
		list(, $mMod, , , , $mBullet) = $matches;
		// [1] => .(title)[class]{style}<>
		// [2] => ...
		// [3] => .(title)[class]{style}<>
		// [4] => space
		// [5] => - * +

		$tx = $this->texy;

		$bullet = NULL;
		foreach ($this->bullets as $desc) {
			if (preg_match('#'.$desc[0].'#Au', $mBullet)) {
				$bullet = isset($desc[3]) ? $desc[3] : $desc[0];
				break;
			}
		}

		$el = Texy\HtmlElement::el('dl');
		$mod = new Texy\Modifier($mMod);
		$mod->decorate($tx, $el);
		$parser->moveBackward(2);

		$patternTerm = '#^\n?(\S.*)\:\ *'.Texy\Patterns::MODIFIER_H.'?()$#mUA';

		while (TRUE) {
			if ($elItem = $this->patternItem($parser, $bullet, TRUE, 'dd')) {
				$el->add($elItem);
				continue;
			}

			if ($parser->next($patternTerm, $matches)) {
				list(, $mContent, $mMod) = $matches;
				// [1] => ...
				// [2] => .(title)[class]{style}<>

				$elItem = Texy\HtmlElement::el('dt');
				$modItem = new Texy\Modifier($mMod);
				$modItem->decorate($tx, $elItem);

				$elItem->parseLine($tx, $mContent);
				$el->add($elItem);
				continue;
			}

			break;
		}

		// event listener
		$tx->invokeHandlers('afterDefinitionList', array($parser, $el, $mod));

		return $el;
	}


	/**
	 * Callback for single list item.
	 *
	 * @param  Texy\BlockParser
	 * @param  string  bullet type
	 * @param  string  left space
	 * @param  string  html tag
	 * @return Texy\HtmlElement|FALSE
	 */
	public function patternItem($parser, $bullet, $indented, $tag)
	{
		$tx = $this->texy;
		$spacesBase = $indented ? ('\ {1,}') : '';
		$patternItem = "#^\n?($spacesBase)$bullet\\ *(\\S.*)?".Texy\Patterns::MODIFIER_H."?()$#mAUu";

		// first line with bullet
		$matches = NULL;
		if (!$parser->next($patternItem, $matches)) {
			return FALSE;
		}

		list(, $mIndent, $mContent, $mMod) = $matches;
			// [1] => indent
			// [2] => ...
			// [3] => .(title)[class]{style}<>

		$elItem = Texy\HtmlElement::el($tag);
		$mod = new Texy\Modifier($mMod);
		$mod->decorate($tx, $elItem);

		// next lines
		$spaces = '';
		$content = ' ' . $mContent; // trick
		while ($parser->next('#^(\n*)'.$mIndent.'(\ {1,'.$spaces.'})(.*)()$#Am', $matches)) {
			list(, $mBlank, $mSpaces, $mContent) = $matches;
			// [1] => blank line?
			// [2] => spaces
			// [3] => ...

			if ($spaces === '') {
				$spaces = strlen($mSpaces);
			}
			$content .= "\n" . $mBlank . $mContent;
		}

		// parse content
		$elItem->parseBlock($tx, $content, TRUE);

		if (isset($elItem[0]) && $elItem[0] instanceof Texy\HtmlElement) {
			$elItem[0]->setName(NULL);
		}

		return $elItem;
	}

}
