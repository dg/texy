<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

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

		if (is_array($texy->htmlPolicy->allowedTags)) {
			unset($texy->htmlPolicy->allowedTags['a']);
		} // TODO: else...
	}


	/**
	 * Disable all images.
	 */
	public static function disableImages(Texy $texy): void
	{
		$texy->allowedSyntax[Syntax::Image] = false;
		$texy->allowedSyntax[Syntax::Figure] = false;
		$texy->allowedSyntax[Syntax::ImageDefinition] = false;

		if (is_array($texy->htmlPolicy->allowedTags)) {
			unset(
				$texy->htmlPolicy->allowedTags['img'],
				$texy->htmlPolicy->allowedTags['object'],
				$texy->htmlPolicy->allowedTags['embed'],
				$texy->htmlPolicy->allowedTags['applet'],
			);
		} // TODO: else...
	}
}
