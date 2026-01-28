<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Output\Html\Schema;
use Texy\Regexp;
use function array_intersect, array_keys, array_unshift, is_string, max, reset, rtrim, str_repeat, str_replace, strtr, wordwrap;


/**
 * Formats and validates HTML output (well-forming, indentation, line wrapping).
 */
final class HtmlOutputModule extends Texy\Module
{
	/** indent HTML code? */
	public bool $indent = true;

	/** @var string[] */
	public array $preserveSpaces = ['textarea', 'pre', 'script', 'code', 'samp', 'kbd'];

	/** base indent level */
	public int $baseIndent = 0;

	/** wrap width, doesn't include indent space */
	public int $lineWrap = 80;

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

		$this->baseDTD = Schema::flowContent();

		// wellform and reformat
		$s = Regexp::replace(
			$s . '</end/>',
			'~
				( [^<]*+ )
				< (?: (!--.*--) | (/?) ([a-z][a-z0-9._:-]*) (|[ \n].*) \s* (/?) ) >
			~Uisx',
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
			if ($item && !isset($item['dtdContent'][Schema::Text])) {  // text not allowed?

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
		$mEmpty = $mEmpty || isset(Schema::voidElements()[$mTag]);
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

		if (!Schema::isKnown($tag)) {
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
			if ($allowed) {
				foreach (Schema::prohibitedAncestors($tag) as $pTag) {
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

			} elseif ($indent && !isset(Schema::inlineElements()[$tag])) {
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
			$dtdContent = Schema::isKnown($tag)
				? Schema::childContent($tag, $dtdContent)
				: $dtdContent; // unknown tags keep inherited content model

			// Format output with indentation for block elements
			if ($this->indent && !isset(Schema::inlineElements()[$tag])) {
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
			$back = $back && isset(Schema::inlineElements()[$itemTag]);
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
					!Schema::hasOptionalEnd($itemTag)
					&& !isset(Schema::inlineElements()[$itemTag])
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
}
