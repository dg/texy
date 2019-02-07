<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

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
		'abbr' => ['title'],
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
	 */
	public static function safeMode(Texy $texy): void
	{
		$texy->allowedClasses = $texy::NONE; // no class or ID are allowed
		$texy->allowedStyles = $texy::NONE; // style modifiers are disabled
		$texy->allowedTags = self::$safeTags; // only some "safe" HTML tags and attributes are allowed
		$texy->urlSchemeFilters[$texy::FILTER_ANCHOR] = '#https?:|ftp:|mailto:#A';
		$texy->urlSchemeFilters[$texy::FILTER_IMAGE] = '#https?:#A';
		$texy->allowed['image'] = false; // disable images
		$texy->allowed['link/definition'] = false; // disable [ref]: URL reference definitions
		$texy->allowed['html/comment'] = false; // disable HTML comments
		$texy->linkModule->forceNoFollow = true; // force rel="nofollow"
	}


	/**
	 * Disable all links.
	 */
	public static function disableLinks(Texy $texy): void
	{
		$texy->allowed['link/reference'] = false;
		$texy->allowed['link/email'] = false;
		$texy->allowed['link/url'] = false;
		$texy->allowed['link/definition'] = false;
		$texy->phraseModule->linksAllowed = false;

		if (is_array($texy->allowedTags)) {
			unset($texy->allowedTags['a']);
		} // TODO: else...
	}


	/**
	 * Disable all images.
	 */
	public static function disableImages(Texy $texy): void
	{
		$texy->allowed['image'] = false;
		$texy->allowed['figure'] = false;
		$texy->allowed['image/definition'] = false;

		if (is_array($texy->allowedTags)) {
			unset($texy->allowedTags['img'], $texy->allowedTags['object'], $texy->allowedTags['embed'], $texy->allowedTags['applet']);
		} // TODO: else...
	}
}
