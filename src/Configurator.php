<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function is_int, is_string;


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
	/** tag => list of allowed attributes */
	public const SafeTags = [
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
	 * Images and figures are disabled unless $images allows them, the definitions of images stay disabled.
	 */
	public static function safeMode(Texy $texy, bool $images = false): void
	{
		self::restrictPolicies($texy->htmlPolicy, $texy->htmlOutput);
		if ($images) {
			$texy->allowedSyntax[Syntax::ImageDefinition] = false;
		} else {
			self::disableImages($texy);
		}
		$texy->allowedSyntax[Syntax::LinkDefinition] = false; // disable [ref]: URL reference definitions
		$texy->allowedSyntax[Syntax::HtmlComment] = false; // disable HTML comments
	}


	/**
	 * The restrictions of safe mode that do not depend on the language.
	 */
	public static function restrictPolicies(HtmlPolicy $htmlPolicy, Output\Html\Config $htmlOutput): void
	{
		$htmlPolicy->allowedClasses = false; // no class or ID are allowed
		$htmlPolicy->allowedStyles = false; // style modifiers are disabled
		// only some "safe" HTML tags and attributes are allowed; the '*' list names
		// attributes acceptable on any element and is the only source for modifiers
		$htmlPolicy->allowedTags = self::SafeTags + ['*' => ['title', 'lang', 'dir']];
		$htmlPolicy->urlPolicy->linkPattern = '~https?:|ftp:|mailto:~A';
		$htmlPolicy->urlPolicy->imagePattern = '~https?:~A';
		$htmlOutput->linkNoFollow = true; // force rel="nofollow"
	}


	/**
	 * Disable all links.
	 */
	public static function disableLinks(Texy $texy): void
	{
		$texy->allowedSyntax[Syntax::AutolinkEmail] = false;
		$texy->allowedSyntax[Syntax::AutolinkUrl] = false;
		$texy->allowedSyntax[Syntax::LinkDefinition] = false;
		$texy->allowedSyntax[Syntax::LinkReference] = false;
		$texy->config->phraseLinks = false;
		self::disallowTags($texy, 'a');
	}


	/**
	 * Disable all images.
	 */
	public static function disableImages(Texy $texy): void
	{
		$texy->allowedSyntax[Syntax::Image] = false;
		$texy->allowedSyntax[Syntax::Figure] = false;
		$texy->allowedSyntax[Syntax::ImageDefinition] = false;
		self::disallowTags($texy, 'img', 'object', 'embed', 'applet');
	}


	private static function disallowTags(Texy $texy, string ...$names): void
	{
		$tags = $texy->htmlPolicy->allowedTags;
		if ($tags === false) {
			return;
		}

		$texy->htmlPolicy->allowedTags = array_filter(
			$tags === true ? Output\Html\Schema::defaultAllowedTags() : $tags,
			fn($value, $key) => !in_array(strtolower(is_int($key) && is_string($value) ? $value : (string) $key), $names, true),
			ARRAY_FILTER_USE_BOTH,
		);
	}
}
