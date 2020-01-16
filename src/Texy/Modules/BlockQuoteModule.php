<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;


/**
 * Blockquote module.
 */
final class BlockQuoteModule extends Texy\Module
{
	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->registerBlockPattern(
			[$this, 'pattern'],
			'#^(?:' . Texy\Patterns::MODIFIER_H . '\n)?\>(\ ++|:)(\S.*+)$#mU', // original
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
	 * @return Texy\HtmlElement|string|null
	 */
	public function pattern(Texy\BlockParser $parser, array $matches)
	{
		[, $mMod, $mPrefix, $mContent] = $matches;
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

			[, $mPrefix, $mContent] = $matches;
		} while (true);

		$el->attrs['cite'] = $mod->cite;
		$el->parseBlock($texy, $content, $parser->isIndented());

		// no content?
		if (!$el->count()) {
			return null;
		}

		// event listener
		$texy->invokeHandlers('afterBlockquote', [$parser, $el, $mod]);

		return $el;
	}


	/**
	 * Converts cite source to URL.
	 */
	public function citeLink(string $link): ?string
	{
		$texy = $this->texy;

		if ($link == null) {
			return null;
		}

		if ($link[0] === '[') { // [ref]
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
