<?php

/**
 * Texy! - web text markup-language
 * --------------------------------
 *
 * Copyright (c) 2004, 2008 David Grudl (http://davidgrudl.com)
 *
 * This source file is subject to the GNU GPL license that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://texy.info/
 *
 * @copyright  Copyright (c) 2004, 2008 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @link       http://texy.info/
 * @package    Texy
 */



/**
 * Links module.
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2008 David Grudl
 * @package    Texy
 * @version    $Revision$ $Date$
 */
final class TexyLinkModule extends TexyModule
{
	/** @var string  root of relative links */
	public $root = '';

	/** @var string image popup event */
	public $imageOnClick = 'return !popupImage(this.href)';  //

	/** @var string class 'popup' event */
	public $popupOnClick = 'return !popup(this.href)';

	/** @var bool  always use rel="nofollow" for absolute links? */
	public $forceNoFollow = FALSE;

	/** @var bool  shorten URLs to more readable form? */
	public $shorten = TRUE;

	/** @var array link references */
	private $references = array();

	/** @var array */
	private static $livelock;



	public function __construct($texy)
	{
		$this->texy = $texy;

		$texy->allowed['link/definition'] = TRUE;
		$texy->addHandler('newReference', array($this, 'solveNewReference'));
		$texy->addHandler('linkReference', array($this, 'solve'));
		$texy->addHandler('linkEmail', array($this, 'solveUrlEmail'));
		$texy->addHandler('linkURL', array($this, 'solveUrlEmail'));
		$texy->addHandler('beforeParse', array($this, 'beforeParse'));

		// [reference]
		$texy->registerLinePattern(
			array($this, 'patternReference'),
			'#(\[[^\[\]\*\n'.TEXY_MARK.']+\])#U',
			'link/reference'
		);

		// direct url and email
		$texy->registerLinePattern(
			array($this, 'patternUrlEmail'),
			'#(?<=^|[\s([<:\x17])(?:https?://|www\.|ftp://)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#iu',
			'link/url'
		);

		$texy->registerLinePattern(
			array($this, 'patternUrlEmail'),
			'#(?<=^|[\s([<:\x17])'.TEXY_EMAIL.'#iu',
			'link/email'
		);
	}



	/**
	 * Text pre-processing.
	 * @param  Texy
	 * @param  string
	 * @return void
	 */
	public function beforeParse($texy, & $text)
	{
		self::$livelock = array();

		// [la trine]: http://www.latrine.cz/ text odkazu .(title)[class]{style}
		if ($texy->allowed['link/definition']) {
			$text = preg_replace_callback(
				'#^\[([^\[\]\#\?\*\n]+)\]: +(\S+)(\ .+)?'.TEXY_MODIFIER.'?\s*()$#mUu',
				array($this, 'patternReferenceDef'),
				$text
			);
		}
	}



	/**
	 * Callback for: [la trine]: http://www.latrine.cz/ text odkazu .(title)[class]{style}.
	 *
	 * @param  array      regexp matches
	 * @return string
	 */
	private function patternReferenceDef($matches)
	{
		list(, $mRef, $mLink, $mLabel, $mMod) = $matches;
		//    [1] => [ (reference) ]
		//    [2] => link
		//    [3] => ...
		//    [4] => .(title)[class]{style}

		$link = new TexyLink($mLink);
		$link->label = trim($mLabel);
		$link->modifier->setProperties($mMod);
		$this->checkLink($link);
		$this->addReference($mRef, $link);
		return '';
	}



	/**
	 * Callback for: [ref].
	 *
	 * @param  TexyLineParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return TexyHtml|string|FALSE
	 */
	public function patternReference($parser, $matches)
	{
		list(, $mRef) = $matches;
		//    [1] => [ref]

		$tx = $this->texy;
		$name = substr($mRef, 1, -1);
		$link = $this->getReference($name);

		if (!$link) {
			return $tx->invokeAroundHandlers('newReference', $parser, array($name));
		}

		$link->type = TexyLink::BRACKET;

		if ($link->label != '') {  // NULL or ''
			// prevent circular references
			if (isset(self::$livelock[$link->name])) {
				$content = $link->label;
			} else {
				self::$livelock[$link->name] = TRUE;
				$el = TexyHtml::el();
				$lineParser = new TexyLineParser($tx, $el);
				$lineParser->parse($link->label);
				$content = $el->toString($tx);
				unset(self::$livelock[$link->name]);
			}
		} else {
			$content = $this->textualUrl($link);
			$content = $this->texy->protect($content, Texy::CONTENT_TEXTUAL);
		}

		return $tx->invokeAroundHandlers('linkReference', $parser, array($link, $content));
	}



	/**
	 * Callback for: http://davidgrudl.com   david@grudl.com.
	 *
	 * @param  TexyLineParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return TexyHtml|string|FALSE
	 */
	public function patternUrlEmail($parser, $matches, $name)
	{
		list($mURL) = $matches;
		//    [0] => URL

		$link = new TexyLink($mURL);
		$this->checkLink($link);

		return $this->texy->invokeAroundHandlers(
			$name === 'link/email' ? 'linkEmail' : 'linkURL',
			$parser,
			array($link)
		);
	}



	/**
	 * Adds new named reference.
	 *
	 * @param  string  reference name
	 * @param  TexyLink
	 * @return void
	 */
	public function addReference($name, TexyLink $link)
	{
		$link->name = TexyUtf::strtolower($name);
		$this->references[$link->name] = $link;
	}



	/**
	 * Returns named reference.
	 *
	 * @param  string  reference name
	 * @return TexyLink reference descriptor (or FALSE)
	 */
	public function getReference($name)
	{
		$name = TexyUtf::strtolower($name);
		if (isset($this->references[$name])) {
			return clone $this->references[$name];

		} else {
			$pos = strpos($name, '?');
			if ($pos === FALSE) $pos = strpos($name, '#');
			if ($pos !== FALSE) { // try to extract ?... #... part
				$name2 = substr($name, 0, $pos);
				if (isset($this->references[$name2])) {
					$link = clone $this->references[$name2];
					$link->URL .= substr($name, $pos);
					return $link;
				}
			}
		}

		return FALSE;
	}



	/**
	 * @param  string
	 * @param  string
	 * @param  string
	 * @return TexyLink
	 */
	public function factoryLink($dest, $mMod, $label)
	{
		$tx = $this->texy;
		$type = TexyLink::COMMON;

		// [ref]
		if (strlen($dest)>1 && $dest{0} === '[' && $dest{1} !== '*') {
			$type = TexyLink::BRACKET;
			$dest = substr($dest, 1, -1);
			$link = $this->getReference($dest);

		// [* image *]
		} elseif (strlen($dest)>1 && $dest{0} === '[' && $dest{1} === '*') {
			$type = TexyLink::IMAGE;
			$dest = trim(substr($dest, 2, -2));
			$image = $tx->imageModule->getReference($dest);
			if ($image) {
				$link = new TexyLink($image->linkedURL === NULL ? $image->URL : $image->linkedURL);
				$link->modifier = $image->modifier;
			}
		}

		if (empty($link)) {
			$link = new TexyLink(trim($dest));
			$this->checkLink($link);
		}

		if (strpos($link->URL, '%s') !== FALSE) {
			$link->URL = str_replace('%s', urlencode($tx->stringToText($label)), $link->URL);
		}
		$link->modifier->setProperties($mMod);
		$link->type = $type;
		return $link;
	}



	/**
	 * Finish invocation.
	 *
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  TexyLink
	 * @param  TexyHtml|string
	 * @return TexyHtml|string
	 */
	public function solve($invocation, $link, $content = NULL)
	{
		if ($link->URL == NULL) return $content;

		$tx = $this->texy;

		$el = TexyHtml::el('a');

		if (empty($link->modifier)) {
			$nofollow = $popup = FALSE;
		} else {
			$nofollow = isset($link->modifier->classes['nofollow']);
			$popup = isset($link->modifier->classes['popup']);
			unset($link->modifier->classes['nofollow'], $link->modifier->classes['popup']);
			$el->attrs['href'] = NULL; // trick - move to front
			$link->modifier->decorate($tx, $el);
		}

		if ($link->type === TexyLink::IMAGE) {
			// image
			$el->attrs['href'] = Texy::prependRoot($link->URL, $tx->imageModule->linkedRoot);
			$el->attrs['onclick'] = $this->imageOnClick;

		} else {
			$el->attrs['href'] = Texy::prependRoot($link->URL, $this->root);

			// rel="nofollow"
			if ($nofollow || ($this->forceNoFollow && strpos($el->attrs['href'], '//') !== FALSE))
				$el->attrs['rel'] = 'nofollow';
		}

		// popup on click
		if ($popup) $el->attrs['onclick'] = $this->popupOnClick;

		if ($content !== NULL) $el->add($content);

		$tx->summary['links'][] = $el->attrs['href'];

		return $el;
	}



	/**
	 * Finish invocation.
	 *
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  TexyLink
	 * @return TexyHtml|string
	 */
	public function solveUrlEmail($invocation, $link)
	{
		$content = $this->textualUrl($link);
		$content = $this->texy->protect($content, Texy::CONTENT_TEXTUAL);
		return $this->solve(NULL, $link, $content);
	}



	/**
	 * Finish invocation.
	 *
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  string
	 * @return FALSE
	 */
	public function solveNewReference($invocation, $name)
	{
		// no change
		return FALSE;
	}



	/**
	 * Checks and corrects $URL.
	 * @param  TexyLink
	 * @return void
	 */
	private function checkLink($link)
	{
		if (strncasecmp($link->URL, 'www.', 4) === 0) {
			// special supported case
			$link->URL = 'http://' . $link->URL;

		} elseif (preg_match('#'.TEXY_EMAIL.'$#iA', $link->URL)) {
			// email
			$link->URL = 'mailto:' . $link->URL;

		} elseif (!$this->texy->checkURL($link->URL, Texy::FILTER_ANCHOR)) {
			$link->URL = NULL;

		} else {
			$link->URL = str_replace('&amp;', '&', $link->URL); // replace unwanted &amp;
		}
	}



	/**
	 * Returns textual representation of URL.
	 * @param  TexyLink
	 * @return string
	 */
	private function textualUrl($link)
	{
		if ($this->texy->obfuscateEmail && preg_match('#^'.TEXY_EMAIL.'$#i', $link->raw)) { // email
			return str_replace('@', "&#64;<!---->", $link->raw);
		}

		if ($this->shorten && preg_match('#^(https?://|ftp://|www\.|/)#i', $link->raw)) {

			if (strncasecmp($link->raw, 'www.', 4) === 0) $parts = @parse_url('none://' . $link->raw);
			else $parts = @parse_url($link->raw);

			if ($parts === FALSE) return $link->raw;

			$res = '';
			if (isset($parts['scheme']) && $parts['scheme'] !== 'none')
				$res .= $parts['scheme'] . '://';

			if (isset($parts['host']))
				$res .= $parts['host'];

			if (isset($parts['path']))
				$res .=  (strlen($parts['path']) > 16 ? ("/\xe2\x80\xa6" . preg_replace('#^.*(.{0,12})$#U', '$1', $parts['path'])) : $parts['path']);

			if (isset($parts['query'])) {
				$res .= strlen($parts['query']) > 4 ? "?\xe2\x80\xa6" : ('?' . $parts['query']);
			} elseif (isset($parts['fragment'])) {
				$res .= strlen($parts['fragment']) > 4 ? "#\xe2\x80\xa6" : ('#' . $parts['fragment']);
			}
			return $res;
		}

		return $link->raw;
	}

}









/**
 * @package Texy
 */
final class TexyLink extends /*Nette::*/Object
{
	/** @see $type */
	const
		COMMON = 1,
		BRACKET = 2,
		IMAGE = 3;

	/** @var string  URL in resolved form */
	public $URL;

	/** @var string  URL as written in text */
	public $raw;

	/** @var TexyModifier */
	public $modifier;

	/** @var int  how was link created? */
	public $type = TexyLink::COMMON;

	/** @var string  optional label, used by references */
	public $label;

	/** @var string  reference name (if is stored as reference) */
	public $name;



	public function __construct($URL)
	{
		$this->URL = $URL;
		$this->raw = $URL;
		$this->modifier = new TexyModifier;
	}



	public function __clone()
	{
		if ($this->modifier) {
			$this->modifier = clone $this->modifier;
		}
	}

}
