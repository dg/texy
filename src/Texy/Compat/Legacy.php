<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Compat;

use Texy\Texy;
use function array_key_exists;


/**
 * Maps v3 property names to their new homes. A target is [property of Texy, property name],
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
		'summary' => null,
	];

	public const OfModule = [
		// modules that no longer exist; $texy->NAME is served by LegacyModuleProxy
		'figureModule' => [
			'tagName' => ['htmlOutput', 'figureTagName'],
			'class' => ['htmlOutput', 'figureClass'],
			'leftClass' => ['htmlOutput', 'figureLeftClass'],
			'rightClass' => ['htmlOutput', 'figureRightClass'],
			'widthDelta' => null,
			'requireCaption' => null,
		],
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
		],
	];


	/**
	 * Returns a reference to the new location of a legacy property, so that writes
	 * into arrays ($texy->horizLineModule->classes['-'] = ...) keep working.
	 * @param  array<string, array{string, string}|null>  $map
	 */
	public static function &ref(Texy $texy, array $map, string $prefix, string $name, string $access): mixed
	{
		if (!array_key_exists($name, $map)) {
			throw new \LogicException("Cannot $access an undeclared property $prefix->\$$name.");
		}

		$target = $map[$name];
		if ($target === null) {
			trigger_error("Property $prefix->$name has been removed and has no replacement.", E_USER_WARNING);
			$removed = null;
			return $removed;
		}

		[$holder, $prop] = $target;
		trigger_error("Property $prefix->$name is deprecated, use \$texy->$holder->$prop instead.", E_USER_DEPRECATED);
		return $texy->$holder->$prop;
	}


	/** @param  array<string, array{string, string}|null>  $map */
	public static function set(Texy $texy, array $map, string $prefix, string $name, mixed $value): void
	{
		$ref = &self::ref($texy, $map, $prefix, $name, 'write to');
		$ref = $value;
	}


	/** @param  array<string, array{string, string}|null>  $map */
	public static function isSet(Texy $texy, array $map, string $name): bool
	{
		$target = $map[$name] ?? null;
		return $target !== null && isset($texy->{$target[0]}->{$target[1]});
	}
}
