<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\Texy;
use function array_fill_keys, array_keys, is_array;


/**
 * Declarative HTML vocabulary: a single authoritative per-element table
 * (category, void-ness, optional end tag, content model) plus deep nesting
 * prohibitions. Every element-related view used by well-forming, paragraph
 * analysis and the default tag whitelist is derived from it.
 */
final class Schema
{
	/** content-model markers */
	public const
		Text = '%TEXT',
		Transparent = '%TRANS';

	/** element flags */
	private const
		Inline = 0b000001,       // phrasing element
		Replaced = 0b000010,     // replaced/opaque element (typography must not reach inside)
		Void = 0b000100,         // no end tag
		OptionalEnd = 0b001000,  // end tag may be omitted (auto-close)
		PhrasingOnly = 0b010000, // block element with phrasing-only content
		ChildOnly = 0b100000;    // allowed only inside its parent, not in flow content

	/**
	 * element => flags | [flags, content model]
	 * Content model is a list of allowed children (+ markers); [] means no
	 * content at all. Without it the category default applies: phrasing for
	 * Inline/PhrasingOnly elements, flow content otherwise.
	 */
	private const Elements = [
		// inline markup
		'a' => [self::Inline, [self::Transparent]],
		'abbr' => self::Inline,
		'audio' => [self::Inline, ['source', 'track', self::Transparent]],
		'b' => self::Inline,
		'bdi' => self::Inline,
		'bdo' => self::Inline,
		'cite' => self::Inline,
		'code' => self::Inline,
		'data' => self::Inline,
		'datalist' => self::Inline,
		'del' => [self::Inline, [self::Transparent]],
		'dfn' => self::Inline,
		'em' => self::Inline,
		'i' => self::Inline,
		'ins' => [self::Inline, [self::Transparent]],
		'kbd' => self::Inline,
		'label' => self::Inline,
		'map' => self::Inline,
		'mark' => self::Inline,
		'q' => self::Inline,
		'ruby' => self::Inline,
		's' => self::Inline,
		'samp' => self::Inline,
		'slot' => self::Inline,
		'small' => self::Inline,
		'span' => self::Inline,
		'strong' => self::Inline,
		'sub' => self::Inline,
		'sup' => self::Inline,
		'template' => self::Inline,
		'time' => self::Inline,
		'u' => self::Inline,
		'var' => self::Inline,

		// inline voids
		'area' => self::Inline | self::Void,
		'br' => self::Inline | self::Replaced | self::Void,
		'embed' => self::Inline | self::Replaced | self::Void,
		'img' => self::Inline | self::Replaced | self::Void,
		'input' => self::Inline | self::Replaced | self::Void,
		'link' => self::Inline | self::Void,
		'meta' => self::Inline | self::Void,
		'wbr' => self::Inline | self::Void,

		// inline replaced
		'button' => self::Inline | self::Replaced,
		'canvas' => self::Inline | self::Replaced,
		'iframe' => [self::Inline | self::Replaced, []],
		'math' => self::Inline | self::Replaced,
		'meter' => self::Inline | self::Replaced,
		'noscript' => [self::Inline | self::Replaced, [self::Transparent]],
		'object' => [self::Inline | self::Replaced, ['param', self::Transparent]],
		'output' => self::Inline | self::Replaced,
		'picture' => self::Inline | self::Replaced,
		'progress' => self::Inline | self::Replaced,
		'script' => [self::Inline | self::Replaced, [self::Text]],
		'select' => [self::Inline | self::Replaced, ['option', 'optgroup']],
		'svg' => self::Inline | self::Replaced,
		'textarea' => [self::Inline | self::Replaced, [self::Text]],
		'video' => [self::Inline | self::Replaced, ['source', 'track', self::Transparent]],

		// blocks with phrasing content
		'p' => self::PhrasingOnly | self::OptionalEnd,
		'h1' => self::PhrasingOnly,
		'h2' => self::PhrasingOnly,
		'h3' => self::PhrasingOnly,
		'h4' => self::PhrasingOnly,
		'h5' => self::PhrasingOnly,
		'h6' => self::PhrasingOnly,
		'pre' => self::PhrasingOnly,

		// blocks with flow content
		'address' => 0,
		'article' => 0,
		'aside' => 0,
		'blockquote' => 0,
		'details' => [0, ['summary', self::Transparent]],
		'div' => 0,
		'fieldset' => [0, ['legend', self::Transparent]],
		'figure' => [0, ['figcaption', self::Transparent]],
		'footer' => 0,
		'form' => 0,
		'header' => 0,
		'hr' => self::Void,
		'main' => 0,
		'nav' => 0,
		'section' => 0,
		'body' => self::OptionalEnd,
		'head' => self::OptionalEnd,
		'html' => self::OptionalEnd,

		// tables
		'table' => [0, ['caption', 'colgroup', 'thead', 'tbody', 'tfoot', 'tr']],
		'caption' => self::PhrasingOnly | self::ChildOnly,
		'colgroup' => [self::OptionalEnd | self::ChildOnly, ['col']],
		'col' => self::Void | self::ChildOnly,
		'thead' => [self::OptionalEnd | self::ChildOnly, ['tr']],
		'tbody' => [self::OptionalEnd | self::ChildOnly, ['tr']],
		'tfoot' => [self::OptionalEnd | self::ChildOnly, ['tr']],
		'tr' => [self::OptionalEnd | self::ChildOnly, ['th', 'td']],
		'td' => self::OptionalEnd | self::ChildOnly,
		'th' => self::OptionalEnd | self::ChildOnly,

		// lists
		'ul' => [0, ['li']],
		'ol' => [0, ['li']],
		'li' => self::OptionalEnd | self::ChildOnly,
		'dl' => [0, ['dt', 'dd']],
		'dt' => self::OptionalEnd | self::ChildOnly,
		'dd' => self::OptionalEnd | self::ChildOnly,

		// other child-only elements
		'figcaption' => self::PhrasingOnly | self::ChildOnly,
		'legend' => self::PhrasingOnly | self::ChildOnly,
		'summary' => self::PhrasingOnly | self::ChildOnly,
		'option' => self::OptionalEnd | self::ChildOnly,
		'optgroup' => [self::ChildOnly, ['option']],
		'param' => self::Void | self::ChildOnly,
		'source' => self::Void | self::ChildOnly,
		'track' => self::Void | self::ChildOnly,
		'style' => [self::ChildOnly, [self::Text]],
		'base' => self::Void | self::ChildOnly,
	];

	private const Prohibits = [
		'a' => ['a', 'button'],
		'button' => ['a', 'button'],
		'form' => ['form'],
	];


	public static function isKnown(string $tag): bool
	{
		return isset(self::Elements[$tag]);
	}


	/**
	 * Phrasing elements; replaced elements have value 1, others 0.
	 * @return array<string, 0|1>
	 */
	public static function inlineElements(): array
	{
		static $cache;
		if ($cache === null) {
			$cache = [];
			foreach (self::Elements as $tag => $def) {
				$flags = is_array($def) ? $def[0] : $def;
				if ($flags & self::Inline) {
					$cache[$tag] = $flags & self::Replaced ? 1 : 0;
				}
			}
		}

		return $cache;
	}


	/**
	 * Void elements (no end tag).
	 * @return array<string, 1>
	 */
	public static function voidElements(): array
	{
		static $cache;
		if ($cache === null) {
			$cache = [];
			foreach (self::Elements as $tag => $def) {
				if ((is_array($def) ? $def[0] : $def) & self::Void) {
					$cache[$tag] = 1;
				}
			}
		}

		return $cache;
	}


	public static function hasOptionalEnd(string $tag): bool
	{
		$def = self::Elements[$tag] ?? 0;
		return (bool) ((is_array($def) ? $def[0] : $def) & self::OptionalEnd);
	}


	/** @return list<string> */
	public static function prohibitedAncestors(string $tag): array
	{
		return self::Prohibits[$tag] ?? [];
	}


	/**
	 * Determines what content (child elements) a known tag can contain.
	 * @param  array<string, int>  $parentContent
	 * @return array<string, int>
	 */
	public static function childContent(string $tag, array $parentContent): array
	{
		$model = self::contentModels()[$tag] ?? null;

		if ($model !== null) {
			if (isset($model[self::Transparent])) {
				// Transparent: inherits parent's content model plus its own additions
				$parentContent += $model;
				unset($parentContent[self::Transparent]);
				return $parentContent;
			}
			if ($model === []) {
				// No content allowed at all (e.g., iframe)
				return [];
			}
			// Explicit content model (e.g., table, ul)
			return $model + [self::Text => 1];
		}

		$def = self::Elements[$tag] ?? 0;
		return (is_array($def) ? $def[0] : $def) & (self::Inline | self::PhrasingOnly)
			? self::phrasingContent()
			: self::flowContent();
	}


	/**
	 * Elements allowed in flow content: every known element except child-only ones.
	 * @return array<string, int>
	 */
	public static function flowContent(): array
	{
		static $cache;
		if ($cache === null) {
			$cache = [];
			foreach (self::Elements as $tag => $def) {
				if (!((is_array($def) ? $def[0] : $def) & self::ChildOnly)) {
					$cache[$tag] = 1;
				}
			}

			$cache[self::Text] = 1;
		}

		return $cache;
	}


	/**
	 * Elements allowed in phrasing content.
	 * @return array<string, int>
	 */
	public static function phrasingContent(): array
	{
		static $cache;
		return $cache ??= self::inlineElements() + [self::Text => 1];
	}


	/**
	 * Default tag whitelist: all known tags with all attributes, except
	 * document-level tags and tags Texy does not emit on its own.
	 * @return array<string, bool>
	 */
	public static function defaultAllowedTags(): array
	{
		static $cache;
		if ($cache === null) {
			$cache = array_fill_keys(array_keys(self::Elements), Texy::ALL);
			unset($cache['html'], $cache['head'], $cache['body'], $cache['style']);
		}

		return $cache;
	}


	/** @return array<string, array<string, 1>> */
	private static function contentModels(): array
	{
		static $cache;
		if ($cache === null) {
			$cache = [];
			foreach (self::Elements as $tag => $def) {
				if (is_array($def)) {
					$cache[$tag] = array_fill_keys($def[1], 1);
				}
			}
		}

		return $cache;
	}
}
