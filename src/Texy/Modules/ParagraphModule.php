<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Regexp;


/**
 * Paragraph module.
 */
final class ParagraphModule extends Texy\Module
{
	public function __construct($texy)
	{
		$this->texy = $texy;
		$texy->addHandler('paragraph', [$this, 'solve']);
	}


	/**
	 * @return void
	 */
	public function process(Texy\BlockParser $parser, $content, Texy\HtmlElement $el)
	{
		if ($parser->isIndented()) {
			$parts = preg_split('#(\n(?! )|\n{2,})#', $content, -1, PREG_SPLIT_NO_EMPTY);
		} else {
			$parts = preg_split('#(\n{2,})#', $content, -1, PREG_SPLIT_NO_EMPTY);
		}

		foreach ($parts as $s) {
			$s = trim($s);
			if ($s === '') {
				continue;
			}

			// try to find modifier
			$mod = null;
			if ($mx = Regexp::match($s, '#' . Texy\Patterns::MODIFIER_H . '(?=\n|\z)#sUm', Regexp::OFFSET_CAPTURE)) {
				list($mMod) = $mx[1];
				$s = trim(substr_replace($s, '', $mx[0][1], strlen($mx[0][0])));
				if ($s === '') {
					continue;
				}
				$mod = new Texy\Modifier;
				$mod->setProperties($mMod);
			}

			$res = $this->texy->invokeAroundHandlers('paragraph', $parser, [$s, $mod]);
			if ($res) {
				$el->insert(null, $res);
			}
		}
	}


	/**
	 * Finish invocation.
	 * @return Texy\HtmlElement|false
	 */
	public function solve(Texy\HandlerInvocation $invocation, $content, Texy\Modifier $mod = null)
	{
		$texy = $this->texy;

		// find hard linebreaks
		if ($texy->mergeLines) {
			// ....
			// ... => \r means break line
			$content = Regexp::replace($content, '#\n +(?=\S)#', "\r");
		} else {
			$content = Regexp::replace($content, '#\n#', "\r");
		}

		$el = new Texy\HtmlElement('p');
		$el->parseLine($texy, $content);
		$content = $el->getText(); // string

		// check content type
		// block contains block tag
		if (strpos($content, $texy::CONTENT_BLOCK) !== false) {
			$el->setName(null); // ignores modifier!

		// block contains text (protected)
		} elseif (strpos($content, $texy::CONTENT_TEXTUAL) !== false) {
			// leave element p

		// block contains text
		} elseif (preg_match('#[^\s' . Texy\Patterns::MARK . ']#u', $content)) {
			// leave element p

		// block contains only replaced element
		} elseif (strpos($content, $texy::CONTENT_REPLACED) !== false) {
			$el->setName($texy->nontextParagraph);

		// block contains only markup tags or spaces or nothing
		} else {
			// if {ignoreEmptyStuff} return false;
			if (!$mod) {
				$el->setName(null);
			}
		}

		if ($el->getName()) {
			// apply modifier
			if ($mod) {
				$mod->decorate($texy, $el);
			}

			// add <br />
			if (strpos($content, "\r") !== false) {
				$key = $texy->protect('<br />', $texy::CONTENT_REPLACED);
				$content = str_replace("\r", $key, $content);
			}
		}

		$content = strtr($content, "\r\n", '  ');
		$el->setText($content);

		return $el;
	}
}
