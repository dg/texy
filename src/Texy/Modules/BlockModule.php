<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\HtmlElement;
use function assert, htmlspecialchars, str_replace, trim;
use const ENT_NOQUOTES;


/**
 * Processes special blocks (/-- code, html, text, div, etc.).
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

		$texy->addHandler('block', $this->solve(...));
		$texy->addHandler('beforeBlockParse', $this->beforeBlockParse(...));

		$texy->registerBlockPattern(
			$this->pattern(...),
			'~^
				/--++ \ *+                    # opening tag /--
				(.*)                          # content type (1)
				' . Texy\Patterns::MODIFIER_H . '? # modifier (2)
				$
				((?:                         # content (3)
					\n (?0) |                # recursive nested blocks
					\n.*+                    # or any content
				)*)
				(?:
					\n \\\--.* $ |           # closing tag
					\z                       # or end of input
				)
			~mUi',
			'blocks',
		);
	}


	/**
	 * Single block pre-processing.
	 */
	private function beforeBlockParse(Texy\BlockParser $parser, string &$text): void
	{
		// autoclose exclusive blocks
		$text = Texy\Regexp::replace(
			$text,
			'~^
				( /--++ \ *+ (?! div|texysource ) .* )  # opening tag except div/texysource (1)
				$
				((?: \n.*+ )*?)                 # content (2)
				(?:
					\n \\\--.* $ |              # closing tag
					(?= (\n /--.* $))           # or next block starts (3)
				)
			~mi',
			"\$1\$2\n\\--",                 // add closing tag
		);
	}


	/**
	 * Callback for:.
	 * /-----code html .(title)[class]{style}
	 * ....
	 * ....
	 * \----
	 * @param  array<?string>  $matches
	 */
	public function pattern(Texy\BlockParser $parser, array $matches): HtmlElement|string|null
	{
		/** @var array{string, string, ?string, string} $matches */
		[, $mParam, $mMod, $mContent] = $matches;
		// [1] => code | text | ...
		// [2] => ... additional parameters
		// [3] => .(title)[class]{style}<>
		// [4] => ... content

		$mod = new Texy\Modifier($mMod);
		$parts = Texy\Regexp::split($mParam, '~\s+~', limit: 2);
		$blocktype = empty($parts[0]) ? 'block/default' : 'block/' . $parts[0];
		$param = empty($parts[1]) ? null : $parts[1];

		return $this->texy->invokeAroundHandlers('block', $parser, [$blocktype, $mContent, $param, $mod]);
	}


	/**
	 * Finish invocation.
	 */
	private function solve(
		Texy\HandlerInvocation $invocation,
		string $blocktype,
		string $s,
		?string $param,
		Texy\Modifier $mod,
	): HtmlElement|string|null
	{
		$texy = $this->texy;
		$parser = $invocation->getParser();
		assert($parser instanceof Texy\BlockParser);

		if ($blocktype === 'block/texy') {
			return $this->blockTexy($s, $texy, $parser);
		} elseif (empty($texy->allowed[$blocktype])) {
			return null;
		} elseif ($blocktype === 'block/texysource') {
			return $this->blockTexySource($s, $texy, $mod, $param);
		} elseif ($blocktype === 'block/code') {
			return $this->blockCode($s, $texy, $mod, $param);
		} elseif ($blocktype === 'block/default') {
			return $this->blockDefault($s, $texy, $mod, $param);
		} elseif ($blocktype === 'block/pre') {
			return $this->blockPre($s, $texy, $mod);
		} elseif ($blocktype === 'block/html') {
			return $this->blockHtml($s, $texy);
		} elseif ($blocktype === 'block/text') {
			return $this->blockText($s, $texy);
		} elseif ($blocktype === 'block/comment') {
			return $this->blockComment();
		} elseif ($blocktype === 'block/div') {
			return $this->blockDiv($s, $texy, $mod, $parser);
		}

		return null;
	}


	private function blockTexy(string $s, Texy\Texy $texy, Texy\BlockParser $parser): HtmlElement
	{
		$el = new HtmlElement;
		$el->parseBlock($texy, $s, $parser->isIndented());
		return $el;
	}


	private function blockTexySource(string $s, Texy\Texy $texy, Texy\Modifier $mod, ?string $param): string|HtmlElement
	{
		$s = Helpers::outdent($s);
		if ($s === '') {
			return "\n";
		}

		$el = new HtmlElement;
		if ($param === 'line') {
			$el->parseLine($texy, $s);
		} else {
			$el->parseBlock($texy, $s);
		}

		$s = $el->toHtml($texy);
		return $this->blockCode($s, $texy, $mod, 'html');
	}


	private function blockCode(string $s, Texy\Texy $texy, Texy\Modifier $mod, ?string $param): string|HtmlElement
	{
		$s = Helpers::outdent($s);
		if ($s === '') {
			return "\n";
		}

		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = $texy->protect($s, $texy::CONTENT_BLOCK);
		$el = new HtmlElement('pre');
		$mod->decorate($texy, $el);
		if ($param !== null) {
			settype($el->attrs['class'], 'array');
			$el->attrs['class'][] = $param;
		}
		$el->create('code', $s);
		return $el;
	}


	private function blockDefault(string $s, Texy\Texy $texy, Texy\Modifier $mod, ?string $param): string|HtmlElement
	{
		$s = Helpers::outdent($s);
		if ($s === '') {
			return "\n";
		}

		$el = new HtmlElement('pre');
		$mod->decorate($texy, $el);
		if ($param !== null) {
			settype($el->attrs['class'], 'array');
			$el->attrs['class'][] = $param;
		}
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = $texy->protect($s, $texy::CONTENT_BLOCK);
		$el->setText($s);
		return $el;
	}


	private function blockPre(string $s, Texy\Texy $texy, Texy\Modifier $mod): string|HtmlElement
	{
		$s = Helpers::outdent($s);
		if ($s === '') {
			return "\n";
		}

		$el = new HtmlElement('pre');
		$mod->decorate($texy, $el);
		$lineParser = new Texy\InlineParser($texy);
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

		$el->setText($lineParser->parse($s));
		$s = $el->getText();
		assert($s !== null);
		$s = Helpers::unescapeHtml($s);
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = $texy->unprotect($s);
		$s = $texy->protect($s, $texy::CONTENT_BLOCK);
		$el->setText($s);
		return $el;
	}


	private function blockHtml(string $s, Texy\Texy $texy): string
	{
		$s = trim($s, "\n");
		if ($s === '') {
			return "\n";
		}

		$lineParser = new Texy\InlineParser($texy);
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

		$el = new HtmlElement(null, $lineParser->parse($s));
		$s = $el->getText();
		assert($s !== null);
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


	private function blockDiv(
		string $s,
		Texy\Texy $texy,
		Texy\Modifier $mod,
		Texy\BlockParser $parser,
	): string|HtmlElement
	{
		$s = Helpers::outdent($s, firstLine: true);
		if ($s === '') {
			return "\n";
		}

		$el = new HtmlElement('div');
		$mod->decorate($texy, $el);
		$el->parseBlock($texy, $s, $parser->isIndented()); // TODO: INDENT or NORMAL ?
		return $el;
	}
}
