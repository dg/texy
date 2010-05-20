<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */


/**
 * Paragraph module.
 *
 * @author     David Grudl
 */
final class TexyParagraphModule extends TexyModule
{


	public function __construct($texy)
	{
		$this->texy = $texy;
		$texy->addHandler('paragraph', array($this, 'solve'));
	}


	/**
	 * @param  TexyBlockParser
	 * @param  string     text
	 * @param  array
	 * @param  TexyHtml
	 * @return vois
	 */
	public function process($parser, $content, $el)
	{
		$tx = $this->texy;

		if ($parser->isIndented()) {
			$parts = preg_split('#(\n(?! )|\n{2,})#', $content, -1, PREG_SPLIT_NO_EMPTY);
		} else {
			$parts = preg_split('#(\n{2,})#', $content, -1, PREG_SPLIT_NO_EMPTY);
		}

		foreach ($parts as $s) {
			$s = trim($s);
			if ($s === '') {
				continue;
			}

			// try to find modifier
			$mod = NULL;
			if ($mx = TexyRegexp::match($s, '#'.TexyPatterns::MODIFIER_H.'(?=\n|\z)#sUm', TexyRegexp::OFFSET_CAPTURE)) {
				list($mMod) = $mx[1];
				$s = trim(substr_replace($s, '', $mx[0][1], strlen($mx[0][0])));
				if ($s === '') {
					continue;
				}
				$mod = new TexyModifier;
				$mod->setProperties($mMod);
			}

			$res = $tx->invokeAroundHandlers('paragraph', $parser, array($s, $mod));
			if ($res) {
				$el->insert(NULL, $res);
			}
		}
	}


	/**
	 * Finish invocation.
	 *
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  string
	 * @param  TexyModifier|NULL
	 * @return TexyHtml|FALSE
	 */
	public function solve($invocation, $content, $mod)
	{
		$tx = $this->texy;

		// find hard linebreaks
		if ($tx->mergeLines) {
			// ....
			// ... => \r means break line
			$content = TexyRegexp::replace($content, '#\n +(?=\S)#', "\r");
		} else {
			$content = TexyRegexp::replace($content, '#\n#', "\r");
		}

		$el = TexyHtml::el('p');
		$el->parseLine($tx, $content);
		$content = $el->getText(); // string

		// check content type
		// block contains block tag
		if (strpos($content, Texy::CONTENT_BLOCK) !== FALSE) {
			$el->setName(NULL); // ignores modifier!

		// block contains text (protected)
		} elseif (strpos($content, Texy::CONTENT_TEXTUAL) !== FALSE) {
			// leave element p

		// block contains text
		} elseif (preg_match('#[^\s'.TexyPatterns::MARK.']#u', $content)) {
			// leave element p

		// block contains only replaced element
		} elseif (strpos($content, Texy::CONTENT_REPLACED) !== FALSE) {
			$el->setName($tx->nontextParagraph);

		// block contains only markup tags or spaces or nothing
		} else {
			// if {ignoreEmptyStuff} return FALSE;
			if (!$mod) {
				$el->setName(NULL);
			}
		}

		if ($el->getName()) {
			// apply modifier
			if ($mod) {
				$mod->decorate($tx, $el);
			}

			// add <br />
			if (strpos($content, "\r") !== FALSE) {
				$key = $tx->protect('<br />', Texy::CONTENT_REPLACED);
				$content = str_replace("\r", $key, $content);
			};
		}

		$content = strtr($content, "\r\n", '  ');
		$el->setText($content);

		return $el;
	}

}
