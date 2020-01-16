<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;


/**
 * Emoticon module.
 */
final class EmoticonModule extends Texy\Module
{
	/** @var array<string, string>  supported emoticons and image files / chars */
	public $icons = [
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
	public $class;

	/** @deprecated */
	public $root;

	/** @deprecated */
	public $fileRoot;


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;
		$texy->allowed['emoticon'] = false;
		$texy->addHandler('emoticon', [$this, 'solve']);
		$texy->addHandler('beforeParse', [$this, 'beforeParse']);
	}


	public function beforeParse(): void
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
			[$this, 'pattern'],
			'#(?<=^|[\x00-\x20])(' . implode('|', $pattern) . ')#',
			'emoticon',
			'#' . implode('|', $pattern) . '#'
		);
	}


	/**
	 * Callback for: :-))).
	 * @return Texy\HtmlElement|string|null
	 */
	public function pattern(Texy\LineParser $parser, array $matches)
	{
		$match = $matches[0];

		// find the closest match
		foreach ($this->icons as $emoticon => $foo) {
			if (strncmp($match, $emoticon, strlen($emoticon)) === 0) {
				return $this->texy->invokeAroundHandlers('emoticon', $parser, [$emoticon, $match]);
			}
		}

		return null;
	}


	/**
	 * Finish invocation.
	 * @return Texy\HtmlElement|string
	 */
	public function solve(Texy\HandlerInvocation $invocation, string $emoticon, string $raw)
	{
		$texy = $this->texy;
		$file = $this->icons[$emoticon];
		if (strpos($file, '.') === false) {
			return $file;
		}
		$el = new Texy\HtmlElement('img');
		$el->attrs['src'] = Texy\Helpers::prependRoot($file, $this->root === null ? $texy->imageModule->root : $this->root);
		$el->attrs['alt'] = $raw;
		$el->attrs['class'][] = $this->class;

		// file path
		$file = rtrim($this->fileRoot === null ? (string) $texy->imageModule->fileRoot : $this->fileRoot, '/\\') . '/' . $file;
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
