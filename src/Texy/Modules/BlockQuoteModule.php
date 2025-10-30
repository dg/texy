<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use function max, strlen;


/**
 * Blockquote module.
 */
final class BlockQuoteModule extends Texy\Module
{
	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->registerBlockPattern(
			$this->parse(...),
			'~^
				(?: ' . Texy\Patterns::MODIFIER_H . '\n)? # modifier (1)
				>                                      # blockquote char
				( [ \t]++ | : )                        # space/tab or colon (2)
				( \S.*+ )                              # content (3)
			$~mU',
			'blockquote',
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
	 */
	public function parse(Texy\BlockParser $parser, array $matches): Texy\HtmlElement|string|null
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
			if ($spaces === '') {
				$spaces = max(1, strlen($mPrefix));
			}
			$content .= $mContent . "\n";

			if (!$parser->next("~^>(?: | ([ \\t]{1,$spaces} | :) (.*))$~mA", $matches)) {
				break;
			}

			[, $mPrefix, $mContent] = $matches;
		} while (true);

		$el->inject($texy->parseBlock($content, $parser->isIndented()));

		// no content?
		if (!$el->count()) {
			return null;
		}

		// event listener
		$texy->invokeHandlers('afterBlockquote', [$parser, $el, $mod]);

		return $el;
	}
}
