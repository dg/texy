<?php declare(strict_types=1);

/**
 * A CommonMark parser built on Texy\Parsing\Engine - proof that the engine
 * carries a language other than Texy. Emphasis pairing and reference links
 * cannot be decided at match time, so they run as passes over the AST.
 * See readme.md.
 */

namespace Texy\Examples\Markdown;

use Texy\Helpers;
use Texy\Node;
use Texy\NodeHelpers;
use Texy\Nodes;
use Texy\Parsing\Engine;
use Texy\Parsing\ParseContext;
use Texy\Range;


final class Markdown
{
	private const HtmlBlockTags = 'address|article|aside|base|basefont|blockquote|body|caption|center|col|colgroup|dd|details|dialog|dir|div|dl|dt|fieldset|figcaption|figure|footer|form|frame|frameset|h1|h2|h3|h4|h5|h6|head|header|hr|html|iframe|legend|li|link|main|menu|menuitem|nav|noframes|ol|optgroup|option|p|param|search|section|summary|table|tbody|td|tfoot|th|thead|title|tr|track|ul';

	private const TagName = '[A-Za-z][A-Za-z0-9-]*';
	private const AttrName = '[a-zA-Z_:][a-zA-Z0-9_.:-]*';
	private const AttrValue = '(?:[^ \t\n"\'=<>`]+|\'[^\']*\'|"[^"]*")';
	private const Attr = '(?:[ \t\n]+' . self::AttrName . '(?:[ \t\n]*=[ \t\n]*' . self::AttrValue . ')?)';
	private const OpenTag = '<' . self::TagName . self::Attr . '*[ \t\n]*/?>';
	private const CloseTag = '</' . self::TagName . '[ \t\n]*>';

	/** a tab advances to the next multiple of four columns */
	private const TabStop = 4;

	private Engine $engine;

	/** @var array<string, array{url: string, title: ?string}>  collected during the block pass */
	private array $linkDefs = [];

	/** @var array<string, bool> */
	private array $allowed = [];


	public function __construct()
	{
		$this->engine = new Engine;
		$this->registerBlocks();
		$this->registerInlines();
		$this->engine->setGapHandler($this->parseGap(...));
	}


	public function parse(string $text): Nodes\DocumentNode
	{
		$this->linkDefs = [];
		$document = $this->engine->parse(self::normalize($text), $this->allowed);
		$this->transform($document);
		return $document;
	}


	private static function normalize(string $s): string
	{
		return str_replace(["\r\n", "\r", "\0"], ["\n", "\n", "\u{FFFD}"], $s);
	}


	private function addBlock(string $name, string $pattern, \Closure $handler): void
	{
		$this->allowed[$name] = true;
		$this->engine->addBlockSyntax($name, $pattern, $handler);
	}


	private function addLine(string $name, string $pattern, \Closure $handler): void
	{
		$this->allowed[$name] = true;
		$this->engine->addLineSyntax($name, $pattern, $handler);
	}


	/********************* block syntaxes ****************d*g**/


	private function registerBlocks(): void
	{
		// [label]: /url "title" - only valid at the start of a block, hence the lookbehind
		$this->addBlock(
			'link/definition',
			'~(?:(?<=\n\n)|(?<!.)(?<!\n))[ ]{0,3}\[((?:[^\[\]\\\]|\\\.){1,999})\]:[ \t]*\n?[ \t]*'
			. '(<[^<>\n]*>|[^\s<][^\s]*)(?:(?:[ \t]+|[ \t]*\n[ \t]*)("[^"]*"|\'[^\']*\'|\([^()]*\)))?[ \t]*(?=\n|$)~',
			$this->parseLinkDefinition(...),
		);

		// ```lang ... ```
		$this->addBlock(
			'block/fenced',
			'~^([ ]{0,3})(`{3,}|\~{3,})[ \t]*([^\n]*)$~',
			$this->parseFenced(...),
		);

		// ### heading
		$this->addBlock(
			'heading/atx',
			'~^[ ]{0,3}(\#{1,6})(?:[ \t]+([^\n]*?))??(?:[ \t]+\#+)?[ \t]*$~',
			$this->parseAtxHeading(...),
		);

		// *** or --- or ___
		$this->addBlock(
			'horizline',
			'~^[ ]{0,3}([-*_])(?:[ \t]*\1){2,}[ \t]*$~',
			fn($ctx, $m, $offsets) => new Nodes\HorizontalRuleNode($m[1], null, new Range($offsets[0], strlen($m[0]))),
		);

		// the seven kinds of HTML block; each has its own terminator, see parseHtmlBlock()
		$this->addBlock(
			'html/block',
			'~^[ ]{0,3}(?:'
				. '(?<t1><(?i:script|pre|style|textarea)(?=[ \t\n>]|$))'
				. '|(?<t2><!--)'
				. '|(?<t3><\?)'
				. '|(?<t4><![A-Za-z])'
				. '|(?<t5><!\[CDATA\[)'
				. '|(?<t6></?(?i:' . self::HtmlBlockTags . ')(?=[ \t\n>]|/>|$))'
				. '|(?<t7>(?:' . self::OpenTag . '|' . self::CloseTag . ')[ \t]*$)'
			. ')~',
			$this->parseHtmlBlock(...),
		);

		// > quote
		$this->addBlock('blockquote', '~^[ ]{0,3}>~', $this->parseBlockQuote(...));

		// - item / 1. item; the lookahead captures the rest of the line for the handler
		$this->addBlock('list', '~^([ ]{0,3})([-+*]|\d{1,9}[.)])(?=([ \t][^\n]*)|$)~', $this->parseList(...));

		// a paragraph underlined with === or ---
		$this->addBlock(
			'heading/setext',
			'~(?:(?<=\n\n)|(?<!.)(?<!\n))((?:[ ]{0,3}\S[^\n]*\n)+)[ ]{0,3}(=+|-+)[ \t]*$~',
			$this->parseSetextHeading(...),
		);

		// four spaces of indentation
		$this->addBlock(
			'block/indented',
			'~(?:(?<=\n\n)|(?<!.)(?<!\n))(?:[ ]{4}|[ ]{0,3}\t)[ \t]*\S[^\n]*$~',
			$this->parseIndentedCode(...),
		);
	}


	/**
	 * @param  array<?string>  $m
	 * @param  array<?int>  $offsets
	 */
	private function parseLinkDefinition(
		ParseContext $ctx,
		array $m,
		array $offsets,
	): Nodes\BlockNode|Nodes\ContentNode|null
	{
		$label = self::normalizeLabel($m[1]);
		if ($label === '') {
			return null;
		}

		$url = $m[2];
		if (str_starts_with($url, '<') && str_ends_with($url, '>')) {
			$url = substr($url, 1, -1);
		}

		$this->linkDefs[$label] ??= [ // the first definition of a label wins
			'url' => self::unescape($url),
			'title' => $m[3] === null ? null : self::unescape(substr($m[3], 1, -1)),
		];

		return new Nodes\ContentNode; // consume, emit nothing
	}


	/**
	 * @param  array<?string>  $m
	 * @param  array<?int>  $offsets
	 */
	private function parseFenced(ParseContext $ctx, array $m, array $offsets): ?Nodes\BlockNode
	{
		if ($m[2][0] === '`' && str_contains($m[3], '`')) {
			return null; // a backtick fence must not have a backtick in its info string
		}

		$parser = $ctx->getBlockParser();
		$indent = strlen($m[1]);
		$closing = '~^[ ]{0,3}' . preg_quote($m[2][0], '~') . '{' . strlen($m[2]) . ',}[ \t]*$~';
		$lines = [];

		while ($parser->next('~[^\n]*~', $line)) {
			if (preg_match($closing, $line[0])) {
				break;
			}
			$lines[] = self::stripIndent($line[0], $indent);
		}

		$info = trim(self::unescape($m[3]));
		return new Nodes\CodeBlockNode(
			'code',
			$lines ? implode("\n", $lines) . "\n" : '',
			$info === '' ? null : preg_split('~\s~', $info)[0],
			null,
			new Range($offsets[0], strlen($m[0])),
		);
	}


	/**
	 * @param  array<?string>  $m
	 * @param  array<?int>  $offsets
	 */
	private function parseAtxHeading(ParseContext $ctx, array $m, array $offsets): ?Nodes\BlockNode
	{
		$content = $m[2] ?? '';
		// "#5 bolt" is not a heading: a space must follow the hashes
		if ($content === '' && !preg_match('~^[ ]{0,3}\#{1,6}[ \t]*(\#+[ \t]*)?$~', $m[0])) {
			return null;
		}

		$content = rtrim($content);
		if (preg_match('~^(.*?)(?<!\\\)[ \t]+\#+$~', $content, $trailing)) {
			$content = rtrim($trailing[1]); // closing sequence of hashes
		}

		return new Nodes\HeadingNode(
			$ctx->parseInline($content, $offsets[2] ?? $offsets[0]),
			strlen($m[1]),
			Nodes\HeadingType::Surrounded,
			null,
			new Range($offsets[0], strlen($m[0])),
		);
	}


	/**
	 * @param  array<?string>  $m
	 * @param  array<?int>  $offsets
	 */
	private function parseSetextHeading(ParseContext $ctx, array $m, array $offsets): ?Nodes\BlockNode
	{
		$lines = explode("\n", rtrim($m[1], "\n"));
		foreach ($lines as $line) {
			if (preg_match('~^[ ]{0,3}(\#{1,6}[ \t]|>|```|\~\~\~|(?:[-*_][ \t]*){3,}$)~', $line)) {
				return null; // what is underlined must be a paragraph, not another block
			}
		}

		return new Nodes\HeadingNode(
			$ctx->parseInline(implode("\n", array_map(fn($l) => trim($l), $lines)), $offsets[1]),
			$m[2][0] === '=' ? 1 : 2,
			Nodes\HeadingType::Underlined,
			null,
			new Range($offsets[0], strlen($m[0])),
		);
	}


	/**
	 * @param  array<?string>  $m
	 * @param  array<?int>  $offsets
	 */
	private function parseIndentedCode(ParseContext $ctx, array $m, array $offsets): Nodes\BlockNode
	{
		$parser = $ctx->getBlockParser();
		$parser->moveBackward(1);
		$lines = $pending = [];

		while ($parser->next('~[^\n]*~', $line)) {
			if (trim($line[0]) === '') {
				$pending[] = self::stripIndent($line[0], self::TabStop);
			} elseif (self::indentWidth($line[0]) >= self::TabStop) {
				$lines = array_merge($lines, $pending, [self::stripIndent($line[0], self::TabStop)]);
				$pending = [];
			} else {
				$parser->moveBackward(1);
				break;
			}
		}

		return new Nodes\CodeBlockNode('code', implode("\n", $lines) . "\n", null, null, new Range($offsets[0], strlen($m[0])));
	}


	/**
	 * @param  array<?string>  $m
	 * @param  array<?int>  $offsets
	 */
	private function parseHtmlBlock(ParseContext $ctx, array $m, array $offsets): Nodes\BlockNode
	{
		$parser = $ctx->getBlockParser();
		$parser->moveBackward(1);

		// types 1-5 end at their own terminator, 6 and 7 at a blank line
		$end = match (true) {
			($m['t1'] ?? '') !== '' => '~</(?i:script|pre|style|textarea)>~',
			($m['t2'] ?? '') !== '' => '~-->~',
			($m['t3'] ?? '') !== '' => '~\?>~',
			($m['t4'] ?? '') !== '' => '~>~',
			($m['t5'] ?? '') !== '' => '~\]\]>~',
			default => null,
		};

		$lines = [];
		while ($parser->next('~[^\n]*~', $line)) {
			if ($end === null && trim($line[0]) === '') {
				break;
			}
			$lines[] = $line[0];
			if ($end !== null && preg_match($end, $line[0])) {
				break;
			}
		}

		return new HtmlBlockNode(rtrim(implode("\n", $lines), "\n"), new Range($offsets[0], strlen($m[0])));
	}


	/**
	 * Strips one level of "> " and hands the rest back to the block parser.
	 * @param  array<?string>  $m
	 * @param  array<?int>  $offsets
	 */
	private function parseBlockQuote(ParseContext $ctx, array $m, array $offsets): Nodes\BlockNode
	{
		$parser = $ctx->getBlockParser();
		$parser->moveBackward(1);
		$lines = [];
		$inParagraph = false;

		while ($parser->next('~[^\n]*~', $line)) {
			if (preg_match('~^[ ]{0,3}>[ ]?~', $line[0], $marker)) {
				$lines[] = $content = substr($line[0], strlen($marker[0]));
				$inParagraph = trim($content) !== '';
			} elseif ($inParagraph && trim($line[0]) !== '' && !self::startsNewBlock($line[0])) {
				$lines[] = $line[0]; // lazy continuation
			} else {
				$parser->moveBackward(1);
				break;
			}
		}

		return new Nodes\BlockQuoteNode($ctx->parseBlock(implode("\n", $lines)), null, new Range($offsets[0], strlen($m[0])));
	}


	/**
	 * Collects the items, strips each one's indentation and parses its content as blocks.
	 * @param  array<?string>  $m
	 * @param  array<?int>  $offsets
	 */
	private function parseList(ParseContext $ctx, array $m, array $offsets): Nodes\BlockNode|Nodes\ContentNode|null
	{
		$parser = $ctx->getBlockParser();
		$markerType = self::markerType($m[2]);
		$start = ctype_digit($m[2][0]) ? (int) substr($m[2], 0, -1) : null;

		// only a list starting at 1 with a non-empty first item may interrupt a paragraph;
		// otherwise the line is grafted onto the preceding paragraph as its continuation
		$nodes = $parser->getNodes();
		$previous = end($nodes) ?: null;
		if ($previous instanceof Nodes\ParagraphNode
			&& $previous->range !== null
			&& $offsets[0] - $previous->range->offset - $previous->range->length === 1
			&& ($start !== null && $start !== 1 || trim($m[3] ?? '') === '')
		) {
			$parser->moveBackward(1);
			$parser->next('~[^\n]*~', $line, $lineOffsets);
			$previous->content->children[] = new SoftBreakNode;
			array_push($previous->content->children, ...$ctx->parseInline(trim($line[0]), $lineOffsets[0])->children);
			$previous->range = new Range($previous->range->offset, $lineOffsets[0] + strlen($line[0]) - $previous->range->offset);
			return new Nodes\ContentNode;
		}

		$parser->moveBackward(1);
		$items = [];
		$current = null;
		$contentIndent = 0;
		$blanks = 0;
		$loose = false;

		while ($parser->next('~[^\n]*~', $line)) {
			$l = $line[0];

			if (trim($l) === '') {
				if ($current === null) {
					$parser->moveBackward(1);
					break;
				}
				$blanks++;
				$current[] = '';
				continue;
			}

			if (preg_match('~^([ ]{0,3})([-+*]|\d{1,9}[.)])([ \t]+|$)~', $l, $marker)
				&& self::markerType($marker[2]) === $markerType
				&& ($current === null || strlen($marker[1]) < $contentIndent)
			) {
				if ($current !== null) {
					$loose = $loose || $blanks > 0;
					$items[] = $current;
				}

				$blanks = 0;
				$rest = substr($l, strlen($marker[0]));
				$width = strlen($marker[1]) + strlen($marker[2]);
				$spaces = strlen($marker[3]);

				// more than four spaces after the marker mean an indented code block
				$contentIndent = $spaces > self::TabStop || ($spaces === 0 && trim($rest) === '')
					? $width + 1
					: $width + $spaces;
				if ($spaces > self::TabStop) {
					$rest = str_repeat(' ', $spaces - 1) . $rest;
				}

				$current = [$rest];
				continue;
			}

			if ($current === null) {
				$parser->moveBackward(1);
				break;
			}

			if (self::indentWidth($l) >= $contentIndent) {
				$current[] = self::stripIndent($l, $contentIndent);
				$blanks = 0;
				continue;
			}

			if ($blanks === 0 && !self::startsNewBlock($l) && trim(end($current)) !== '') {
				$current[] = ltrim($l); // lazy continuation
				continue;
			}

			$parser->moveBackward(1);
			break;
		}

		if ($current !== null) {
			$items[] = $current;
		}
		if (!$items) {
			return null;
		}

		$nodes = [];
		foreach ($items as $item) {
			$text = rtrim(implode("\n", $item), "\n");
			// a blank line makes the list loose only between the item's own blocks
			$stripped = preg_replace('~^([ ]{0,3})(`{3,}|\~{3,})[^\n]*\n(.*?\n)??\1?\2[ \t]*(?=\n|$)~ms', '', $text);
			$loose = $loose || (bool) preg_match('~\n[ \t]*\n(?![ \t])(?!$)~', $stripped);
			$nodes[] = new Nodes\ListItemNode($ctx->parseBlock($text));
		}

		$list = new ListNode(
			$nodes,
			str_starts_with($markerType, 'ol') ? Nodes\ListType::Decimal : Nodes\ListType::Unordered,
			$start === 1 ? null : $start,
		);
		$list->tight = !$loose;
		$list->range = new Range($offsets[0], strlen($m[0]));
		return $list;
	}


	/** Lines a paragraph may not swallow as a lazy continuation. */
	private static function startsNewBlock(string $line): bool
	{
		return (bool) preg_match(
			'~^[ ]{0,3}(?:\#{1,6}(?:[ \t]|$)|```|\~\~\~|>|(?:[-*_][ \t]*){3,}$|(?:[-+*]|\d{1,9}[.)])(?:[ \t]|$))~',
			$line,
		);
	}


	private static function markerType(string $marker): string
	{
		return ctype_digit($marker[0]) ? 'ol' . substr($marker, -1) : 'ul' . $marker;
	}


	/** Width of the leading whitespace in columns, counting a tab to the next tab stop. */
	private static function indentWidth(string $line): int
	{
		preg_match('~^[ \t]*~', $line, $m);
		$width = 0;
		foreach (str_split($m[0]) as $char) {
			$width += $char === "\t" ? self::TabStop - $width % self::TabStop : 1;
		}
		return $width;
	}


	/** Removes $columns columns of indentation; a tab straddling the boundary leaves spaces behind. */
	private static function stripIndent(string $line, int $columns): string
	{
		$i = $column = 0;
		while ($i < strlen($line) && $column < $columns) {
			if ($line[$i] === ' ') {
				$column++;
			} elseif ($line[$i] === "\t") {
				$column += self::TabStop - $column % self::TabStop;
			} else {
				break;
			}
			$i++;
		}
		return str_repeat(' ', max(0, $column - $columns)) . substr($line, $i);
	}


	/**
	 * The gap handler: everything the block patterns did not claim is paragraphs.
	 * @return array<Nodes\BlockNode>
	 */
	private function parseGap(ParseContext $ctx, string $text, int $offset): array
	{
		$res = [];
		foreach (preg_split('~\n[ \t]*\n(?:[ \t]*\n)*~', $text, -1, PREG_SPLIT_OFFSET_CAPTURE) as [$chunk, $chunkOffset]) {
			if (trim($chunk) === '') {
				continue;
			}

			// the Range spans the chunk as written in the source
			$range = new Range($offset + $chunkOffset, strlen(rtrim($chunk, "\n")));

			$chunk = preg_replace('~^(?:[ \t]*\n)+~', '', $chunk);
			// trailing spaces stay - two of them are a hard line break
			$chunk = rtrim(implode("\n", array_map(fn($l) => preg_replace('~^[ \t]+~', '', $l), explode("\n", $chunk))));

			$res[] = new Nodes\ParagraphNode(
				$ctx->parseInline($chunk, $offset + $chunkOffset),
				null,
				$range,
			);
		}

		return $res;
	}


	/********************* inline syntaxes ****************d*g**/


	private function registerInlines(): void
	{
		// `code`, with any number of backticks
		$this->addLine('phrase/code', '~(?<!`)(`+)(?!`)([\s\S]*?)(?<!`)\1(?!`)~', $this->parseCodeSpan(...));

		// <https://example.com> and <user@example.com>
		$this->addLine(
			'link/auto',
			'~<([a-zA-Z][a-zA-Z0-9+.-]{1,31}:[^<>[:cntrl:] ]*)>~',
			fn($ctx, $m, $offsets) => self::autolink($m[1], $m[1], $offsets[0], strlen($m[0])),
		);
		$this->addLine(
			'link/email',
			'~<([a-zA-Z0-9.!#$%&\'*+/=?^_`{|}\~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?'
			. '(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*)>~',
			fn($ctx, $m, $offsets) => self::autolink('mailto:' . $m[1], $m[1], $offsets[0], strlen($m[0])),
		);

		// raw HTML tags, comments, processing instructions, declarations, CDATA
		$this->addLine(
			'html/inline',
			'~' . self::OpenTag . '|' . self::CloseTag . '|<!-->|<!--->|<!--(?:[^-]|-(?!->))*-->'
			. '|<\?[\s\S]*?\?>|<![A-Za-z][^>]*>|<!\[CDATA\[[\s\S]*?\]\]>~',
			fn($ctx, $m, $offsets) => new RawInlineNode($m[0], new Range($offsets[0], strlen($m[0]))),
		);

		// \* and friends
		$this->addLine(
			'escape',
			'~\\\([!"\#$%&\'()*+,\-./:;<=>?@\[\\\\\]^_`{|}\~])~',
			fn($ctx, $m, $offsets) => new Nodes\TextNode($m[1], new Range($offsets[0], 2)),
		);

		// a backslash or two spaces before a newline is a hard break; a bare newline is a soft one
		$this->addLine('escape/break', '~\\\\\n~', fn($ctx, $m, $offsets) => new Nodes\LineBreakNode(new Range($offsets[0], 2)));
		$this->addLine('break/hard', '~ {2,}\n~', fn($ctx, $m, $offsets) => new Nodes\LineBreakNode(new Range($offsets[0], strlen($m[0]))));
		$this->addLine('break/soft', '~[ \t]*\n[ \t]*~', fn($ctx, $m, $offsets) => new SoftBreakNode(new Range($offsets[0], strlen($m[0]))));

		// [text](/url "title"), [text][ref] and the ! variants; brackets nest via recursive subpattern
		$bracket = '(?<brk>\[(?:[^\[\]\\\]|\\\.|(?&brk))*\])';
		$paren = '(?<par>\((?:[^()\\\]|\\\.|(?&par))*\))';
		$dest = '(?:<(?:[^<>\n\\\]|\\\.)*>|(?:[^ \t\n()\\\[:cntrl:]]|\\\.|' . $paren . ')*)';
		$title = '(?:"(?:[^"\\\]|\\\.)*"|\'(?:[^\'\\\]|\\\.)*\'|\((?:[^()\\\]|\\\.)*\))';
		$tail = '(?:\((?<inl>[ \t\n]*(?<dest>' . $dest . ')(?:[ \t\n]+(?<ttl>' . $title . '))?[ \t\n]*)\)'
			. '|(?<ref>\[(?:[^\[\]\\\]|\\\.)*\])?)';

		$this->addLine('image', '~!' . $bracket . $tail . '~', $this->parseImage(...));
		$this->addLine('link', '~(?<!!)' . $bracket . $tail . '~', $this->parseLink(...));

		// delimiter runs are only marked here; pairing happens in processEmphasis()
		$this->addLine(
			'delimiter',
			'~(?:(?<=(.))|(?<!.))(\*+|_+)(?=(.)|$)~s',
			fn($ctx, $m, $offsets) => new DelimiterNode($m[2], $m[1] ?? '', $m[3] ?? '', new Range($offsets[2], strlen($m[2]))),
		);

		// &amp; &#35; &#x22;
		$this->addLine('entity', '~' . Helpers::EntityPattern . '~', $this->parseEntity(...));
	}


	/**
	 * @param  array<?string>  $m
	 * @param  array<?int>  $offsets
	 */
	private function parseCodeSpan(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		$code = str_replace("\n", ' ', $m[2]);
		if (str_starts_with($code, ' ') && str_ends_with($code, ' ') && trim($code) !== '') {
			$code = substr($code, 1, -1);
		}

		return new Nodes\PhraseNode(
			new Nodes\ContentNode([new Nodes\RawTextNode($code)]),
			'code',
			null,
			new Range($offsets[0], strlen($m[0])),
		);
	}


	private static function autolink(string $url, string $label, int $offset, int $length): LinkNode
	{
		return new LinkNode($url, new Nodes\ContentNode([new Nodes\TextNode($label)]), null, new Range($offset, $length));
	}


	/**
	 * @param  array<?string>  $m
	 * @param  array<?int>  $offsets
	 */
	private function parseEntity(ParseContext $ctx, array $m, array $offsets): ?Nodes\InlineNode
	{
		$decoded = Helpers::decodeEntity($m[0]);
		return $decoded === null
			? null
			: new Nodes\TextNode($decoded, new Range($offsets[0], strlen($m[0])));
	}


	/**
	 * @param  array<?string>  $m
	 * @param  array<?int>  $offsets
	 */
	private function parseImage(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		$label = substr($m['brk'], 1, -1);
		$alt = $ctx->parseInline($label, $offsets['brk'] + 1);
		$range = new Range($offsets[0], strlen($m[0]));

		if (($m['inl'] ?? null) !== null) {
			return new ImageNode(self::cleanDestination($m['dest'] ?? ''), $alt, self::cleanTitle($m['ttl'] ?? null), $range);
		}

		$node = new ImageNode(self::reference($m['ref'] ?? '', $label), $alt, null, $range);
		$node->fallback = ['![', ']' . ($m['ref'] ?? '')];
		return $node;
	}


	/**
	 * @param  array<?string>  $m
	 * @param  array<?int>  $offsets
	 */
	private function parseLink(ParseContext $ctx, array $m, array $offsets): ?Nodes\InlineNode
	{
		$label = substr($m['brk'], 1, -1);

		// code spans, autolinks and raw HTML take precedence: refuse, the position goes back
		if (substr_count($label, '`') % 2 || substr_count($label, '<') !== substr_count($label, '>')) {
			return null;
		}

		$content = $ctx->parseInline($label, $offsets['brk'] + 1);
		if (NodeHelpers::findFirst($content, fn($node) => $node instanceof LinkNode)) {
			return null; // links may not nest
		}

		$range = new Range($offsets[0], strlen($m[0]));
		if (($m['inl'] ?? null) !== null) {
			return new LinkNode(self::cleanDestination($m['dest'] ?? ''), $content, self::cleanTitle($m['ttl'] ?? null), $range);
		}

		$node = new LinkNode(self::reference($m['ref'] ?? '', $label), $content, null, $range);
		$node->fallback = ['[', ']' . ($m['ref'] ?? '')];
		return $node;
	}


	/** A URL placeholder for a reference that only the transform phase can resolve. */
	private static function reference(string $suffix, string $label): string
	{
		return "\0ref:" . self::normalizeLabel($suffix !== '' && $suffix !== '[]' ? substr($suffix, 1, -1) : $label);
	}


	private static function cleanDestination(string $dest): string
	{
		if (str_starts_with($dest, '<') && str_ends_with($dest, '>')) {
			$dest = substr($dest, 1, -1);
		}
		return self::unescape($dest);
	}


	private static function cleanTitle(?string $title): ?string
	{
		return $title === null ? null : self::unescape(substr($title, 1, -1));
	}


	private static function unescape(string $s): string
	{
		$s = preg_replace('~\\\([!"\#$%&\'()*+,\-./:;<=>?@\[\\\\\]^_`{|}\~])~', '$1', $s);
		return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}


	private static function normalizeLabel(string $s): string
	{
		return mb_strtolower(preg_replace('~\s+~', ' ', trim($s)), 'UTF-8');
	}


	/********************* transform phase ****************d*g**/


	/**
	 * Pairs emphasis and resolves references - both need the whole tree, which
	 * no syntax handler has.
	 */
	private function transform(Nodes\DocumentNode $document): void
	{
		$walk = function (Node $node) use (&$walk): void {
			if (!$node instanceof Nodes\ContentNode) {
				foreach ($node->getChildren() as $child) {
					$walk($child);
				}
				return;
			}

			$this->processEmphasis($node);

			$res = [];
			foreach ($node->children as $child) {
				$ref = ($child instanceof LinkNode || $child instanceof ImageNode) && str_starts_with($child->url, "\0ref:")
					? substr($child->url, strlen("\0ref:"))
					: null;

				if ($ref !== null && isset($this->linkDefs[$ref])) {
					$child->url = $this->linkDefs[$ref]['url'];
					$child->title = $this->linkDefs[$ref]['title'];

				} elseif ($ref !== null) {
					// unresolved reference degrades to literal text, content stays parsed
					$inner = $child instanceof ImageNode ? $child->alt : $child->content;
					$walk($inner);
					$res[] = new Nodes\TextNode($child->fallback[0]);
					$res = array_merge($res, $inner->children);
					$res[] = new Nodes\TextNode($child->fallback[1]);
					continue;
				}

				$walk($child);
				$res[] = $child;
			}

			$node->children = $res;
		};

		$walk($document);
	}


	/** The spec's "process emphasis" algorithm over the flat child list of one container. */
	private function processEmphasis(Nodes\ContentNode $container): void
	{
		$nodes = $container->children;
		$delims = array_values(array_filter($nodes, fn($node) => $node instanceof DelimiterNode));
		$openersBottom = [];
		$pos = 0;

		while ($pos < count($delims)) {
			$closer = $delims[$pos];
			if (!$closer->canClose) {
				$pos++;
				continue;
			}

			$key = $closer->char . ($closer->canOpen ? 'o' : '-') . ($closer->origLength % 3);
			$bottom = $openersBottom[$key] ?? -1;
			$openerPos = null;

			for ($i = $pos - 1; $i > $bottom; $i--) {
				$candidate = $delims[$i];
				if ($candidate->char !== $closer->char || !$candidate->canOpen) {
					continue;
				}

				// the "rule of three"
				$forbidden = ($candidate->canClose || $closer->canOpen)
					&& ($candidate->origLength + $closer->origLength) % 3 === 0
					&& !($candidate->origLength % 3 === 0 && $closer->origLength % 3 === 0);

				if (!$forbidden) {
					$openerPos = $i;
					break;
				}
			}

			if ($openerPos === null) {
				$openersBottom[$key] = $pos - 1;
				if ($closer->canOpen) {
					$pos++;
				} else {
					array_splice($delims, $pos, 1);
				}
				continue;
			}

			$opener = $delims[$openerPos];
			$use = $opener->remaining >= 2 && $closer->remaining >= 2 ? 2 : 1;

			$from = array_search($opener, $nodes, true);
			$to = array_search($closer, $nodes, true);
			$phrase = new Nodes\PhraseNode(
				new Nodes\ContentNode(array_slice($nodes, $from + 1, $to - $from - 1)),
				$use === 2 ? 'strong' : 'em',
			);

			$opener->remaining -= $use;
			$closer->remaining -= $use;
			$opener->text = substr($opener->text, 0, $opener->remaining);
			$closer->text = substr($closer->text, $use);

			$nodes = array_merge(array_slice($nodes, 0, $from + 1), [$phrase], array_slice($nodes, $to));

			// delimiters inside the new pair can never match again
			array_splice($delims, $openerPos + 1, $pos - $openerPos - 1);
			$pos = $openerPos + 1;

			if ($closer->remaining === 0) {
				$nodes = array_values(array_filter($nodes, fn($node) => $node !== $closer));
				array_splice($delims, $pos, 1);
			}
			if ($opener->remaining === 0) {
				$nodes = array_values(array_filter($nodes, fn($node) => $node !== $opener));
				array_splice($delims, $openerPos, 1);
				$pos--;
			}
		}

		$container->children = array_map(
			fn($node) => $node instanceof DelimiterNode ? new Nodes\TextNode($node->text, $node->range) : $node,
			$nodes,
		);
	}
}
