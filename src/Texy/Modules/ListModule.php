<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\BlockParser;
use Texy\HtmlElement;
use Texy\Modifier;
use Texy\Patterns;
use Texy\Regexp;
use function implode, ord, strlen;


/**
 * Processes ordered, unordered, and definition lists with nesting.
 */
final class ListModule extends Texy\Module
{
	/** @var array<string, array{string, int, string, 3?: string}> [regex, ordered?, list-style-type, next-regex?] */
	public array $bullets = [
		// first-rexexp ordered? list-style-type next-regexp
		'*' => ['\* [ \t]', 0, ''],
		'-' => ['[\x{2013}-] (?! [>-] )', 0, ''],
		'+' => ['\+ [ \t]', 0, ''],
		'1.' => ['1 \. [ \t]', /* not \d !*/ 1, '', '\d{1,3} \. [ \t]'],
		'1)' => ['\d{1,3} \) [ \t]', 1, ''],
		'I.' => ['I \. [ \t]', 1, 'upper-roman', '[IVX]{1,4} \. [ \t]'],
		'I)' => ['[IVX]+ \) [ \t]', 1, 'upper-roman'], // before A) !
		'a)' => ['[a-z] \) [ \t]', 1, 'lower-alpha'],
		'A)' => ['[A-Z] \) [ \t]', 1, 'upper-alpha'],
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
			$this->parseList(...),
			'~^
				(?:' . Patterns::MODIFIER_H . '\n)? # modifier (1)
				(' . implode('|', $RE) . ')         # list marker (2)
				[ \t]*+
				\S .*                               # content
			$~mU',
			'list',
		);

		$this->texy->registerBlockPattern(
			$this->parseDefList(...),
			'~^
				(?:' . Patterns::MODIFIER_H . '\n)?   # modifier (1)
				( \S .{0,2000} )                      # definition term (2)
				: [ \t]*                              # colon separator
				' . Patterns::MODIFIER_H . '?         # modifier (3)
				\n
				([ \t]++)                             # indentation (4)
				(' . implode('|', $REul) . ')         # description marker (5)
				[ \t]*+
				\S .*                                 # content
			$~mU',
			'list/definition',
		);
	}


	/**
	 * Parses list.
	 * @param  array<?string>  $matches
	 */
	public function parseList(BlockParser $parser, array $matches): ?HtmlElement
	{
		/** @var array{string, ?string, string} $matches */
		[, $mMod, $mBullet] = $matches;
		// [1] => .(title)[class]{style}<>
		// [2] => bullet * + - 1) a) A) IV)

		$el = new HtmlElement;

		$bullet = $min = null;
		foreach ($this->bullets as $type => $desc) {
			if (Regexp::match($mBullet, '~' . $desc[0] . '~A')) {
				$bullet = $desc[3] ?? $desc[0];
				$min = isset($desc[3]) ? 2 : 1;
				$el->setName($desc[1] ? 'ol' : 'ul');
				settype($el->attrs['style'], 'array');
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
		assert($bullet !== null);

		$mod = new Modifier($mMod);
		$mod->decorate($this->texy, $el);

		$parser->moveBackward(1);

		while ($elItem = $this->parseItem($parser, $bullet, false, 'li')) {
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
	 * Parses definition list.
	 * @param  array<?string>  $matches
	 */
	public function parseDefList(BlockParser $parser, array $matches): HtmlElement
	{
		/** @var array{string, ?string, string, ?string, string, string} $matches */
		[, $mMod, , , , $mBullet] = $matches;
		// [1] => .(title)[class]{style}<>
		// [2] => ...
		// [3] => .(title)[class]{style}<>
		// [4] => space
		// [5] => - * +

		$texy = $this->texy;

		$bullet = null;
		foreach ($this->bullets as $desc) {
			if (Regexp::match($mBullet, '~' . $desc[0] . '~A')) {
				$bullet = $desc[3] ?? $desc[0];
				break;
			}
		}
		assert($bullet !== null);

		$el = new HtmlElement('dl');
		$mod = new Modifier($mMod);
		$mod->decorate($texy, $el);
		$parser->moveBackward(2);

		$patternTerm = '~^
			\n?
			( \S .* )                       # term content
			: [ \t]*                        # colon separator
			' . Patterns::MODIFIER_H . '?
		$~mUA';

		while (true) {
			if ($elItem = $this->parseItem($parser, $bullet, true, 'dd')) {
				$el->add($elItem);
				continue;
			}

			if ($parser->next($patternTerm, $matches)) {
				/** @var array{string, string, ?string} $matches */
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
	 * Parses single list item.
	 */
	private function parseItem(BlockParser $parser, string $bullet, bool $indented, string $tag): ?HtmlElement
	{
		$spacesBase = $indented ? ('[\ \t]{1,}') : '';
		$patternItem = "~^
			\\n?
			($spacesBase)                            # base indentation
			{$bullet}                                # bullet character
			[ \\t]*
			( \\S .* )?                              # content
			" . Patterns::MODIFIER_H . '?
		$~mAU';

		// first line with bullet
		$matches = null;
		if (!$parser->next($patternItem, $matches)) {
			return null;
		}

		/** @var array{string, string, ?string, ?string} $matches */
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
		while ($parser->next('~^
			(\n*)
			' . Regexp::quote($mIndent) . '
			([ \t]{1,' . $spaces . '})
			(.*)
		$~Am', $matches)) {
			/** @var array{string, string, string, string} $matches */
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
