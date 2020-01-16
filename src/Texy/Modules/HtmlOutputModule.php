<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\HtmlElement;
use Texy\Regexp;


/**
 * HTML output
 */
final class HtmlOutputModule extends Texy\Module
{
	/** @var bool  indent HTML code? */
	public $indent = true;

	/** @var string[] */
	public $preserveSpaces = ['textarea', 'pre', 'script', 'code', 'samp', 'kbd'];

	/** @var int  base indent level */
	public $baseIndent = 0;

	/** @var int  wrap width, doesn't include indent space */
	public $lineWrap = 80;

	/** @deprecated */
	public $removeOptional = false;

	/** @var int  indent space counter */
	private $space = 0;

	/** @var array<string, int> */
	private $tagUsed = [];

	/** @var array<int, array{tag: string, open: string, close: string, dtdContent: array<string, int>, indent: int}> */
	private $tagStack = [];

	/** @var array<string, int>  content DTD used, when context is not defined */
	private $baseDTD = [];


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;
		$texy->addHandler('postProcess', [$this, 'postProcess']);
	}


	/**
	 * Converts <strong><em> ... </strong> ... </em>.
	 * into <strong><em> ... </em></strong><em> ... </em>
	 */
	public function postProcess(Texy\Texy $texy, string &$s): void
	{
		$this->space = $this->baseIndent;
		$this->tagStack = [];
		$this->tagUsed = [];

		// special "base content"
		$dtd = $texy->getDTD();
		$this->baseDTD = $dtd['div'][1] + $dtd['html'][1] /*+ $dtd['head'][1]*/ + $dtd['body'][1] + ['html' => 1];

		// wellform and reformat
		$s = Regexp::replace(
			$s . '</end/>',
			'#([^<]*+)<(?:(!--.*--)|(/?)([a-z][a-z0-9._:-]*)(|[ \n].*)\s*(/?))>()#Uis',
			[$this, 'cb']
		);

		// empty out stack
		foreach ($this->tagStack as $item) {
			$s .= $item['close'];
		}

		// right trim
		$s = Regexp::replace($s, "#[\t ]+(\n|\r|$)#", '$1'); // right trim

		// join double \r to single \n
		$s = str_replace("\r\r", "\n", $s);
		$s = strtr($s, "\r", "\n");

		// greedy chars
		$s = Regexp::replace($s, '#\x07 *#', '');
		// back-tabs
		$s = Regexp::replace($s, '#\t? *\x08#', '');

		// line wrap
		if ($this->lineWrap > 0) {
			$s = Regexp::replace(
				$s,
				'#^(\t*)(.*)$#m',
				[$this, 'wrap']
			);
		}
	}


	/**
	 * Callback function: <tag> | </tag> | ....
	 * @internal
	 */
	public function cb(array $matches): string
	{
		// html tag
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
			if ($item && !isset($item['dtdContent'][HtmlElement::INNER_TEXT])) {  // text not allowed?

			} elseif (array_intersect(array_keys($this->tagUsed, true, false), $this->preserveSpaces)) { // inside pre & textarea preserve spaces
				$s = Texy\Helpers::freezeSpaces($mText);

			} else {
				$s = Regexp::replace($mText, '#[ \n]+#', ' '); // otherwise shrink multiple spaces
			}
		}

		// phase #2 - HTML comment
		if ($mComment) {
			return $s . '<' . Texy\Helpers::freezeSpaces($mComment) . '>';
		}

		// phase #3 - HTML tag
		$mEmpty = $mEmpty || isset(HtmlElement::$emptyElements[$mTag]);
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
		$dtd = $this->texy->getDTD();

		if (!isset($dtd[$tag])) {
			// unknown (non-html) tag
			$allowed = true;
			$item = reset($this->tagStack);
			if ($item) {
				$dtdContent = $item['dtdContent'];
			}

		} else {
			$s .= $this->closeOptionalTags($tag, $dtdContent);

			// is tag allowed in this content?
			$allowed = isset($dtdContent[$tag]);

			// check deep element prohibitions
			if ($allowed && isset(HtmlElement::$prohibits[$tag])) {
				foreach (HtmlElement::$prohibits[$tag] as $pTag) {
					if (!empty($this->tagUsed[$pTag])) {
						$allowed = false;
						break;
					}
				}
			}
		}

		// empty elements se neukladaji do zasobniku
		if ($empty) {
			if (!$allowed) {
				return $s;
			}

			$indent = $this->indent && !array_intersect(array_keys($this->tagUsed, true, false), $this->preserveSpaces);

			if ($indent && $tag === 'br') { // formatting exception
				return rtrim($s) . '<' . $tag . $attr . ">\n" . str_repeat("\t", max(0, $this->space - 1)) . "\x07";

			} elseif ($indent && !isset(HtmlElement::$inlineElements[$tag])) {
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

			// receive new content
			if ($tagDTD = $dtd[$tag] ?? null) {
				if (isset($tagDTD[1][HtmlElement::INNER_TRANSPARENT])) {
					$dtdContent += $tagDTD[1];
					unset($dtdContent[HtmlElement::INNER_TRANSPARENT]);
				} else {
					$dtdContent = $tagDTD[1];
				}
			}

			// format output
			if ($this->indent && !isset(HtmlElement::$inlineElements[$tag])) {
				$close = "\x08" . '</' . $tag . '>' . "\n" . str_repeat("\t", $this->space);
				$s .= "\n" . str_repeat("\t", $this->space++) . $open . "\x07";
				$indent = 1;
			} else {
				$close = '</' . $tag . '>';
				$s .= $open;
			}

			// TODO: problematic formatting of select / options, object / params
		}

		// open tag, put to stack, increase counter
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
			$back = $back && isset(HtmlElement::$inlineElements[$itemTag]);
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
			if ($item['close'] && (!isset(HtmlElement::$optionalEnds[$itemTag]) && !isset(HtmlElement::$inlineElements[$itemTag]))) {
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
	 * @internal
	 */
	public function wrap(array $m): string
	{
		[, $space, $s] = $m;
		return $space . wordwrap($s, $this->lineWrap, "\n" . $space);
	}
}
