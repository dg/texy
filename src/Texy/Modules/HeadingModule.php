<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Modifier;


/**
 * Heading module.
 */
final class HeadingModule extends Texy\Module
{
	public const
		DYNAMIC = 1, // auto-leveling
		FIXED = 2; // fixed-leveling

	/** textual content of first heading */
	public ?string $title = null;

	/** @var array<int, array{el: Texy\HtmlElement, level: int, type: string}>  generated Table of Contents */
	public array $TOC = [];

	public bool $generateID = false;

	/** prefix for autogenerated ID */
	public string $idPrefix = 'toc-';

	/** level of top heading, 1..6 */
	public int $top = 1;

	/** surrounded headings: more #### means higher heading */
	public bool $moreMeansHigher = true;

	/** balancing mode */
	public int $balancing = self::DYNAMIC;

	/** @var array<string, int>  when $balancing = HeadingModule::FIXED */
	public array $levels = [
		'#' => 0, // # --> $levels['#'] + $top = 0 + 1 = 1 --> <h1> ... </h1>
		'*' => 1,
		'=' => 2,
		'-' => 3,
	];

	/** @var array<string, true>  used ID's */
	private array $usedID = [];


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->addHandler('heading', $this->toElement(...));
		$texy->addHandler('beforeParse', $this->beforeParse(...));
		$texy->addHandler('afterParse', $this->afterParse(...));

		$texy->registerBlockPattern(
			$this->parseUnderline(...),
			'~^
				( \S .{0,1000} )                 # heading text (1)
				' . Texy\Patterns::MODIFIER_H . '? # modifier (2)
				\n
				( \#{3,}+ | \*{3,}+ | ={3,}+ | -{3,}+ )  # underline characters (3)
			$~mU',
			'heading/underlined',
		);

		$texy->registerBlockPattern(
			$this->parseSurround(...),
			'~^
				( \#{2,}+ | ={2,}+ )             # opening characters (1)
				(.+)                             # heading text (2)
				' . Texy\Patterns::MODIFIER_H . '? # modifier (2)
			$~mU',
			'heading/surrounded',
		);
	}


	private function beforeParse(): void
	{
		$this->title = null;
		$this->usedID = [];
		$this->TOC = [];
	}


	private function afterParse(Texy\Texy $texy, Texy\HtmlElement $DOM, bool $isSingleLine): void
	{
		if ($isSingleLine) {
			return;
		}

		if ($this->balancing === self::DYNAMIC) {
			$top = $this->top;
			$map = [];
			$min = 100;
			foreach ($this->TOC as $item) {
				$level = $item['level'];
				if ($item['type'] === 'surrounded') {
					$min = min($level, $min);
					$top = $this->top - $min;

				} elseif ($item['type'] === 'underlined') {
					$map[$level] = $level;
				}
			}

			asort($map);
			$map = array_flip(array_values($map));
		}

		foreach ($this->TOC as $key => $item) {
			if ($this->balancing === self::DYNAMIC) {
				if ($item['type'] === 'surrounded') {
					$level = $item['level'] + $top;

				} elseif ($item['type'] === 'underlined') {
					$level = $map[$item['level']] + $this->top;

				} else {
					$level = $item['level'];
				}

				$item['el']->setName('h' . min(6, max(1, $level)));
				$this->TOC[$key]['level'] = $level;
			}

			if ($this->generateID) {
				if (!empty($item['el']->attrs['style']['toc']) && is_array($item['el']->attrs['style'])) {
					$title = $item['el']->attrs['style']['toc'];
					unset($item['el']->attrs['style']['toc']);
				} else {
					$title = trim($this->texy->maskedStringToText($this->texy->elemToMaskedString($item['el'])));
				}

				$this->TOC[$key]['title'] = $title;
				if (empty($item['el']->attrs['id'])) {
					$id = $this->idPrefix . Texy\Helpers::webalize($title);
					$counter = '';
					if (isset($this->usedID[$id . $counter])) {
						$counter = 2;
						while (isset($this->usedID[$id . '-' . $counter])) {
							$counter++;
						}

						$id .= '-' . $counter;
					}

					$this->usedID[$id] = true;
					$item['el']->attrs['id'] = $id;
				}
			}
		}

		// document title
		if ($this->title === null && count($this->TOC)) {
			$item = reset($this->TOC);
			$this->title = $item['title'] ?? trim($this->texy->maskedStringToText($this->texy->elemToMaskedString($item['el'])));
		}
	}


	/**
	 * Callback for underlined heading.
	 *
	 * Heading .(title)[class]{style}>
	 * -------------------------------
	 */
	public function parseUnderline(Texy\BlockParser $parser, array $matches): Texy\HtmlElement|string|null
	{
		[, $mContent, $mMod, $mLine] = $matches;
		// $matches:
		// [1] => ...
		// [2] => .(title)[class]{style}<>
		// [3] => ...

		$mod = new Modifier($mMod);
		$level = $this->levels[$mLine[0]];
		return $this->texy->invokeAroundHandlers('heading', $parser, [$level, $mContent, $mod, false]);
	}


	/**
	 * Callback for surrounded heading.
	 *
	 * ### Heading .(title)[class]{style}>
	 */
	public function parseSurround(Texy\BlockParser $parser, array $matches): Texy\HtmlElement|string|null
	{
		[, $mLine, $mContent, $mMod] = $matches;
		// [1] => ###
		// [2] => ...
		// [3] => .(title)[class]{style}<>

		$mod = new Modifier($mMod);
		$level = min(7, max(2, strlen($mLine)));
		$level = $this->moreMeansHigher ? 7 - $level : $level - 2;
		$mContent = rtrim($mContent, $mLine[0] . ' ');
		return $this->texy->invokeAroundHandlers('heading', $parser, [$level, $mContent, $mod, true]);
	}


	public function toElement(
		Texy\HandlerInvocation $invocation,
		int $level,
		string $content,
		Modifier $mod,
		bool $isSurrounded,
	): Texy\HtmlElement
	{
		// as fixed balancing, for block/texysource & correct decorating
		$el = new Texy\HtmlElement('h' . min(6, max(1, $level + $this->top)));
		$mod->decorate($this->texy, $el);

		$el->inject($this->texy->parseLine(trim($content)));

		$this->TOC[] = [
			'el' => $el,
			'level' => $level,
			'type' => $isSurrounded ? 'surrounded' : 'underlined',
		];

		return $el;
	}
}
