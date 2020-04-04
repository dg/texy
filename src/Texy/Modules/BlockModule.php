<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\HtmlElement;


/**
 * Special blocks module.
 */
final class BlockModule extends Texy\Module
{
	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		//$texy->allowed['blocks'] = true;
		$texy->allowed['block/default'] = true;
		$texy->allowed['block/pre'] = true;
		$texy->allowed['block/code'] = true;
		$texy->allowed['block/html'] = true;
		$texy->allowed['block/text'] = true;
		$texy->allowed['block/texysource'] = true;
		$texy->allowed['block/comment'] = true;
		$texy->allowed['block/div'] = true;

		$texy->addHandler('block', [$this, 'solve']);
		$texy->addHandler('beforeBlockParse', [$this, 'beforeBlockParse']);

		$texy->registerBlockPattern(
			[$this, 'pattern'],
			'#^/--++ *+(.*)' . Texy\Patterns::MODIFIER_H . '?$((?:\n(?0)|\n.*+)*)(?:\n\\\\--.*$|\z)#mUi',
			'blocks'
		);
	}


	/**
	 * Single block pre-processing.
	 */
	public function beforeBlockParse(Texy\BlockParser $parser, string &$text): void
	{
		// autoclose exclusive blocks
		$text = Texy\Regexp::replace(
			$text,
			'#^(/--++ *+(?!div|texysource).*)$((?:\n.*+)*?)(?:\n\\\\--.*$|(?=(\n/--.*$)))#mi',
			"\$1\$2\n\\--"
		);
	}


	/**
	 * Callback for:.
	 * /-----code html .(title)[class]{style}
	 * ....
	 * ....
	 * \----
	 *
	 * @return HtmlElement|string|null
	 */
	public function pattern(Texy\BlockParser $parser, array $matches)
	{
		[, $mParam, $mMod, $mContent] = $matches;
		// [1] => code | text | ...
		// [2] => ... additional parameters
		// [3] => .(title)[class]{style}<>
		// [4] => ... content

		$mod = new Texy\Modifier($mMod);
		$parts = preg_split('#\s+#u', $mParam, 2);
		$blocktype = empty($parts[0]) ? 'block/default' : 'block/' . $parts[0];
		$param = empty($parts[1]) ? null : $parts[1];

		return $this->texy->invokeAroundHandlers('block', $parser, [$blocktype, $mContent, $param, $mod]);
	}


	/**
	 * Finish invocation.
	 * @return HtmlElement|string|null
	 */
	public function solve(Texy\HandlerInvocation $invocation, string $blocktype, string $s, $param, Texy\Modifier $mod)
	{
		/** @var Texy\BlockParser $parser */
		$parser = $invocation->getParser();

		if (empty($this->texy->allowed[$blocktype])) {
			return null;
		}

		switch($blocktype) {
			case 'block/texy':
				return $this->blockTexy($s, $this->texy, $parser);
			break;

			case 'block/texysource':
				return $this->blockTexySource($s, $this->texy, $mod, $param);
			break;

			case 'block/code':
				return $this->blockCode($s, $this->texy, $mod, $param);
			break;

			case 'block/default':
				return $this->blockDefault($s, $this->texy, $mod, $param);
			break;

			case 'block/pre':
				return $this->blockPre($s, $this->texy, $mod, $param);
			break;

			case 'block/html':
				return $this->blockHtml($s, $this->texy, $mod, $param);
			break;

			case 'block/text':
				return $this->blockText($s, $this->texy, $mod, $param);
			break;

			case 'block/comment':
				return $this->blockComment($s, $this->texy, $mod, $param);
			break;

			case 'block/div':
				return $this->blockDiv($s, $this->texy, $mod, $param);
			break;

			default: 
				return null;
			break;
		}
		
	}

	private function isOutdentEqualEmptyString(string $s) 
	{
		$s = Helpers::outdent($s);

		if ($s === '') {
			return "\n";
		}
	}


	private function blockTexy(string $s, Texy\Texy $texy, Texy\BlockParser $parser): HtmlElement
	{
		$htmlElem = new HtmlElement;
		$htmlElem->parseBlock($texy, $s, $parser->isIndented());
		return $htmlElem;
	}


	/**
	 * @return string|HtmlElement
	 */
	private function blockTexySource(string $s, Texy\Texy $texy, Texy\Modifier $mod, $param)
	{
		$s = $this->isOutdentEqualEmptyString($s);
		
		$htmlElem = new HtmlElement;
		if ($param === 'line') {
			$htmlElem->parseLine($texy, $s);
		} else {
			$htmlElem->parseBlock($texy, $s);
		}
		$s = $htmlElem->toHtml($texy);
		return $this->blockCode($s, $texy, $mod, 'html');
	}


	/**
	 * @return string|HtmlElement
	 */
	private function blockCode(string $s, Texy\Texy $texy, Texy\Modifier $mod, $param)
	{
		$s = $this->isOutdentEqualEmptyString($s);

		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = $texy->protect($s, $texy::CONTENT_BLOCK);
		$htmlElem = new HtmlElement('pre');
		$mod->decorate($texy, $htmlElem);
		$htmlElem->attrs['class'][] = $param; // lang
		$htmlElem->create('code', $s);
		return $htmlElem;
	}


	/**
	 * @return string|HtmlElement
	 */
	private function blockDefault(string $s, Texy\Texy $texy, Texy\Modifier $mod, $param)
	{
		$s = $this->isOutdentEqualEmptyString($s); 

		$htmlElem = new HtmlElement('pre');
		$mod->decorate($texy, $htmlElem);
		$htmlElem->attrs['class'][] = $param; // lang
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = $texy->protect($s, $texy::CONTENT_BLOCK);
		$htmlElem->setText($s);
		return $htmlElem;
	}


	/**
	 * @return string|HtmlElement
	 */
	private function blockPre(string $s, Texy\Texy $texy, Texy\Modifier $mod)
	{
		$s = $this->isOutdentEqualEmptyString($s);

		$htmlElem = new HtmlElement('pre');
		$mod->decorate($texy, $htmlElem);
		$lineParser = new Texy\LineParser($texy, $htmlElem);
		// special mode - parse only html tags
		$tmp = $lineParser->patterns;
		$lineParser->patterns = [];
		if (isset($tmp['html/tag'])) {
			$lineParser->patterns['html/tag'] = $tmp['html/tag'];
		}
		if (isset($tmp['html/comment'])) {
			$lineParser->patterns['html/comment'] = $tmp['html/comment'];
		}
		unset($tmp);

		$lineParser->parse($s);
		$s = $htmlElem->getText();
		$s = Helpers::unescapeHtml($s);
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = $texy->unprotect($s);
		$s = $texy->protect($s, $texy::CONTENT_BLOCK);
		$htmlElem->setText($s);
		return $htmlElem;
	}


	private function blockHtml(string $s, Texy\Texy $texy): string
	{
		$s = trim($s, "\n");
		if ($s === '') {
			return "\n";
		}
		$htmlElem = new HtmlElement;
		$lineParser = new Texy\LineParser($texy, $htmlElem);
		// special mode - parse only html tags
		$tmp = $lineParser->patterns;
		$lineParser->patterns = [];
		if (isset($tmp['html/tag'])) {
			$lineParser->patterns['html/tag'] = $tmp['html/tag'];
		}
		if (isset($tmp['html/comment'])) {
			$lineParser->patterns['html/comment'] = $tmp['html/comment'];
		}
		unset($tmp);

		$lineParser->parse($s);
		$s = $htmlElem->getText();
		$s = Helpers::unescapeHtml($s);
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = $texy->unprotect($s);
		return $texy->protect($s, $texy::CONTENT_BLOCK) . "\n";
	}


	private function blockText(string $s, Texy\Texy $texy): string
	{
		$s = trim($s, "\n");
		if ($s === '') {
			return "\n";
		}
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = str_replace("\n", (new HtmlElement('br'))->startTag(), $s); // nl2br
		return $texy->protect($s, $texy::CONTENT_BLOCK) . "\n";
	}


	private function blockComment(): string
	{
		return "\n";
	}


	private function blockDiv(string $s, Texy\Texy $texy, Texy\Modifier $mod, Texy\BlockParser $parser)
	{
		$s = $this->isOutdentEqualEmptyString($s);

		$htmlElem = new HtmlElement('div');
		$mod->decorate($texy, $htmlElem);
		$htmlElem->parseBlock($texy, $s, $parser->isIndented()); // TODO: INDENT or NORMAL ?
		return $htmlElem;
	}
}
