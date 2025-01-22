<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\HtmlElement;
use Texy\Nodes\EmoticonNode;


/**
 * Emoticon module.
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
		$texy->addHandler(EmoticonNode::class, $this->toElement(...));
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
			$pattern[] = Texy\Regexp::quote($key) . '+'; // last char can be repeated
		}

		$this->texy->registerLinePattern(
			$this->parse(...),
			'~
				(?<= ^ | [\x00-\x20] )
				(' . implode('|', $pattern) . ')
			~',
			'emoticon',
			'~' . implode('|', $pattern) . '~',
		);
	}


	/**
	 * Callback for: :-))).
	 */
	public function parse(Texy\LineParser $parser, array $matches): EmoticonNode
	{
		return new EmoticonNode($matches[0]);
	}


	public function toElement(EmoticonNode $node, Texy\Texy $texy): HtmlElement
	{
		$found = null;
		foreach ($this->icons as $emoticon => $replacement) {
			if (strncmp($node->emoticon, $emoticon, strlen($emoticon)) === 0) {
				$found = $replacement;
				break;
			}
		}

		if ($found === null) {
			return (new HtmlElement)->setText($node->emoticon);
		} elseif (!str_contains($found, '.')) {
			return (new HtmlElement)->setText($found);
		}

		$el = new HtmlElement('img');
		$el->attrs['src'] = Texy\Helpers::prependRoot($found, $this->root ?? $texy->imageModule->root);
		$el->attrs['alt'] = $node->emoticon;
		$el->attrs['class'][] = $this->class;

		// file path
		$found = rtrim($this->fileRoot ?? (string) $texy->imageModule->fileRoot, '/\\') . '/' . $found;
		if (@is_file($found)) { // intentionally @
			$size = @getimagesize($found); // intentionally @
			if (is_array($size)) {
				$el->attrs['width'] = $size[0];
				$el->attrs['height'] = $size[1];
			}
		}

		$texy->summary['images'][] = $el->attrs['src'];
		return $el;
	}
}
