<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Texy basic configurators.
 *
 * <code>
 * $texy = new Texy();
 * Configurator::safeMode($texy);
 * </code>
 */
class Configurator
{
	use Strict;

	public static $safeTags = [
		'a' => ['href', 'title'],
		'acronym' => ['title'],
		'b' => [],
		'br' => [],
		'cite' => [],
		'code' => [],
		'em' => [],
		'i' => [],
		'strong' => [],
		'sub' => [],
		'sup' => [],
		'q' => [],
		'small' => [],
	];


	/**
	 * static class.
	 */
	final public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . get_class($this));
	}


	/**
	 * Configure Texy! for web comments and other usages, where input text may insert attacker.
	 * @return void
	 */
	public static function safeMode(Texy $texy)
	{
		$texy->allowedClasses = $texy::NONE; // no class or ID are allowed
		$texy->allowedStyles = $texy::NONE; // style modifiers are disabled
		$texy->allowedTags = self::$safeTags; // only some "safe" HTML tags and attributes are allowed
		$texy->urlSchemeFilters[$texy::FILTER_ANCHOR] = '#https?:|ftp:|mailto:#A';
		$texy->urlSchemeFilters[$texy::FILTER_IMAGE] = '#https?:#A';
		$texy->allowed['image'] = FALSE; // disable images
		$texy->allowed['link/definition'] = FALSE; // disable [ref]: URL reference definitions
		$texy->allowed['html/comment'] = FALSE; // disable HTML comments
		$texy->linkModule->forceNoFollow = TRUE; // force rel="nofollow"
	}


	/**
	 * Disable all links.
	 * @return void
	 */
	public static function disableLinks(Texy $texy)
	{
		$texy->allowed['link/reference'] = FALSE;
		$texy->allowed['link/email'] = FALSE;
		$texy->allowed['link/url'] = FALSE;
		$texy->allowed['link/definition'] = FALSE;
		$texy->phraseModule->linksAllowed = FALSE;

		if (is_array($texy->allowedTags)) {
			unset($texy->allowedTags['a']);
		} // TODO: else...
	}


	/**
	 * Disable all images.
	 * @return void
	 */
	public static function disableImages(Texy $texy)
	{
		$texy->allowed['image'] = FALSE;
		$texy->allowed['figure'] = FALSE;
		$texy->allowed['image/definition'] = FALSE;

		if (is_array($texy->allowedTags)) {
			unset($texy->allowedTags['img'], $texy->allowedTags['object'], $texy->allowedTags['embed'], $texy->allowedTags['applet']);
		} // TODO: else...
	}

}
