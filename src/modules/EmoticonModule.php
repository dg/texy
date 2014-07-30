<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * Emoticon module.
 *
 * @author     David Grudl
 */
final class EmoticonModule extends Texy\Module
{
	/** @var array  supported emoticons and image files */
	public $icons = array(
		':-)' => 'smile.gif',
		':-(' => 'sad.gif',
		';-)' => 'wink.gif',
		':-D' => 'biggrin.gif',
		'8-O' => 'eek.gif',
		'8-)' => 'cool.gif',
		':-?' => 'confused.gif',
		':-x' => 'mad.gif',
		':-P' => 'razz.gif',
		':-|' => 'neutral.gif',
	);

	/** @var string  CSS class for emoticons */
	public $class;

	/** @var string  root of relative images (default value is $texy->imageModule->root) */
	public $root;

	/** @var string  physical location of images on server (default value is $texy->imageModule->fileRoot) */
	public $fileRoot;


	public function __construct($texy)
	{
		$this->texy = $texy;
		$texy->allowed['emoticon'] = FALSE;
		$texy->addHandler('emoticon', array($this, 'solve'));
		$texy->addHandler('beforeParse', array($this, 'beforeParse'));
	}


	public function beforeParse()
	{
		if (empty($this->texy->allowed['emoticon'])) {
			return;
		}

		krsort($this->icons);

		$pattern = array();
		foreach ($this->icons as $key => $foo) {
			$pattern[] = preg_quote($key, '#') . '+'; // last char can be repeated
		}

		$this->texy->registerLinePattern(
			array($this, 'pattern'),
			'#(?<=^|[\\x00-\\x20])(' . implode('|', $pattern) . ')#',
			'emoticon',
			'#' . implode('|', $pattern) . '#'
		);
	}


	/**
	 * Callback for: :-))).
	 *
	 * @param  Texy\LineParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return Texy\HtmlElement|string|FALSE
	 */
	public function pattern($parser, $matches)
	{
		$match = $matches[0];
		$tx = $this->texy;

		// find the closest match
		foreach ($this->icons as $emoticon => $foo) {
			if (strncmp($match, $emoticon, strlen($emoticon)) === 0) {
				return $tx->invokeAroundHandlers('emoticon', $parser, array($emoticon, $match));
			}
		}

		return FALSE; // tohle se nestane
	}


	/**
	 * Finish invocation.
	 *
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string
	 * @param  string
	 * @return Texy\HtmlElement|FALSE
	 */
	public function solve($invocation, $emoticon, $raw)
	{
		$tx = $this->texy;
		$file = $this->icons[$emoticon];
		$el = Texy\HtmlElement::el('img');
		$el->attrs['src'] = Texy\Texy::prependRoot($file, $this->root === NULL ? $tx->imageModule->root : $this->root);
		$el->attrs['alt'] = $raw;
		$el->attrs['class'][] = $this->class;

		// file path
		$file = rtrim($this->fileRoot === NULL ? $tx->imageModule->fileRoot : $this->fileRoot, '/\\') . '/' . $file;
		if (@is_file($file)) { // intentionally @
			$size = @getImageSize($file); // intentionally @
			if (is_array($size)) {
				$el->attrs['width'] = $size[0];
				$el->attrs['height'] = $size[1];
			}
		}
		$tx->summary['images'][] = $el->attrs['src'];
		return $el;
	}

}
