<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Regexp;
use function array_intersect, array_keys, array_unshift, is_string, max, reset, rtrim, str_repeat, str_replace, strtr, wordwrap;


/**
 * Formats and validates HTML output (well-forming, indentation, line wrapping).
 */
final class HtmlOutputModule extends Texy\Module
{
	public const InnerTransparent = '%TRANS';
	public const InnerText = '%TEXT';

	/** @var array<string, 1>  void elements */
	public static array $emptyElements = [
		'area' => 1, 'base' => 1, 'br' => 1, 'col' => 1, 'embed' => 1, 'hr' => 1, 'img' => 1, 'input' => 1,
		'link' => 1, 'meta' => 1, 'param' => 1, 'source' => 1, 'track' => 1, 'wbr' => 1,
	];

	/** @var array<string, int>  phrasing elements; replaced elements + br have value 1 */
	public static array $inlineElements = [
		'a' => 0, 'abbr' => 0, 'area' => 0, 'audio' => 0, 'b' => 0, 'bdi' => 0, 'bdo' => 0, 'br' => 1, 'button' => 1, 'canvas' => 1,
		'cite' => 0, 'code' => 0, 'data' => 0, 'datalist' => 0, 'del' => 0, 'dfn' => 0, 'em' => 0, 'embed' => 1, 'i' => 0, 'iframe' => 1,
		'img' => 1, 'input' => 1, 'ins' => 0, 'kbd' => 0, 'label' => 0, 'link' => 0, 'map' => 0, 'mark' => 0, 'math' => 1, 'meta' => 0,
		'meter' => 1, 'noscript' => 1, 'object' => 1, 'output' => 1, 'picture' => 1, 'progress' => 1, 'q' => 0, 'ruby' => 0, 's' => 0,
		'samp' => 0, 'script' => 1, 'select' => 1, 'slot' => 0, 'small' => 0, 'span' => 0, 'strong' => 0, 'sub' => 0, 'sup' => 0,
		'svg' => 1, 'template' => 0, 'textarea' => 1, 'time' => 0, 'u' => 0, 'var' => 0, 'video' => 1, 'wbr' => 0,
	];

	/** @var array<string, 1>  elements with optional end tag in HTML */
	public static array $optionalEnds = [
		'body' => 1, 'head' => 1, 'html' => 1, 'colgroup' => 1, 'dd' => 1, 'dt' => 1, 'li' => 1,
		'option' => 1, 'p' => 1, 'tbody' => 1, 'td' => 1, 'tfoot' => 1, 'th' => 1, 'thead' => 1, 'tr' => 1,
	];

	/** @var array<string, list<string>>  deep element prohibitions */
	public static array $prohibits = [
		'a' => ['a', 'button'],
		'button' => ['a', 'button'],
		'form' => ['form'],
	];

	/**
	 * Content model for HTML well-forming (simplified).
	 * @var array<string, array<string, 1>>
	 */
	public static array $contentModel = [
		// Tables
		'table' => ['caption' => 1, 'colgroup' => 1, 'thead' => 1, 'tbody' => 1, 'tfoot' => 1, 'tr' => 1],
		'thead' => ['tr' => 1],
		'tbody' => ['tr' => 1],
		'tfoot' => ['tr' => 1],
		'tr' => ['th' => 1, 'td' => 1],
		'colgroup' => ['col' => 1],
		// Lists
		'ul' => ['li' => 1],
		'ol' => ['li' => 1],
		'dl' => ['dt' => 1, 'dd' => 1],
		// Transparent content model (inherit from parent)
		'a' => [self::InnerTransparent => 1],
		'ins' => [self::InnerTransparent => 1],
		'del' => [self::InnerTransparent => 1],
		'figure' => ['figcaption' => 1, self::InnerTransparent => 1],
		'fieldset' => ['legend' => 1, self::InnerTransparent => 1],
		'object' => ['param' => 1, self::InnerTransparent => 1],
		'noscript' => [self::InnerTransparent => 1],
		// Text-only
		'script' => [self::InnerText => 1],
		'style' => [self::InnerText => 1],
		'textarea' => [self::InnerText => 1],
		// Empty content
		'iframe' => [],
	];

	/** indent HTML code? */
	public bool $indent = true;

	/** @var string[] */
	public array $preserveSpaces = ['textarea', 'pre', 'script', 'code', 'samp', 'kbd'];

	/** base indent level */
	public int $baseIndent = 0;

	/** wrap width, doesn't include indent space */
	public int $lineWrap = 80;

	/** @var array<string, 1>  block elements with phrasing content */
	private static array $phrasingElements = [
		'p' => 1, 'h1' => 1, 'h2' => 1, 'h3' => 1, 'h4' => 1, 'h5' => 1, 'h6' => 1,
		'pre' => 1, 'legend' => 1, 'caption' => 1, 'figcaption' => 1, 'summary' => 1,
	];

	/** indent space counter */
	private int $space = 0;

	/** @var array<string, int> */
	private array $tagUsed = [];

	/** @var array<int, array{tag: string, open: ?string, close: ?string, dtdContent: array<string, int>, indent: int}> */
	private array $tagStack = [];

	/** @var array<string, int>  content DTD used, when context is not defined */
	private array $baseDTD = [];


	public function __construct(Texy\Texy $texy)
	{
		$texy->addHandler('postProcess', $this->postProcess(...));
	}


	/**
	 * Converts <strong><em> ... </strong> ... </em>.
	 * into <strong><em> ... </em></strong><em> ... </em>
	 */
	private function postProcess(string &$s): void
	{
		$this->space = $this->baseIndent;
		$this->tagStack = [];
		$this->tagUsed = [];

		$this->baseDTD = self::getFlowContent();

		// wellform and reformat
		$s = Regexp::replace(
			$s . '</end/>',
			'~
				( [^<]*+ )
				< (?: (!--.*--) | (/?) ([a-z][a-z0-9._:-]*) (|[ \n].*) \s* (/?) ) >
			~Uis',
			$this->processFragment(...),
		);

		// empty out stack
		foreach ($this->tagStack as $item) {
			$s .= $item['close'];
		}

		// right trim
		$s = Regexp::replace($s, '~[\t ]+(\n|\r|$)~', '$1'); // right trim

		// join double \r to single \n
		$s = str_replace("\r\r", "\n", $s);
		$s = strtr($s, "\r", "\n");

		// greedy chars
		$s = Regexp::replace($s, '~\x07\ *~', '');
		// back-tabs
		$s = Regexp::replace($s, '~\t?\ *\x08~', '');

		// line wrap
		if ($this->lineWrap > 0) {
			$s = Regexp::replace(
				$s,
				'~^(\t*)(.*)$~m',
				$this->wrap(...),
			);
		}
	}


	/**
	 * Processes a fragment of HTML: text content followed by a tag or comment.
	 * @param  array<?string>  $matches
	 */
	private function processFragment(array $matches): string
	{
		// html tag
		/** @var array{string, string, ?string, ?string, ?string, ?string, ?string} $matches */
		[, $mText, $mComment, $mEnd, $mTag, $mAttr, $mEmpty] = $matches;
		// [1] => text
		// [1] => !-- comment --
		// [2] => /
		// [3] => TAG
		// [4] => ... (attributes)
		// [5] => / (empty)

		$s = '';

		// phase #1 - stuff between tags
		if ($mText !== '') {
			$item = reset($this->tagStack);
			if ($item && !isset($item['dtdContent'][self::InnerText])) {  // text not allowed?

			} elseif (array_intersect(array_keys($this->tagUsed, filter_value: true, strict: false), $this->preserveSpaces)) { // inside pre & textarea preserve spaces
				$s = Texy\Helpers::freezeSpaces($mText);

			} else {
				$s = Regexp::replace($mText, '~[ \n]+~', ' '); // otherwise shrink multiple spaces
			}
		}

		// phase #2 - HTML comment
		if ($mComment) {
			return $s . '<' . Texy\Helpers::freezeSpaces($mComment) . '>';
		}

		// phase #3 - HTML tag
		assert(is_string($mTag) && is_string($mAttr));
		$mEmpty = $mEmpty || isset(self::$emptyElements[$mTag]);
		if ($mEmpty && $mEnd) { // bad tag; /end/
			return $s;
		} elseif ($mEnd) {
			return $s . $this->processEndTag($mTag);
		} else {
			return $this->processStartTag($mTag, $mEmpty, $mAttr, $s);
		}
	}


	private function processStartTag(string $tag, bool $empty, string $attr, string $s): string
	{
		$dtdContent = $this->baseDTD;

		if (!self::isKnownTag($tag)) {
			// Unknown tags (custom elements) are always allowed and inherit content model
			// from parent - they act as transparent wrappers that don't restrict children
			$allowed = true;
			$item = reset($this->tagStack);
			if ($item) {
				$dtdContent = $item['dtdContent'];
			}
		} else {
			// Known HTML tags must respect content model rules
			$s .= $this->closeOptionalTags($tag, $dtdContent);
			$allowed = isset($dtdContent[$tag]);

			// Deep prohibitions: certain elements cannot be nested anywhere inside others
			// (e.g., <a> cannot contain <a> or <button> at any depth)
			if ($allowed && isset(self::$prohibits[$tag])) {
				foreach (self::$prohibits[$tag] as $pTag) {
					if (!empty($this->tagUsed[$pTag])) {
						$allowed = false;
						break;
					}
				}
			}
		}

		// Void elements don't go on stack - they have no closing tag
		if ($empty) {
			if (!$allowed) {
				return $s;
			}

			$indent = $this->indent && !array_intersect(array_keys($this->tagUsed, filter_value: true, strict: false), $this->preserveSpaces);

			if ($indent && $tag === 'br') {
				return rtrim($s) . '<' . $tag . $attr . ">\n" . str_repeat("\t", max(0, $this->space - 1)) . "\x07";

			} elseif ($indent && !isset(self::$inlineElements[$tag])) {
				$space = "\r" . str_repeat("\t", $this->space);
				return $s . $space . '<' . $tag . $attr . '>' . $space;

			} else {
				return $s . '<' . $tag . $attr . '>';
			}
		}

		$open = null;
		$close = null;
		$indent = 0;

		if ($allowed) {
			$open = '<' . $tag . $attr . '>';

			// Determine what children this tag can contain
			$dtdContent = self::isKnownTag($tag)
				? $this->getChildContent($tag, $dtdContent)
				: $dtdContent; // unknown tags keep inherited content model

			// Format output with indentation for block elements
			if ($this->indent && !isset(self::$inlineElements[$tag])) {
				$close = "\x08" . '</' . $tag . '>' . "\n" . str_repeat("\t", $this->space);
				$s .= "\n" . str_repeat("\t", $this->space++) . $open . "\x07";
				$indent = 1;
			} else {
				$close = '</' . $tag . '>';
				$s .= $open;
			}
		}

		// Push tag to stack for tracking open elements
		$item = [
			'tag' => $tag,
			'open' => $open,
			'close' => $close,
			'dtdContent' => $dtdContent,
			'indent' => $indent,
		];
		array_unshift($this->tagStack, $item);
		$tmp = &$this->tagUsed[$tag];
		$tmp++;

		return $s;
	}


	/**
	 * Determines what content (child elements) a known tag can contain.
	 * @param  array<string, int>  $parentContent
	 * @return array<string, int>
	 */
	private function getChildContent(string $tag, array $parentContent): array
	{
		$tagContent = self::$contentModel[$tag] ?? null;

		if ($tagContent !== null) {
			if (isset($tagContent[self::InnerTransparent])) {
				// Transparent: inherits parent's content model plus its own additions
				$parentContent += $tagContent;
				unset($parentContent[self::InnerTransparent]);
				return $parentContent;
			}
			if ($tagContent === []) {
				// Empty content model (e.g., iframe) - no children allowed
				return [];
			}
			// Explicit content model (e.g., table, ul)
			return $tagContent + [self::InnerText => 1];
		}

		if (isset(self::$inlineElements[$tag]) || isset(self::$phrasingElements[$tag])) {
			// Phrasing content only (e.g., <p>, <span>)
			return self::getPhrasingContent();
		}

		// Block element - allows flow content (e.g., <div>)
		return self::getFlowContent();
	}


	private function processEndTag(string $tag): string
	{
		// has start tag?
		if (empty($this->tagUsed[$tag])) {
			return '';
		}

		// autoclose tags
		$tmp = [];
		$back = true;
		$s = '';
		foreach ($this->tagStack as $i => $item) {
			$itemTag = $item['tag'];
			$s .= $item['close'];
			$this->space -= $item['indent'];
			$this->tagUsed[$itemTag]--;
			$back = $back && isset(self::$inlineElements[$itemTag]);
			unset($this->tagStack[$i]);
			if ($itemTag === $tag) {
				break;
			}

			array_unshift($tmp, $item);
		}

		if (!$back || !$tmp) {
			return $s;
		}

		// allowed-check (nejspis neni ani potreba)
		$item = reset($this->tagStack);
		$dtdContent = $item ? $item['dtdContent'] : $this->baseDTD;
		if (!isset($dtdContent[$tmp[0]['tag']])) {
			return $s;
		}

		// autoopen tags
		foreach ($tmp as $item) {
			$s .= $item['open'];
			$this->space += $item['indent'];
			$this->tagUsed[$item['tag']]++;
			array_unshift($this->tagStack, $item);
		}

		return $s;
	}


	/** @param  array<string, int>  $dtdContent */
	private function closeOptionalTags(string $tag, array &$dtdContent): string
	{
		$s = '';
		foreach ($this->tagStack as $i => $item) {
			// is tag allowed here?
			$dtdContent = $item['dtdContent'];
			if (isset($dtdContent[$tag])) {
				break;
			}

			$itemTag = $item['tag'];

			// auto-close hidden, optional and inline tags
			if (
				$item['close']
				&& (
					!isset(self::$optionalEnds[$itemTag])
					&& !isset(self::$inlineElements[$itemTag])
				)
			) {
				break;
			}

			// close it
			$s .= $item['close'];
			$this->space -= $item['indent'];
			$this->tagUsed[$itemTag]--;
			unset($this->tagStack[$i]);
			$dtdContent = $this->baseDTD;
		}

		return $s;
	}


	/**
	 * Callback function: wrap lines.
	 * @param  array<?string>  $m
	 */
	private function wrap(array $m): string
	{
		/** @var array{string, string, string} $m */
		[, $space, $s] = $m;
		return $space . wordwrap($s, $this->lineWrap, "\n" . $space);
	}


	private static function isKnownTag(string $tag): bool
	{
		return isset(self::$inlineElements[$tag])
			|| isset(self::$emptyElements[$tag])
			|| isset(self::$optionalEnds[$tag])
			|| isset(self::$contentModel[$tag])
			|| isset(self::$prohibits[$tag])
			|| isset(self::$phrasingElements[$tag])
			|| isset(self::getFlowContent()[$tag]);
	}


	/** @return array<string, 1> */
	private static function getFlowContent(): array
	{
		static $content;
		return $content ??= self::$inlineElements
			+ ['div' => 1, 'p' => 1, 'ul' => 1, 'ol' => 1, 'dl' => 1, 'table' => 1,
				'blockquote' => 1, 'pre' => 1, 'figure' => 1, 'hr' => 1, 'address' => 1,
				'h1' => 1, 'h2' => 1, 'h3' => 1, 'h4' => 1, 'h5' => 1, 'h6' => 1,
				'header' => 1, 'footer' => 1, 'main' => 1, 'article' => 1, 'section' => 1, 'nav' => 1, 'aside' => 1,
				'form' => 1, 'fieldset' => 1, 'html' => 1, 'head' => 1, 'body' => 1, self::InnerText => 1];
	}


	/** @return array<string, 1> */
	private static function getPhrasingContent(): array
	{
		static $content;
		return $content ??= self::$inlineElements + [self::InnerText => 1];
	}
}
