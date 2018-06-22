<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * Blockquote module.
 */
final class BlockQuoteModule extends Texy\Module
{
	public function __construct($texy)
	{
		$this->texy = $texy;

		$texy->registerBlockPattern(
			[$this, 'pattern'],
			'#^(?:' . Texy\Patterns::MODIFIER_H . '\n)?\>(\ ++|:)(\S.*+)$#mU', // original
			// '#^(?:'.Texy\Patterns::MODIFIER_H.'\n)?\>(?:(\>|\ +?|:)(.*))?()$#mU', // >>>>
			// '#^(?:'.Texy\Patterns::MODIFIER_H.'\n)?\>(?:(\ +?|:)(.*))()$#mU', // only >
			'blockquote'
		);
	}


	/**
	 * Callback for:.
	 *
	 * > They went in single file, running like hounds on a strong scent,
	 * and an eager light was in their eyes. Nearly due west the broad
	 * swath of the marching Orcs tramped its ugly slot; the sweet grass
	 * of Rohan had been bruised and blackened as they passed.
	 * >:http://www.mycom.com/tolkien/twotowers.html
	 *
	 * @return Texy\HtmlElement|string|false
	 */
	public function pattern(Texy\BlockParser $parser, array $matches)
	{
		list(, $mMod, $mPrefix, $mContent) = $matches;
		// [1] => .(title)[class]{style}<>
		// [2] => spaces |
		// [3] => ... / LINK

		$texy = $this->texy;

		$el = new Texy\HtmlElement('blockquote');
		$mod = new Texy\Modifier($mMod);
		$mod->decorate($texy, $el);

		$content = '';
		$spaces = '';
		do {
			if ($mPrefix === ':') {
				$mod->cite = $texy->blockQuoteModule->citeLink($mContent);
				$content .= "\n";
			} else {
				if ($spaces === '') {
					$spaces = max(1, strlen($mPrefix));
				}
				$content .= $mContent . "\n";
			}

			if (!$parser->next("#^>(?:|(\\ {1,$spaces}|:)(.*))()$#mA", $matches)) {
				break;
			}

/*
			if ($mPrefix === '>') {
				$content .= $mPrefix . $mContent . "\n";
			} elseif ($mPrefix === ':') {
				$mod->cite = $texy->blockQuoteModule->citeLink($mContent);
				$content .= "\n";
			} else {
				if ($spaces === '') $spaces = max(1, strlen($mPrefix));
				$content .= $mContent . "\n";
			}
			if (!$parser->next("#^\\>(?:(\\>|\\ {1,$spaces}|:)(.*))?()$#mA", $matches)) break;
*/

			list(, $mPrefix, $mContent) = $matches;
		} while (true);

		$el->attrs['cite'] = $mod->cite;
		$el->parseBlock($texy, $content, $parser->isIndented());

		// no content?
		if (!$el->count()) {
			return false;
		}

		// event listener
		$texy->invokeHandlers('afterBlockquote', [$parser, $el, $mod]);

		return $el;
	}


	/**
	 * Converts cite source to URL.
	 * @param  string
	 * @return string|null
	 */
	public function citeLink($link)
	{
		$texy = $this->texy;

		if ($link == null) {
			return null;
		}

		if ($link{0} === '[') { // [ref]
			$link = substr($link, 1, -1);
			$ref = $texy->linkModule->getReference($link);
			if ($ref) {
				return Texy\Helpers::prependRoot($ref->URL, $texy->linkModule->root);
			}
		}

		// special supported case
		if (strncasecmp($link, 'www.', 4) === 0) {
			return 'http://' . $link;
		}

		return Texy\Helpers::prependRoot($link, $texy->linkModule->root);
	}
}
