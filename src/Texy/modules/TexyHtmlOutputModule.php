<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */


/**
 *
 * @author     David Grudl
 */
final class TexyHtmlOutputModule extends TexyModule
{
	/** @var bool  indent HTML code? */
	public $indent = TRUE;

	/** @var array */
	public $preserveSpaces = array('textarea', 'pre', 'script', 'code', 'samp', 'kbd');

	/** @var int  base indent level */
	public $baseIndent = 0;

	/** @var int  wrap width, doesn't include indent space */
	public $lineWrap = 80;

	/** @var bool  remove optional HTML end tags? */
	public $removeOptional = FALSE;

	/** @var int  indent space counter */
	private $space;

	/** @var array */
	private $tagUsed;

	/** @var array */
	private $tagStack;

	/** @var array  content DTD used, when context is not defined */
	private $baseDTD;

	/** @var bool */
	private $xml;


	public function __construct($texy)
	{
		$this->texy = $texy;
		$texy->addHandler('postProcess', array($this, 'postProcess'));
	}


	/**
	 * Converts <strong><em> ... </strong> ... </em>.
	 * into <strong><em> ... </em></strong><em> ... </em>
	 */
	public function postProcess($texy, & $s)
	{
		$this->space = $this->baseIndent;
		$this->tagStack = array();
		$this->tagUsed = array();
		$this->xml = $texy->getOutputMode() & Texy::XML;

		// special "base content"
		$this->baseDTD = $texy->dtd['div'][1] + $texy->dtd['html'][1] /*+ $texy->dtd['head'][1]*/ + $texy->dtd['body'][1] + array('html'=>1);

		// wellform and reformat
		$s = TexyRegexp::replace(
			$s . '</end/>',
			'#([^<]*+)<(?:(!--.*--)|(/?)([a-z][a-z0-9._:-]*)(|[ \n].*)\s*(/?))>()#Uis',
			array($this, 'cb')
		);

		// empty out stack
		foreach ($this->tagStack as $item) {
			$s .= $item['close'];
		}

		// right trim
		$s = TexyRegexp::replace($s, "#[\t ]+(\n|\r|$)#", '$1'); // right trim

		// join double \r to single \n
		$s = str_replace("\r\r", "\n", $s);
		$s = strtr($s, "\r", "\n");

		// greedy chars
		$s = TexyRegexp::replace($s, "#\\x07 *#", '');
		// back-tabs
		$s = TexyRegexp::replace($s, "#\\t? *\\x08#", '');

		// line wrap
		if ($this->lineWrap > 0) {
			$s = TexyRegexp::replace(
				$s,
				'#^(\t*)(.*)$#m',
				array($this, 'wrap')
			);
		}

		// remove HTML 4.01 optional end tags
		if (!$this->xml && $this->removeOptional) {
			$s = TexyRegexp::replace($s, '#\\s*</(colgroup|dd|dt|li|option|p|td|tfoot|th|thead|tr)>#u', '');
		}
	}


	/**
	 * Callback function: <tag> | </tag> | ....
	 * @return string
	 * @internal
	 */
	public function cb($matches)
	{
		// html tag
		list(, $mText, $mComment, $mEnd, $mTag, $mAttr, $mEmpty) = $matches;
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
			if ($item && !isset($item['dtdContent']['%DATA'])) {  // text not allowed?

			} elseif (array_intersect(array_keys($this->tagUsed, TRUE), $this->preserveSpaces)) { // inside pre & textarea preserve spaces
				$s = Texy::freezeSpaces($mText);

			} else {
				$s = TexyRegexp::replace($mText, '#[ \n]+#', ' '); // otherwise shrink multiple spaces
			}
		}


		// phase #2 - HTML comment
		if ($mComment) {
			return $s . '<' . Texy::freezeSpaces($mComment) . '>';
		}


		// phase #3 - HTML tag
		$mEmpty = $mEmpty || isset(TexyHtml::$emptyElements[$mTag]);
		if ($mEmpty && $mEnd) {
			return $s; // bad tag; /end/
		}


		if ($mEnd) { // end tag

			// has start tag?
			if (empty($this->tagUsed[$mTag])) {
				return $s;
			}

			// autoclose tags
			$tmp = array();
			$back = TRUE;
			foreach ($this->tagStack as $i => $item) {
				$tag = $item['tag'];
				$s .= $item['close'];
				$this->space -= $item['indent'];
				$this->tagUsed[$tag]--;
				$back = $back && isset(TexyHtml::$inlineElements[$tag]);
				unset($this->tagStack[$i]);
				if ($tag === $mTag) {
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


		} else { // start tag

			$dtdContent = $this->baseDTD;

			if (!isset($this->texy->dtd[$mTag])) {
				// unknown (non-html) tag
				$allowed = TRUE;
				$item = reset($this->tagStack);
				if ($item) {
					$dtdContent = $item['dtdContent'];
				}


			} else {
				// optional end tag closing
				foreach ($this->tagStack as $i => $item) {
					// is tag allowed here?
					$dtdContent = $item['dtdContent'];
					if (isset($dtdContent[$mTag])) {
						break;
					}

					$tag = $item['tag'];

					// auto-close hidden, optional and inline tags
					if ($item['close'] && (!isset(TexyHtml::$optionalEnds[$tag]) && !isset(TexyHtml::$inlineElements[$tag]))) {
						break;
					}

					// close it
					$s .= $item['close'];
					$this->space -= $item['indent'];
					$this->tagUsed[$tag]--;
					unset($this->tagStack[$i]);
					$dtdContent = $this->baseDTD;
				}

				// is tag allowed in this content?
				$allowed = isset($dtdContent[$mTag]);

				// check deep element prohibitions
				if ($allowed && isset(TexyHtml::$prohibits[$mTag])) {
					foreach (TexyHtml::$prohibits[$mTag] as $pTag) {
						if (!empty($this->tagUsed[$pTag])) {
							$allowed = FALSE; break;
						}
					}
				}
			}

			// empty elements se neukladaji do zasobniku
			if ($mEmpty) {
				if (!$allowed) {
					return $s;
				}

				if ($this->xml) {
					$mAttr .= " /";
				}

				$indent = $this->indent && !array_intersect(array_keys($this->tagUsed, TRUE), $this->preserveSpaces);

				if ($indent && $mTag === 'br') { // formatting exception
					return rtrim($s) . '<' . $mTag . $mAttr . ">\n" . str_repeat("\t", max(0, $this->space - 1)) . "\x07";

				} elseif ($indent && !isset(TexyHtml::$inlineElements[$mTag])) {
					$space = "\r" . str_repeat("\t", $this->space);
					return $s . $space . '<' . $mTag . $mAttr . '>' . $space;

				} else {
					return $s . '<' . $mTag . $mAttr . '>';
				}
			}

			$open = NULL;
			$close = NULL;
			$indent = 0;

			/*
			if (!isset(TexyHtml::$inlineElements[$mTag])) {
				// block tags always decorate with \n
				$s .= "\n";
				$close = "\n";
			}
			*/

			if ($allowed) {
				$open = '<' . $mTag . $mAttr . '>';

				// receive new content (ins & del are special cases)
				if (!empty($this->texy->dtd[$mTag][1])) {
					$dtdContent = $this->texy->dtd[$mTag][1];
				}

				// format output
				if ($this->indent && !isset(TexyHtml::$inlineElements[$mTag])) {
					$close = "\x08" . '</'.$mTag.'>' . "\n" . str_repeat("\t", $this->space);
					$s .= "\n" . str_repeat("\t", $this->space++) . $open . "\x07";
					$indent = 1;
				} else {
					$close = '</'.$mTag.'>';
					$s .= $open;
				}

				// TODO: problematic formatting of select / options, object / params
			}


			// open tag, put to stack, increase counter
			$item = array(
				'tag' => $mTag,
				'open' => $open,
				'close' => $close,
				'dtdContent' => $dtdContent,
				'indent' => $indent,
			);
			array_unshift($this->tagStack, $item);
			$tmp = & $this->tagUsed[$mTag]; $tmp++;
		}

		return $s;
	}


	/**
	 * Callback function: wrap lines.
	 * @return string
	 * @internal
	 */
	public function wrap($m)
	{
		list(, $space, $s) = $m;
		return $space . wordwrap($s, $this->lineWrap, "\n" . $space);
	}

}
