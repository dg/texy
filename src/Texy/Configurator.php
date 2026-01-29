<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;

use function is_array;


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
	/** @var array<string, list<string>> */
	public static array $safeTags = [
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
		throw new \LogicException('Cannot instantiate static class ' . static::class);
	}


	/**
	 * Configure Texy! for web comments and other usages, where input text may insert attacker.
	 */
	public static function safeMode(Texy $texy): void
	{
		$texy->allowedClasses = $texy::NONE; // no class or ID are allowed
		$texy->allowedStyles = $texy::NONE; // style modifiers are disabled
		$texy->allowedTags = self::$safeTags; // only some "safe" HTML tags and attributes are allowed
		$texy->urlSchemeFilters[$texy::FILTER_ANCHOR] = '~https?:|ftp:|mailto:~A';
		$texy->urlSchemeFilters[$texy::FILTER_IMAGE] = '~https?:~A';
		$texy->allowed[Syntax::Image] = false; // disable images
		$texy->allowed[Syntax::LinkDefinition] = false; // disable [ref]: URL reference definitions
		$texy->allowed[Syntax::HtmlComment] = false; // disable HTML comments
		$texy->linkModule->forceNoFollow = true; // force rel="nofollow"
	}


	/**
	 * Disable all links.
	 */
	public static function disableLinks(Texy $texy): void
	{
		$texy->allowed[Syntax::AutolinkEmail] = false;
		$texy->allowed[Syntax::AutolinkUrl] = false;
		$texy->allowed[Syntax::LinkDefinition] = false;
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
		$texy->allowed[Syntax::Image] = false;
		$texy->allowed[Syntax::Figure] = false;
		$texy->allowed[Syntax::ImageDefinition] = false;

		if (is_array($texy->allowedTags)) {
			unset($texy->allowedTags['img'], $texy->allowedTags['object'], $texy->allowedTags['embed'], $texy->allowedTags['applet']);
		} // TODO: else...
	}
}
