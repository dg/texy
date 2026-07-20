<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy;
use Texy\Regexp;
use function array_intersect, array_keys, array_unshift, htmlspecialchars, max, reset, rtrim, str_repeat, strtr, wordwrap;
use const ENT_NOQUOTES;


/**
 * Call-driven well-forming and formatting engine: the former string-parsing
 * engine, now fed by startTag()/endTag()/text()/raw() calls from the
 * renderer's tree walk (or by the raw() string frontend).
 * Fixes pairing, content model and nesting, produces indented, wrapped HTML.
 */
final class WellFormer
{
	private string $out = '';

	private string $textBuffer = '';

	/**
	 * Offset where the current "fragment" (text emitted since the last tag)
	 * begins; the legacy engine trimmed per regex fragment, so operations
	 * like the <br> rtrim must not reach past this boundary.
	 */
	private int $fragmentStart = 0;

	/** indent space counter */
	private int $space;

	/** @var array<string, int> */
	private array $tagUsed = [];

	/** @var array<int, array{tag: string, open: ?string, close: ?string, dtdContent: array<string, int>, indent: int}> */
	private array $tagStack = [];

	/** @var array<string, int>  content DTD used, when context is not defined */
	private array $baseDTD;


	public function __construct(
		private Config $config,
	) {
		$this->space = $config->baseIndent;
		$this->baseDTD = Schema::flowContent();
	}


	/**
	 * Plain (unescaped) text.
	 */
	public function text(string $s): void
	{
		$this->escapedText(htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8'));
	}


	/**
	 * Raw HTML fragment: tokenized by the same grammar the string engine
	 * used. Unmatched pieces (stray "<", trailing text) keep their position,
	 * exactly as the string parser left them in place.
	 */
	public function raw(string $html): void
	{
		$offset = 0;
		$matches = Regexp::matchAll(
			$html,
			'~
				( [^<]*+ )
				< (?: (!--.*--) | (/?) ([a-z][a-z0-9._:-]*) (|[ \n].*) \s* (/?) ) >
			~Uisx',
			captureOffset: true,
		);

		foreach ($matches as $m) {
			/** @var array<array{?string, int}> $m */
			$gap = substr($html, $offset, $m[0][1] - $offset);
			if ($gap !== '') { // unmatched garbage bypassed the parser untouched
				$this->flushText();
				$this->out .= $gap;
				$this->endFragment();
			}

			$offset = $m[0][1] + strlen((string) $m[0][0]);

			$mText = $m[1][0] ?? '';
			$mComment = $m[2][0] ?? null;
			$mEnd = $m[3][0] ?? null;
			$mTag = $m[4][0] ?? null;
			$mEmpty = $m[6][0] ?? null;

			if ($mText !== '') {
				$this->escapedText($mText);
			}

			if ($mComment) {
				$this->comment($mComment);
			} elseif ($mTag !== null && $mTag !== '') {
				$empty = (bool) $mEmpty || isset(Schema::voidElements()[$mTag]);
				if ($empty && $mEnd) { // bad tag (closing a void element); still ends the text fragment
					$this->flushText();
					$this->endFragment();
				} elseif ($mEnd) {
					$this->endTag($mTag);
				} else {
					$this->startTag($mTag, $m[5][0] ?? '', $empty);
				}
			}
		}

		$rest = substr($html, $offset);
		if ($rest !== '') {
			$this->escapedText($rest);
		}
	}


	/**
	 * HTML comment (inner content in the form `!-- ... --`).
	 */
	public function comment(string $inner): void
	{
		$this->flushText();
		$this->out .= '<' . Texy\Helpers::freezeSpaces($inner) . '>';
		$this->endFragment();
	}


	/**
	 * Already escaped text content (internal and raw-fragment path).
	 * Buffered until the next tag: whitespace shrinking must span the whole
	 * inter-tag fragment, not individual text nodes.
	 */
	private function escapedText(string $s): void
	{
		$this->textBuffer .= $s;
	}


	private function flushText(): void
	{
		$s = $this->textBuffer;
		if ($s === '') {
			return;
		}

		$this->textBuffer = '';
		$item = reset($this->tagStack);
		if ($item && !isset($item['dtdContent'][Schema::Text])) { // text not allowed?
			return;
		}

		if (array_intersect(array_keys($this->tagUsed, filter_value: true, strict: false), $this->config->preserveSpaces)) {
			$this->out .= Texy\Helpers::freezeSpaces($s); // inside pre & textarea preserve spaces
		} else {
			$this->out .= Regexp::replace($s, '~[ \n]+~', ' '); // otherwise shrink multiple spaces
		}
	}


	public function startTag(string $tag, string $attr, bool $empty): void
	{
		$this->flushText();
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
			$this->closeOptionalTags($tag, $dtdContent);
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
				return;
			}

			$indent = $this->config->indent && !array_intersect(array_keys($this->tagUsed, filter_value: true, strict: false), $this->config->preserveSpaces);

			if ($indent && $tag === 'br') {
				// trim only the current fragment, not previously emitted markup
				$this->out = substr($this->out, 0, $this->fragmentStart)
					. rtrim(substr($this->out, $this->fragmentStart))
					. '<' . $tag . $attr . ">\n" . str_repeat("\t", max(0, $this->space - 1)) . "\x07";

			} elseif ($indent && !isset(Schema::inlineElements()[$tag])) {
				$space = "\r" . str_repeat("\t", $this->space);
				$this->out .= $space . '<' . $tag . $attr . '>' . $space;

			} else {
				$this->out .= '<' . $tag . $attr . '>';
			}

			$this->endFragment();
			return;
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
			if ($this->config->indent && !isset(Schema::inlineElements()[$tag])) {
				$close = "\x08" . '</' . $tag . '>' . "\n" . str_repeat("\t", $this->space);
				$this->out .= "\n" . str_repeat("\t", $this->space++) . $open . "\x07";
				$indent = 1;
			} else {
				$close = '</' . $tag . '>';
				$this->out .= $open;
			}
		}

		// Push tag to stack for tracking open elements
		array_unshift($this->tagStack, [
			'tag' => $tag,
			'open' => $open,
			'close' => $close,
			'dtdContent' => $dtdContent,
			'indent' => $indent,
		]);
		$tmp = &$this->tagUsed[$tag];
		$tmp++;
		$this->endFragment();
	}


	public function endTag(string $tag): void
	{
		$this->flushText();
		try {
			$this->processEndTag($tag);
		} finally {
			$this->endFragment();
		}
	}


	private function processEndTag(string $tag): void
	{
		// has start tag?
		if (empty($this->tagUsed[$tag])) {
			return;
		}

		// autoclose tags
		$tmp = [];
		$back = true;
		foreach ($this->tagStack as $i => $item) {
			$itemTag = $item['tag'];
			$this->out .= $item['close'];
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
			return;
		}

		// allowed-check (nejspis neni ani potreba)
		$item = reset($this->tagStack);
		$dtdContent = $item ? $item['dtdContent'] : $this->baseDTD;
		if (!isset($dtdContent[$tmp[0]['tag']])) {
			return;
		}

		// autoopen tags
		foreach ($tmp as $item) {
			$this->out .= $item['open'];
			$this->space += $item['indent'];
			$this->tagUsed[$item['tag']]++;
			array_unshift($this->tagStack, $item);
		}
	}


	private function endFragment(): void
	{
		$this->fragmentStart = strlen($this->out);
	}


	/**
	 * Flushes open tags and finalizes the output (trim, indentation cleanup,
	 * line wrapping, unfreezing spaces).
	 */
	public function finish(): string
	{
		$this->flushText();
		$s = $this->out;
		$this->out = '';

		// empty out stack
		foreach ($this->tagStack as $item) {
			$s .= $item['close'];
		}

		$this->tagStack = [];
		$this->tagUsed = [];

		// right trim
		$s = Regexp::replace($s, '~[\t ]+(\n|\r|$)~', '$1');

		// join double \r to single \n
		$s = str_replace("\r\r", "\n", $s);
		$s = strtr($s, "\r", "\n");

		// greedy chars
		$s = Regexp::replace($s, '~\x07\ *~', '');
		// back-tabs
		$s = Regexp::replace($s, '~\t?\ *\x08~', '');

		// line wrap
		if ($this->config->lineWrap > 0) {
			$s = Regexp::replace($s, '~^(\t*)(.*)$~m', $this->wrap(...));
		}

		// unfreeze spaces
		return Texy\Helpers::unfreezeSpaces($s);
	}


	/**
	 * Callback function: wrap lines.
	 * @param  array<?string>  $m
	 */
	private function wrap(array $m): string
	{
		/** @var array{string, string, string} $m */
		[, $space, $s] = $m;
		return $space . wordwrap($s, $this->config->lineWrap, "\n" . $space);
	}


	/** @param  array<string, int>  $dtdContent */
	private function closeOptionalTags(string $tag, array &$dtdContent): void
	{
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
			$this->out .= $item['close'];
			$this->space -= $item['indent'];
			$this->tagUsed[$itemTag]--;
			unset($this->tagStack[$i]);
			$dtdContent = $this->baseDTD;
		}
	}
}
