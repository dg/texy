<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy;
use Texy\Regexp;
use function array_intersect, array_keys, array_unshift, count, htmlspecialchars, ltrim, max, reset, rtrim, str_repeat, strlen, substr, wordwrap;
use const ENT_NOQUOTES;


/**
 * Call-driven well-forming and formatting engine: the former string-parsing
 * engine, now fed by startTag()/endTag()/text()/raw() calls from the
 * renderer's tree walk (or by the raw() string frontend).
 * Fixes pairing, content model and nesting, produces indented, wrapped HTML.
 *
 * The output buffer is a list of segments; whitespace fix-ups that the string
 * machine encoded with control bytes (\x07 greedy space eater, \x08 back-tab,
 * \r collapsible newline, frozen preserve-space text) are explicit operations
 * here. Segment boundaries reproduce the byte-level quirks of the original:
 * a Barrier marks a spot the final right-trim could not cross, and back-tab
 * eating passes through EatSpaces barriers (the machine removed \x07 bytes
 * before processing \x08) but stops at Close barriers and preserved text.
 */
final class WellFormer
{
	// segment types
	private const
		Normal = 0,
		Pre = 1,          // preserved text (pre, code, comments): exempt from trims and wrapping
		BarrierEat = 2,   // former \x07 site: blocks trims, transparent to back-tab eating
		BarrierClose = 3; // former \x08 site: blocks trims and back-tab eating

	/** @var list<array{int, string}>  output buffer: [segment type, text] */
	private array $segs = [];

	private string $textBuffer = '';

	/**
	 * Where the current "fragment" (text emitted since the last tag) begins;
	 * the legacy engine trimmed per regex fragment, so operations like the
	 * <br> rtrim must not reach past this boundary.
	 * @var array{int, int}  [segment count, offset in last segment]
	 */
	private array $fragmentStart = [0, 0];

	/** eat spaces immediately following the last emitted tag (former \x07) */
	private bool $eatSpaces = false;

	/** @var ?array{int, int}  position right after the last soft newline (former \r) */
	private ?array $softMark = null;

	/** the soft newline at $softMark has already absorbed a second one */
	private bool $softCollapsed = false;

	/** indent space counter */
	private int $space;

	/** @var array<string, int> */
	private array $tagUsed = [];

	/** @var array<int, array{tag: string, open: ?string, close: ?string, closeIndent: ?int, dtdContent: array<string, int>, indent: int}> */
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
				$this->append($gap);
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
		$this->append('<');
		$this->appendPre($inner);
		$this->append('>');
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
			$this->appendPre($s); // inside pre & textarea preserve spaces verbatim
		} else {
			$this->append(Regexp::replace($s, '~[ \n]+~', ' ')); // otherwise shrink multiple spaces
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
				$this->stripTail(" \t\n\r\0\x0B", $this->fragmentStart);
				$this->append('<' . $tag . $attr . ">\n" . str_repeat("\t", max(0, $this->space - 1)));
				$this->eatBarrier();

			} elseif ($indent && !isset(Schema::inlineElements()[$tag])) {
				$tabs = str_repeat("\t", $this->space);
				$this->softNewline();
				$this->append($tabs . '<' . $tag . $attr . '>');
				$this->softNewline();
				$this->append($tabs);

			} else {
				$this->append('<' . $tag . $attr . '>');
			}

			$this->endFragment();
			return;
		}

		$open = null;
		$close = null;
		$closeIndent = null;
		$indent = 0;

		if ($allowed) {
			$open = '<' . $tag . $attr . '>';
			$close = '</' . $tag . '>';

			// Determine what children this tag can contain
			$dtdContent = Schema::isKnown($tag)
				? Schema::childContent($tag, $dtdContent)
				: $dtdContent; // unknown tags keep inherited content model

			// Format output with indentation for block elements
			if ($this->config->indent && !isset(Schema::inlineElements()[$tag])) {
				$closeIndent = $this->space;
				$this->append("\n" . str_repeat("\t", $this->space++) . $open);
				$this->eatBarrier();
				$indent = 1;
			} else {
				$this->append($open);
			}
		}

		// Push tag to stack for tracking open elements
		array_unshift($this->tagStack, [
			'tag' => $tag,
			'open' => $open,
			'close' => $close,
			'closeIndent' => $closeIndent,
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
			$this->emitClose($item);
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
			if ($item['open'] !== null) {
				$this->append($item['open']);
			}
			$this->space += $item['indent'];
			$this->tagUsed[$item['tag']]++;
			array_unshift($this->tagStack, $item);
		}
	}


	/**
	 * Emits the closing tag of a stack frame; block closes eat the preceding
	 * indentation tab (former \x08 back-tab) and re-indent for the parent.
	 * @param  array{tag: string, open: ?string, close: ?string, closeIndent: ?int, dtdContent: array<string, int>, indent: int}  $item
	 */
	private function emitClose(array $item): void
	{
		if ($item['close'] === null) {
			return;
		}

		if ($item['closeIndent'] !== null) {
			$this->backtabEat();
			$this->segs[] = [self::BarrierClose, ''];
			$this->append($item['close'] . "\n" . str_repeat("\t", $item['closeIndent']));
		} else {
			$this->append($item['close']);
		}
	}


	/********************* output buffer operations ****************d*g**/


	/**
	 * Appends markup/text to the buffer, consuming leading spaces while the
	 * eat-spaces mode (set right after an indented open tag or <br>) is on.
	 */
	private function append(string $s): void
	{
		if ($s === '') {
			return;
		}

		if ($this->eatSpaces) {
			$s = ltrim($s, ' ');
			if ($s === '') {
				return; // fully eaten, keep eating
			}
			$this->eatSpaces = false;
		}

		$last = count($this->segs) - 1;
		if ($last >= 0 && $this->segs[$last][0] === self::Normal) {
			$this->segs[$last][1] .= $s;
		} else {
			$this->segs[] = [self::Normal, $s];
		}
	}


	/**
	 * Marks a former \x07 site: blocks the final right-trim across it and
	 * turns on eating of the spaces that follow.
	 */
	private function eatBarrier(): void
	{
		$this->segs[] = [self::BarrierEat, ''];
		$this->eatSpaces = true;
	}


	/**
	 * Appends preserved text: exempt from space eating, trims and wrapping.
	 */
	private function appendPre(string $s): void
	{
		if ($s === '') {
			return;
		}

		$this->eatSpaces = false; // preserved content is not eaten
		$this->segs[] = [self::Pre, $s];
	}


	/**
	 * Soft newline around empty block voids (former \r): trailing tabs and
	 * spaces before it are trimmed, and two adjacent soft newlines collapse
	 * into a single "\n" (pairwise, like the \r\r -> \n replacement did).
	 */
	private function softNewline(): void
	{
		$this->stripTail(" \t");

		if ($this->softMark === $this->endPos() && !$this->softCollapsed) {
			$this->softCollapsed = true;
			return;
		}

		$this->append("\n");
		$this->softMark = $this->endPos();
		$this->softCollapsed = false;
	}


	/**
	 * Back-tab (former \x08): removes spaces and at most one tab immediately
	 * before a block closing tag. Passes through eat-barriers (the machine
	 * removed \x07 bytes first), stops at close-barriers and preserved text.
	 */
	private function backtabEat(): void
	{
		for ($i = count($this->segs) - 1; $i >= 0; $i--) {
			$type = $this->segs[$i][0];
			if ($type === self::BarrierEat || ($type === self::Normal && $this->segs[$i][1] === '')) {
				continue; // transparent
			}
			if ($type === self::Normal) {
				$this->segs[$i][1] = Regexp::replace($this->segs[$i][1], '~\t?\ *\z~', '', limit: 1);
			}
			return; // eaten chars must be contiguous - one segment only
		}
	}


	/**
	 * Strips trailing characters from the buffer, walking segments backward;
	 * stops at any barrier or preserved segment, and at $limit (fragment start).
	 * @param  ?array{int, int}  $limit
	 */
	private function stripTail(string $chars, ?array $limit = null): void
	{
		for ($i = count($this->segs) - 1; $i >= 0; $i--) {
			if ($this->segs[$i][0] !== self::Normal) {
				return;
			}

			$s = $this->segs[$i][1];
			if ($limit !== null && $limit[0] - 1 === $i) { // fragment starts inside this segment
				$this->segs[$i][1] = substr($s, 0, $limit[1]) . rtrim(substr($s, $limit[1]), $chars);
				return;
			}
			if ($limit !== null && $limit[0] - 1 > $i) {
				return; // before the fragment entirely
			}

			$s = rtrim($s, $chars);
			$this->segs[$i][1] = $s;
			if ($s !== '') {
				return;
			}
		}
	}


	/** @return array{int, int} */
	private function endPos(): array
	{
		$last = count($this->segs) - 1;
		return [$last + 1, $last >= 0 ? strlen($this->segs[$last][1]) : 0];
	}


	private function endFragment(): void
	{
		$this->fragmentStart = $this->endPos();
	}


	/**
	 * Flushes open tags and finalizes the output (trim, indentation cleanup,
	 * line wrapping).
	 */
	public function finish(): string
	{
		$this->flushText();

		// empty out stack
		foreach ($this->tagStack as $item) {
			$this->emitClose($item);
		}

		$this->tagStack = [];
		$this->tagUsed = [];

		// right trim inside normal segments; barriers and preserved text
		// interrupt the [\t ]+ runs exactly like the control bytes did
		$wrap = $this->config->lineWrap > 0;
		$s = '';
		$lastIndex = count($this->segs) - 1;
		foreach ($this->segs as $i => [$type, $text]) {
			if ($type === self::Pre) {
				// wordwrap must not break inside preserved text - encode its
				// whitespace as non-breakable for the duration of the wrap
				$s .= $wrap ? Texy\Helpers::freezeSpaces($text) : $text;
			} elseif ($type === self::Normal) {
				$text = Regexp::replace($text, '~[\t ]+(?=\n)~', '');
				if ($i === $lastIndex) {
					$text = Regexp::replace($text, '~[\t ]+\z~', '');
				}
				$s .= $text;
			}
		}

		$this->segs = [];
		$this->fragmentStart = [0, 0];
		$this->eatSpaces = false;
		$this->softMark = null;
		$this->softCollapsed = false;

		// line wrap
		if ($wrap) {
			$s = Regexp::replace($s, '~^(\t*)(.*)$~m', $this->wrap(...));
		}

		// unfreeze attribute values (and wrap-protected preserved text)
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
			$this->emitClose($item);
			$this->space -= $item['indent'];
			$this->tagUsed[$itemTag]--;
			unset($this->tagStack[$i]);
			$dtdContent = $this->baseDTD;
		}
	}
}
