<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

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
	public array $bullets = [
		// first-rexexp ordered? list-style-type next-regexp
		'*' => ['\*[\ \t]', 0, ''],
		'-' => ['[\x{2013}-](?![>-])', 0, ''],
		'+' => ['\+[\ \t]', 0, ''],
		'1.' => ['1\.[\ \t]', /* not \d !*/ 1, '', '\d{1,3}\.[\ \t]'],
		'1)' => ['\d{1,3}\)[\ \t]', 1, ''],
		'I.' => ['I\.[\ \t]', 1, 'upper-roman', '[IVX]{1,4}\.[\ \t]'],
		'I)' => ['[IVX]+\)[\ \t]', 1, 'upper-roman'], // before A) !
		'a)' => ['[a-z]\)[\ \t]', 1, 'lower-alpha'],
		'A)' => ['[A-Z]\)[\ \t]', 1, 'upper-alpha'],
	];


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->addHandler('beforeParse', $this->beforeParse(...));
		$texy->allowed['list'] = true;
		$texy->allowed['list/definition'] = true;
	}


	private function beforeParse(): void
	{
		$RE = $REul = [];
		foreach ($this->bullets as $desc) {
			$RE[] = $desc[0];
			if (!$desc[1]) {
				$REul[] = $desc[0];
			}
		}

		$this->texy->registerBlockPattern(
			$this->patternList(...),
			'#^(?:' . Patterns::MODIFIER_H . '\n)?' // .{color: red}
			. '(' . implode('|', $RE) . ')[\ \t]*+\S.*$#mUu', // item (unmatched)
			'list',
		);

		$this->texy->registerBlockPattern(
			$this->patternDefList(...),
			'#^(?:' . Patterns::MODIFIER_H . '\n)?' // .{color:red}
			. '(\S.{0,2000})\:[\ \t]*' . Patterns::MODIFIER_H . '?\n' // Term:
			. '([\ \t]++)(' . implode('|', $REul) . ')[\ \t]*+\S.*$#mUu', // - description
			'list/definition',
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
	 */
	public function patternList(BlockParser $parser, array $matches): ?HtmlElement
	{
		[, $mMod, $mBullet] = $matches;
		// [1] => .(title)[class]{style}<>
		// [2] => bullet * + - 1) a) A) IV)

		$el = new HtmlElement;

		$bullet = $min = null;
		foreach ($this->bullets as $type => $desc) {
			if (preg_match('#' . $desc[0] . '#Au', $mBullet)) {
				$bullet = $desc[3] ?? $desc[0];
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
			return null;
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
	 */
	public function patternDefList(BlockParser $parser, array $matches): HtmlElement
	{
		[, $mMod, , , , $mBullet] = $matches;
		// [1] => .(title)[class]{style}<>
		// [2] => ...
		// [3] => .(title)[class]{style}<>
		// [4] => space
		// [5] => - * +

		$texy = $this->texy;

		$bullet = null;
		foreach ($this->bullets as $desc) {
			if (preg_match('#' . $desc[0] . '#Au', $mBullet)) {
				$bullet = $desc[3] ?? $desc[0];
				break;
			}
		}

		$el = new HtmlElement('dl');
		$mod = new Modifier($mMod);
		$mod->decorate($texy, $el);
		$parser->moveBackward(2);

		$patternTerm = '#^\n?(\S.*)\:[\ \t]*' . Patterns::MODIFIER_H . '?()$#mUA';

		while (true) {
			if ($elItem = $this->patternItem($parser, $bullet, true, 'dd')) {
				$el->add($elItem);
				continue;
			}

			if ($parser->next($patternTerm, $matches)) {
				[, $mContent, $mMod] = $matches;
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
	 */
	private function patternItem(BlockParser $parser, string $bullet, bool $indented, string $tag): ?HtmlElement
	{
		$spacesBase = $indented ? ('[\ \t]{1,}') : '';
		$patternItem = "#^\n?($spacesBase){$bullet}[ \\t]*(\\S.*)?" . Patterns::MODIFIER_H . '?()$#mAUu';

		// first line with bullet
		$matches = null;
		if (!$parser->next($patternItem, $matches)) {
			return null;
		}

		[, $mIndent, $mContent, $mMod] = $matches;
		// [1] => indent
		// [2] => ...
		// [3] => .(title)[class]{style}<>

		$elItem = new HtmlElement($tag);
		$mod = new Modifier($mMod);
		$mod->decorate($this->texy, $elItem);

		// next lines
		$spaces = '';
		$content = ' ' . $mContent; // trick
		while ($parser->next('#^(\n*)' . $mIndent . '([\ \t]{1,' . $spaces . '})(.*)()$#Am', $matches)) {
			[, $mBlank, $mSpaces, $mContent] = $matches;
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
