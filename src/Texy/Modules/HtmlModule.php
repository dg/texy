<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\HtmlElement;
use Texy\Patterns;
use Texy\Regexp;
use function array_flip, count, explode, is_array, is_string, str_contains, str_ends_with, strtolower, strtoupper, strtr, substr, trim;


/**
 * Processes HTML tags and comments in input text.
 */
final class HtmlModule extends Texy\Module
{
	/** pass HTML comments to output? */
	public bool $passComment = true;


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->addHandler('htmlComment', $this->solveComment(...));
		$texy->addHandler('htmlTag', $this->solveTag(...));

		$texy->registerLinePattern(
			$this->parseTag(...),
			'~
				< (/?)                          # tag begin
				([a-z][a-z0-9_:-]{0,50})        # tag name
				(
					(?:
						\s++ [a-z0-9_:-]++ |   # attribute name
						= \s*+ " [^"' . Patterns::MARK . ']*+ " |     # attribute value in double quotes
						= \s*+ \' [^\'' . Patterns::MARK . ']*+ \' |  # attribute value in single quotes
						= [^\s>' . Patterns::MARK . ']++              # attribute value without quotes
					)*
				)
				\s*+
				(/?)                             # self-closing slash
				>
			~is',
			'html/tag',
		);

		$texy->registerLinePattern(
			$this->parseComment(...),
			'~
				<!--
				( [^' . Patterns::MARK . ']*? )
				-->
			~is',
			'html/comment',
		);
	}


	/**
	 * Parses <!-- comment -->
	 * @param  array<?string>  $matches
	 */
	public function parseComment(Texy\InlineParser $parser, array $matches): HtmlElement|string|null
	{
		[, $mComment] = $matches;
		return $this->texy->invokeAroundHandlers('htmlComment', $parser, [$mComment]);
	}


	/**
	 * Parses <tag attr="...">
	 * @param  array<?string>  $matches
	 */
	public function parseTag(Texy\InlineParser $parser, array $matches): ?string
	{
		/** @var array{string, string, string, string, string} $matches */
		[, $mEnd, $mTag, $mAttr, $mEmpty] = $matches;
		// [1] => /
		// [2] => tag
		// [3] => attributes
		// [4] => /

		$isStart = $mEnd !== '/';
		$isEmpty = $mEmpty === '/';
		if (!$isEmpty && str_ends_with($mAttr, '/')) { // uvizlo v $mAttr?
			$mAttr = substr($mAttr, 0, -1);
			$isEmpty = true;
		}

		// error - can't close empty element
		if ($isEmpty && !$isStart) {
			return null;
		}

		// error - end element with atttrs
		$mAttr = trim(strtr($mAttr, "\n", ' '));
		if ($mAttr && !$isStart) {
			return null;
		}

		$el = new HtmlElement($mTag);
		if ($isStart) {
			$el->attrs = $this->parseAttributes($mAttr);
		}

		$res = $this->texy->invokeAroundHandlers('htmlTag', $parser, [$el, $isStart, $isEmpty]);

		if ($res instanceof HtmlElement) {
			return $this->texy->protect($isStart ? $res->startTag() : $res->endTag(), $res->getContentType());
		}

		return $res;
	}


	/**
	 * Finish invocation.
	 */
	private function solveTag(
		Texy\HandlerInvocation $invocation,
		HtmlElement $el,
		bool $isStart,
		?bool $forceEmpty = null,
	): ?HtmlElement
	{
		$texy = $this->texy;

		// tag & attibutes
		$allowedTags = $texy->allowedTags; // speed-up
		if (!$allowedTags) {
			return null; // all tags are disabled
		}

		// convert case
		$name = $el->getName();
		assert($name !== null);
		$lower = strtolower($name);
		if (isset($texy->getDTD()[$lower]) || $name === strtoupper($name)) {
			// complete UPPER convert to lower
			$name = $lower;
			$el->setName($name);
		}

		if (is_array($allowedTags)) {
			if (!isset($allowedTags[$name])) {
				return null;
			}
		} else { // allowedTags === Texy\Texy::ALL
			if ($forceEmpty) {
				$el->setName($name, empty: true);
			}
		}

		// end tag? we are finished
		if (!$isStart) {
			return $el;
		}

		$this->applyAttrs($el->attrs, is_array($allowedTags) ? $allowedTags[$name] : $texy::ALL);
		$this->applyClasses($el->attrs, $texy->getAllowedProps()[0]);
		$this->applyStyles($el->attrs, $texy->getAllowedProps()[1]);
		if (!$this->validateAttrs($el, $texy)) {
			return null;
		}

		$el->validateAttrs($texy->getDTD());

		return $el;
	}


	/**
	 * Finish invocation.
	 */
	private function solveComment(Texy\HandlerInvocation $invocation, string $content): string
	{
		if (!$this->passComment) {
			return '';
		}

		// sanitize comment
		$content = Regexp::replace($content, '~-{2,}~', ' - ');
		$content = trim($content, '-');

		return $this->texy->protect('<!--' . $content . '-->', Texy\Texy::CONTENT_MARKUP);
	}


	/**
	 * @param  array<string, array<string|int|bool>|string|int|bool|null>  $attrs
	 * @param  bool|string[]  $allowedAttrs
	 */
	private function applyAttrs(array &$attrs, bool|array $allowedAttrs): void
	{
		if (!$allowedAttrs) {
			$attrs = [];

		} elseif (is_array($allowedAttrs)) {
			// skip disabled
			$allowedAttrs = array_flip($allowedAttrs);
			foreach ($attrs as $key => $foo) {
				if (!isset($allowedAttrs[$key])) {
					unset($attrs[$key]);
				}
			}
		}
	}


	/**
	 * @param  array<string, string|int|bool|array<string|int|bool>|null>  $attrs
	 * @param  array<string, int>|bool  $allowedClasses
	 */
	private function applyClasses(array &$attrs, bool|array $allowedClasses): void
	{
		if (!isset($attrs['class'])) {
		} elseif (is_array($allowedClasses)) {
			$attrs['class'] = is_string($attrs['class']) ? explode(' ', $attrs['class']) : (array) $attrs['class'];
			foreach ($attrs['class'] as $key => $value) {
				if (!isset($allowedClasses[$value])) {
					unset($attrs['class'][$key]); // id & class are case-sensitive
				}
			}
		} elseif ($allowedClasses !== Texy\Texy::ALL) {
			$attrs['class'] = null;
		}

		if (!isset($attrs['id'])) {
		} elseif (is_array($allowedClasses)) {
			if (!is_string($attrs['id']) || !isset($allowedClasses['#' . $attrs['id']])) {
				$attrs['id'] = null;
			}

		} elseif ($allowedClasses !== Texy\Texy::ALL) {
			$attrs['id'] = null;
		}
	}


	/**
	 * @param  array<string, string|int|bool|array<string|int|bool>|null>  $attrs
	 * @param  array<string, int>|bool  $allowedStyles
	 */
	private function applyStyles(array &$attrs, bool|array $allowedStyles): void
	{
		if (!isset($attrs['style'])) {
		} elseif (is_array($allowedStyles)) {
			if (is_string($attrs['style'])) {
				$parts = explode(';', $attrs['style']);
				$attrs['style'] = [];
				foreach ($parts as $value) {
					if (count($pair = explode(':', $value, 2)) === 2) {
						$attrs['style'][trim($pair[0])] = trim($pair[1]);
					}
				}
			} else {
				$attrs['style'] = (array) $attrs['style'];
			}

			foreach ($attrs['style'] as $key => $value) {
				if (!isset($allowedStyles[strtolower((string) $key)])) { // CSS is case-insensitive
					unset($attrs['style'][$key]);
				}
			}
		} elseif ($allowedStyles !== Texy\Texy::ALL) {
			$attrs['style'] = null;
		}
	}


	private function validateAttrs(HtmlElement $el, Texy\Texy $texy): bool
	{
		foreach (['src', 'href', 'name', 'id'] as $attr) {
			if (isset($el->attrs[$attr])) {
				$el->attrs[$attr] = is_string($el->attrs[$attr])
					? trim($el->attrs[$attr])
					: '';
				if ($el->attrs[$attr] === '') {
					unset($el->attrs[$attr]);
				}
			}
		}

		$name = $el->getName();
		if ($name === 'img') {
			if (!isset($el->attrs['src'])) {
				return false;
			}

			assert(is_string($el->attrs['src']));
			if (!$texy->checkURL($el->attrs['src'], $texy::FILTER_IMAGE)) {
				return false;
			}

			$texy->summary['images'][] = $el->attrs['src'];

		} elseif ($name === 'a') {
			if (!isset($el->attrs['href']) && !isset($el->attrs['name']) && !isset($el->attrs['id'])) {
				return false;
			}

			if (isset($el->attrs['href'])) {
				assert(is_string($el->attrs['href']));
				if ($texy->linkModule->forceNoFollow && str_contains($el->attrs['href'], '//')) {
					settype($el->attrs['rel'], 'array');
					$el->attrs['rel'][] = 'nofollow';
				}

				if (!$texy->checkURL($el->attrs['href'], $texy::FILTER_ANCHOR)) {
					return false;
				}

				$texy->summary['links'][] = $el->attrs['href'];
			}

		} elseif (Regexp::match($name ?? '', '~^h[1-6]~i')) {
			$texy->headingModule->TOC[] = [
				'el' => $el,
				'level' => (int) substr($name, 1),
				'type' => 'html',
			];
		}

		return true;
	}


	/** @return array<string, string|bool> */
	private function parseAttributes(string $attrs): array
	{
		$res = [];
		$matches = Regexp::matchAll(
			$attrs,
			<<<'X'
				~
				([a-z0-9_:-]+)                 # attribute name
				\s*
				(?:
					= \s*                      # equals sign
					(
						' [^']* ' |            # single quoted value
						" [^"]* " |            # double quoted value
						[^'"\s]+               # unquoted value
					)
				)?
				~is
				X,
		);

		/** @var array{string, string, ?string} $m */
		foreach ($matches as $m) {
			$key = strtolower($m[1]);
			$value = $m[2];
			if ($value == null) {
				$res[$key] = true;
			} elseif ($value[0] === '\'' || $value[0] === '"') {
				$res[$key] = Texy\Helpers::unescapeHtml(substr($value, 1, -1));
			} else {
				$res[$key] = Texy\Helpers::unescapeHtml($value);
			}
		}

		return $res;
	}
}
