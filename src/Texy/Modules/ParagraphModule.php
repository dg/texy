<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Regexp;


/**
 * Paragraph module.
 */
final class ParagraphModule extends Texy\Module
{
	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;
		$texy->addHandler('paragraph', [$this, 'solve']);
	}


	public function process(Texy\BlockParser $parser, string $content, Texy\HtmlElement $el): void
	{
		$parts = $parser->isIndented()
			? preg_split('#(\n(?! )|\n{2,})#', $content, -1, PREG_SPLIT_NO_EMPTY)
			: preg_split('#(\n{2,})#', $content, -1, PREG_SPLIT_NO_EMPTY);

		foreach ($parts as $s) {
			$s = trim($s);
			if ($s === '') {
				continue;
			}

			// try to find modifier
			$mod = null;
			if ($mx = Regexp::match($s, '#' . Texy\Patterns::MODIFIER_H . '(?=\n|\z)#sUm', Regexp::OFFSET_CAPTURE)) {
				[$mMod] = $mx[1];
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
	 */
	public function solve(
		Texy\HandlerInvocation $invocation,
		string $content,
		?Texy\Modifier $mod = null,
	): ?Texy\HtmlElement
	{
		$texy = $this->texy;

		// find hard linebreaks
		$content = $texy->mergeLines
			// ....
			// ... => \r means break line
			? Regexp::replace($content, '#\n +(?=\S)#', "\r")
			: Regexp::replace($content, '#\n#', "\r");

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
			if ($texy->nontextParagraph instanceof Texy\HtmlElement) {
				$el = (clone $texy->nontextParagraph)->setText($content);
			} else {
				$el->setName($texy->nontextParagraph);
			}

		// block contains only markup tags or spaces or nothing
		} else {
			// if {ignoreEmptyStuff} return null;
			if (!$mod) {
				$el->setName(null);
			}
		}

		if ($el->getName()) {
			// apply modifier
			if ($mod) {
				$mod->decorate($texy, $el);
			}

			// add <br>
			if (strpos($content, "\r") !== false) {
				$key = $texy->protect('<br>', $texy::CONTENT_REPLACED);
				$content = str_replace("\r", $key, $content);
			}
		}

		$content = strtr($content, "\r\n", '  ');
		$el->setText($content);

		return $el;
	}
}
