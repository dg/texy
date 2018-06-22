<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\BlockParser;
use Texy\HtmlElement;
use Texy\Modifier;
use Texy\Patterns;


/**
 * Ordered / unordered nested list module.
 */
final class ListModule extends Texy\Module
{
	public $bullets = [
		// first-rexexp ordered? list-style-type next-regexp
		'*' => ['\*\ ', 0, ''],
		'-' => ['[\x{2013}-](?![>-])', 0, ''],
		'+' => ['\+\ ', 0, ''],
		'1.' => ['1\.\ ', /* not \d !*/ 1, '', '\d{1,3}\.\ '],
		'1)' => ['\d{1,3}\)\ ', 1, ''],
		'I.' => ['I\.\ ', 1, 'upper-roman', '[IVX]{1,4}\.\ '],
		'I)' => ['[IVX]+\)\ ', 1, 'upper-roman'], // before A) !
		'a)' => ['[a-z]\)\ ', 1, 'lower-alpha'],
		'A)' => ['[A-Z]\)\ ', 1, 'upper-alpha'],
	];


	public function __construct($texy)
	{
		$this->texy = $texy;

		$texy->addHandler('beforeParse', [$this, 'beforeParse']);
		$texy->allowed['list'] = true;
		$texy->allowed['list/definition'] = true;
	}


	public function beforeParse()
	{
		$RE = $REul = [];
		foreach ($this->bullets as $desc) {
			$RE[] = $desc[0];
			if (!$desc[1]) {
				$REul[] = $desc[0];
			}
		}

		$this->texy->registerBlockPattern(
			[$this, 'patternList'],
			'#^(?:' . Patterns::MODIFIER_H . '\n)?' // .{color: red}
			. '(' . implode('|', $RE) . ')\ *+\S.*$#mUu', // item (unmatched)
			'list'
		);

		$this->texy->registerBlockPattern(
			[$this, 'patternDefList'],
			'#^(?:' . Patterns::MODIFIER_H . '\n)?' // .{color:red}
			. '(\S.{0,2000})\:\ *' . Patterns::MODIFIER_H . '?\n' // Term:
			. '(\ ++)(' . implode('|', $REul) . ')\ *+\S.*$#mUu', // - description
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
	 * @return HtmlElement|false
	 */
	public function patternList(BlockParser $parser, array $matches)
	{
		list(, $mMod, $mBullet) = $matches;
		// [1] => .(title)[class]{style}<>
		// [2] => bullet * + - 1) a) A) IV)

		$el = new HtmlElement;

		$bullet = $min = null;
		foreach ($this->bullets as $type => $desc) {
			if (preg_match('#' . $desc[0] . '#Au', $mBullet)) {
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

		$mod = new Modifier($mMod);
		$mod->decorate($this->texy, $el);

		$parser->moveBackward(1);

		while ($elItem = $this->patternItem($parser, $bullet, false, 'li')) {
			$el->add($elItem);
		}

		if ($el->count() < $min) {
			return false;
		}

		// event listener
		$this->texy->invokeHandlers('afterList', [$parser, $el, $mod]);

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
	 * @return HtmlElement
	 */
	public function patternDefList(BlockParser $parser, array $matches)
	{
		list(, $mMod, , , , $mBullet) = $matches;
		// [1] => .(title)[class]{style}<>
		// [2] => ...
		// [3] => .(title)[class]{style}<>
		// [4] => space
		// [5] => - * +

		$texy = $this->texy;

		$bullet = null;
		foreach ($this->bullets as $desc) {
			if (preg_match('#' . $desc[0] . '#Au', $mBullet)) {
				$bullet = isset($desc[3]) ? $desc[3] : $desc[0];
				break;
			}
		}

		$el = new HtmlElement('dl');
		$mod = new Modifier($mMod);
		$mod->decorate($texy, $el);
		$parser->moveBackward(2);

		$patternTerm = '#^\n?(\S.*)\:\ *' . Patterns::MODIFIER_H . '?()$#mUA';

		while (true) {
			if ($elItem = $this->patternItem($parser, $bullet, true, 'dd')) {
				$el->add($elItem);
				continue;
			}

			if ($parser->next($patternTerm, $matches)) {
				list(, $mContent, $mMod) = $matches;
				// [1] => ...
				// [2] => .(title)[class]{style}<>

				$elItem = new HtmlElement('dt');
				$modItem = new Modifier($mMod);
				$modItem->decorate($texy, $elItem);

				$elItem->parseLine($texy, $mContent);
				$el->add($elItem);
				continue;
			}

			break;
		}

		// event listener
		$texy->invokeHandlers('afterDefinitionList', [$parser, $el, $mod]);

		return $el;
	}


	/**
	 * Callback for single list item.
	 * @return HtmlElement|false
	 */
	public function patternItem(BlockParser $parser, $bullet, $indented, $tag)
	{
		$spacesBase = $indented ? ('\ {1,}') : '';
		$patternItem = "#^\n?($spacesBase)$bullet\\ *(\\S.*)?" . Patterns::MODIFIER_H . '?()$#mAUu';

		// first line with bullet
		$matches = null;
		if (!$parser->next($patternItem, $matches)) {
			return false;
		}

		list(, $mIndent, $mContent, $mMod) = $matches;
			// [1] => indent
			// [2] => ...
			// [3] => .(title)[class]{style}<>

		$elItem = new HtmlElement($tag);
		$mod = new Modifier($mMod);
		$mod->decorate($this->texy, $elItem);

		// next lines
		$spaces = '';
		$content = ' ' . $mContent; // trick
		while ($parser->next('#^(\n*)' . $mIndent . '(\ {1,' . $spaces . '})(.*)()$#Am', $matches)) {
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
		$elItem->parseBlock($this->texy, $content, true);

		if (isset($elItem[0]) && $elItem[0] instanceof HtmlElement) {
			$elItem[0]->setName(null);
		}

		return $elItem;
	}
}
