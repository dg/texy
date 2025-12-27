<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use function getimagesize, implode, is_array, is_file, krsort, preg_quote, rtrim, str_contains;


/**
 * Replaces emoticons with images or Unicode characters.
 */
final class EmoticonModule extends Texy\Module
{
	/** @var array<string, string>  supported emoticons and image files / chars */
	public array $icons = [
		':-)' => '🙂',
		':-(' => '☹',
		';-)' => '😉',
		':-D' => '😁',
		'8-O' => '😮',
		'8-)' => '😄',
		':-?' => '😕',
		':-x' => '😶',
		':-P' => '😛',
		':-|' => '😐',
	];

	/** @deprecated */
	public ?string $class = null;

	/** @deprecated */
	public ?string $root = null;

	/** @deprecated */
	public ?string $fileRoot = null;


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;
		$texy->allowed['emoticon'] = false;
		$texy->addHandler('emoticon', $this->solve(...));
		$texy->addHandler('beforeParse', $this->beforeParse(...));
	}


	private function beforeParse(): void
	{
		if (empty($this->texy->allowed['emoticon'])) {
			return;
		}

		krsort($this->icons);

		$pattern = [];
		foreach ($this->icons as $key => $foo) {
			$pattern[] = preg_quote($key, '#') . '+'; // last char can be repeated
		}

		$this->texy->registerLinePattern(
			$this->pattern(...),
			'#(?<=^|[\x00-\x20])(' . implode('|', $pattern) . ')#',
			'emoticon',
			'#' . implode('|', $pattern) . '#',
		);
	}


	/**
	 * Callback for: :-))).
	 * @param  string[]  $matches
	 */
	public function pattern(Texy\LineParser $parser, array $matches): Texy\HtmlElement|string|null
	{
		$match = $matches[0];

		// find the closest match
		foreach ($this->icons as $emoticon => $foo) {
			if (str_starts_with($match, $emoticon)) {
				return $this->texy->invokeAroundHandlers('emoticon', $parser, [$emoticon, $match]);
			}
		}

		return null;
	}


	/**
	 * Finish invocation.
	 */
	private function solve(Texy\HandlerInvocation $invocation, string $emoticon, string $raw): Texy\HtmlElement|string
	{
		$texy = $this->texy;
		$file = $this->icons[$emoticon];
		if (!str_contains($file, '.')) {
			return $file;
		}

		$el = new Texy\HtmlElement('img');
		$el->attrs['src'] = Texy\Helpers::prependRoot($file, $this->root ?? $texy->imageModule->root);
		$el->attrs['alt'] = $raw;
		if ($this->class !== null) {
			settype($el->attrs['class'], 'array');
			$el->attrs['class'][] = $this->class;
		}

		// file path
		$file = rtrim($this->fileRoot ?? (string) $texy->imageModule->fileRoot, '/\\') . '/' . $file;
		if (@is_file($file)) { // intentionally @
			$size = @getimagesize($file); // intentionally @
			if (is_array($size)) {
				$el->attrs['width'] = $size[0];
				$el->attrs['height'] = $size[1];
			}
		}

		$texy->summary['images'][] = $el->attrs['src'];
		return $el;
	}
}
