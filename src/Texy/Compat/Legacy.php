<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Compat;

use Texy\Texy;
use function array_key_exists, is_array, is_string, sprintf;


/**
 * Maps v3 property names to their new homes. A target is [property of Texy, property name],
 * a string with a navigation message for removed properties that have a successor,
 * or null for properties removed without replacement.
 * @internal
 */
final class Legacy
{
	public const OfTexy = [
		'allowedTags' => ['htmlPolicy', 'allowedTags'],
		'allowedClasses' => ['htmlPolicy', 'allowedClasses'],
		'allowedStyles' => ['htmlPolicy', 'allowedStyles'],
		'alignClasses' => ['htmlOutput', 'alignClasses'],
		'obfuscateEmail' => ['htmlOutput', 'obfuscateEmail'],
		'nontextParagraph' => ['htmlOutput', 'nontextParagraph'],
		'urlSchemeFilters' => 'use $texy->urlPolicy',
		'summary' => null,
	];

	public const OfModule = [
		// modules that no longer exist; $texy->NAME is served by LegacyModuleProxy
		'horizLineModule' => [
			'classes' => ['htmlOutput', 'horizontalRuleClasses'],
		],
		'htmlModule' => [
			'passComment' => ['htmlOutput', 'passHtmlComments'],
		],
		'htmlOutputModule' => [
			'indent' => ['htmlOutput', 'indent'],
			'preserveSpaces' => ['htmlOutput', 'preserveSpaces'],
			'baseIndent' => ['htmlOutput', 'baseIndent'],
			'lineWrap' => ['htmlOutput', 'lineWrap'],
		],
		'tableModule' => [
			'oddClass' => null,
			'evenClass' => null,
		],
		'scriptModule' => [
			'separator' => null,
		],

		// modules that still exist; served by their own __get/__set
		'imageModule' => [
			'root' => ['htmlOutput', 'imageRoot'],
			'fileRoot' => ['htmlOutput', 'imageFileRoot'],
			'leftClass' => ['htmlOutput', 'imageLeftClass'],
			'rightClass' => ['htmlOutput', 'imageRightClass'],
			'linkedRoot' => null,
			'defaultAlt' => null,
		],
		'linkModule' => [
			'root' => ['htmlOutput', 'linkRoot'],
			'forceNoFollow' => ['htmlOutput', 'linkNoFollow'],
			'shorten' => ['htmlOutput', 'shortenUrls'],
			'imageClass' => null,
		],
		'phraseModule' => [
			'tags' => ['htmlOutput', 'phraseTags'],
		],
		'emoticonModule' => [
			'class' => ['htmlOutput', 'emoticonClass'],
			'root' => 'emoticons render as Unicode characters, image files are not used',
			'fileRoot' => 'emoticons render as Unicode characters, image files are not used',
		],
		'headingModule' => [
			'title' => 'use HeadingNode::collectFrom($document)[0]?->getTitle()',
			'TOC' => 'use HeadingNode::collectFrom($document)',
		],
	];

	/** v3 methods removed without emulation; the value navigates to the successor */
	public const RemovedMethods = [
		'registerPostLine' => 'the string post-processing pipeline was removed, typography runs as an AST pass',
		'checkURL' => 'use $texy->urlPolicy->isLinkAllowed() or isImageAllowed()',
		'stringToHtml' => 'use process()',
		'stringToText' => 'use toText()',
		'getDOM' => 'use parse(), which returns the document AST',
		'getLinePatterns' => 'internal API removed without replacement',
		'getBlockPatterns' => 'internal API removed without replacement',
		'getAllowedProps' => 'internal API removed without replacement',
	];


	/**
	 * Serves v3 method calls: removed methods throw with a pointer to the successor.
	 * @param  array<mixed>  $args
	 */
	public static function call(Texy $texy, string $name, array $args): mixed
	{
		if ($hint = self::RemovedMethods[$name] ?? null) {
			throw new \LogicException("Method Texy::$name() has been removed; $hint.");
		}

		throw new \LogicException(sprintf('Call to undefined method %s::%s().', Texy::class, $name));
	}


	/**
	 * Returns a reference to the new location of a legacy property, so that writes
	 * into arrays ($texy->horizLineModule->classes['-'] = ...) keep working.
	 * @param  array<string, array{string, string}|string|null>  $map
	 */
	public static function &ref(Texy $texy, array $map, string $prefix, string $name, string $access): mixed
	{
		if (!array_key_exists($name, $map)) {
			throw new \LogicException("Cannot $access an undeclared property $prefix->\$$name.");
		}

		$target = $map[$name];
		if ($target === null || is_string($target)) {
			$hint = is_string($target) ? "; $target" : ' and has no replacement';
			trigger_error("Property $prefix->$name has been removed$hint.", E_USER_WARNING);
			$removed = null;
			return $removed;
		}

		[$holder, $prop] = $target;
		trigger_error("Property $prefix->$name is deprecated, use \$texy->$holder->$prop instead.", E_USER_DEPRECATED);
		return $texy->$holder->$prop;
	}


	/** @param  array<string, array{string, string}|string|null>  $map */
	public static function set(Texy $texy, array $map, string $prefix, string $name, mixed $value): void
	{
		$ref = &self::ref($texy, $map, $prefix, $name, 'write to');
		$ref = $value;
	}


	/** @param  array<string, array{string, string}|string|null>  $map */
	public static function isSet(Texy $texy, array $map, string $name): bool
	{
		$target = $map[$name] ?? null;
		return is_array($target) && isset($texy->{$target[0]}->{$target[1]});
	}
}
