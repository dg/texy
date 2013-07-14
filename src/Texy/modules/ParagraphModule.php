<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * Paragraph module.
 *
 * @author     David Grudl
 */
final class ParagraphModule extends Texy\Module
{


	public function __construct($texy)
	{
		$this->texy = $texy;
		$texy->addHandler('paragraph', array($this, 'solve'));
	}


	/**
	 * @param  Texy\BlockParser
	 * @param  string     text
	 * @param  array
	 * @param  Texy\HtmlElement
	 * @return vois
	 */
	public function process($parser, $content, $el)
	{
		$tx = $this->texy;

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
			$mod = NULL;
			if ($mx = Texy\Regexp::match($s, '#'.Texy\Patterns::MODIFIER_H.'(?=\n|\z)#sUm', Texy\Regexp::OFFSET_CAPTURE)) {
				list($mMod) = $mx[1];
				$s = trim(substr_replace($s, '', $mx[0][1], strlen($mx[0][0])));
				if ($s === '') {
					continue;
				}
				$mod = new Texy\Modifier;
				$mod->setProperties($mMod);
			}

			$res = $tx->invokeAroundHandlers('paragraph', $parser, array($s, $mod));
			if ($res) {
				$el->insert(NULL, $res);
			}
		}
	}


	/**
	 * Finish invocation.
	 *
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string
	 * @param  Texy\Modifier|NULL
	 * @return Texy\HtmlElement|FALSE
	 */
	public function solve($invocation, $content, $mod)
	{
		$tx = $this->texy;

		// find hard linebreaks
		if ($tx->mergeLines) {
			// ....
			// ... => \r means break line
			$content = Texy\Regexp::replace($content, '#\n +(?=\S)#', "\r");
		} else {
			$content = Texy\Regexp::replace($content, '#\n#', "\r");
		}

		$el = Texy\HtmlElement::el('p');
		$el->parseLine($tx, $content);
		$content = $el->getText(); // string

		// check content type
		// block contains block tag
		if (strpos($content, Texy\Texy::CONTENT_BLOCK) !== FALSE) {
			$el->setName(NULL); // ignores modifier!

		// block contains text (protected)
		} elseif (strpos($content, Texy\Texy::CONTENT_TEXTUAL) !== FALSE) {
			// leave element p

		// block contains text
		} elseif (preg_match('#[^\s'.Texy\Patterns::MARK.']#u', $content)) {
			// leave element p

		// block contains only replaced element
		} elseif (strpos($content, Texy\Texy::CONTENT_REPLACED) !== FALSE) {
			$el->setName($tx->nontextParagraph);

		// block contains only markup tags or spaces or nothing
		} else {
			// if {ignoreEmptyStuff} return FALSE;
			if (!$mod) {
				$el->setName(NULL);
			}
		}

		if ($el->getName()) {
			// apply modifier
			if ($mod) {
				$mod->decorate($tx, $el);
			}

			// add <br />
			if (strpos($content, "\r") !== FALSE) {
				$key = $tx->protect('<br />', Texy\Texy::CONTENT_REPLACED);
				$content = str_replace("\r", $key, $content);
			};
		}

		$content = strtr($content, "\r\n", '  ');
		$el->setText($content);

		return $el;
	}

}
