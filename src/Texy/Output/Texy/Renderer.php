<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Texy;

use Texy\{Modifier, Node, Nodes, Range, Regexp, Syntax};
use Texy\Output\NodeRenderer;
use Texy\Parsing\OffsetMap;
use function count, strlen;
use const ENT_HTML5, ENT_QUOTES;


/**
 * Prints a syntax tree as Texy source. A node is printed in the variant of its notation
 * it records (link syntax, bullet, heading marker), anything else in one canonical form,
 * so parsing the result gives the same tree. Text that would read as syntax is escaped
 * with entities, which parse back to the same text.
 * @extends NodeRenderer<string>
 */
final class Renderer extends NodeRenderer
{
	private const Indent = '  ';

	/** delimiters of paired phrases, opening and closing */
	private const Phrases = [
		Syntax::StrongEmphasis => ['***', '***'],
		Syntax::Strong => ['**', '**'],
		Syntax::Emphasis => ['//', '//'],
		Syntax::EmphasisSingleAsterisk => ['*', '*'],
		Syntax::EmphasisSingleAsterisk2 => ['*', '*'],
		Syntax::Inserted => ['++', '++'],
		Syntax::Deleted => ['--', '--'],
		Syntax::Superscript => ['^^', '^^'],
		Syntax::Subscript => ['__', '__'],
		Syntax::SpanQuotes => ['"', '"'],
		Syntax::SpanTilde => ['~', '~'],
		Syntax::Quote => ['>>', '<<'],
	];

	/** underline characters of headings by level, as HeadingModule::$levels has them by default */
	private const HeadingMarkers = ['#', '*', '=', '-'];

	/** the first bullet of a list and the ones that go on with it, as ListModule::$bullets has them by default */
	private const ListBullets = [
		['\*[ \t]', '\*[ \t]'],
		['[\x{2013}-](?![>-])', '[\x{2013}-](?![>-])'],
		['\+[ \t]', '\+[ \t]'],
		['1\.[ \t]', '\d{1,3}\.[ \t]'],
		['\d{1,3}\)[ \t]', '\d{1,3}\)[ \t]'],
		['I\.[ \t]', '[IVX]{1,4}\.[ \t]'],
		['[IVX]+\)[ \t]', '[IVX]+\)[ \t]'],
		['[a-z]\)[ \t]', '[a-z]\)[ \t]'],
		['[A-Z]\)[ \t]', '[A-Z]\)[ \t]'],
	];

	private const RomanDigits = [
		'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50,
		'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1,
	];

	/** a line that starts a block, so a paragraph must not start so */
	private const BlockStart = '~\A(?:
		/-- | ``` | \\\-- | >[ \t:] | \| | [*+][ \t] | [\x{2013}-](?![>-]) | \d{1,3}[.)] | [IVX]+[.)] | [a-zA-Z]\)[ \t]
		| \[[^\[\]\#?*\n]+\]:[ \t] | \[\*[^\n]+\*\]:[ \t] | \#{2} | ={2} | (?:\*{3,} | -{3,})(?=[ \t]*(?:\.[^\n]*)?$)
	)~umx';


	public function __construct()
	{
		$this->handlers = [
			Nodes\DocumentNode::class => fn(Nodes\DocumentNode $n, self $r) => $r->renderBlocks($n->content->children),
			Nodes\ContentNode::class => fn(Nodes\ContentNode $n, self $r) => isset($n->children[0])
				&& $n->children[0] instanceof Nodes\BlockNode
				? $r->renderBlocks($n->children)
				: $r->renderInline($n),
			Nodes\TextNode::class => fn(Nodes\TextNode $n) => self::renderText($n),

			// Block nodes
			Nodes\ParagraphNode::class => fn(Nodes\ParagraphNode $n, self $r) => $r->renderParagraph($n),
			Nodes\HeadingNode::class => fn(Nodes\HeadingNode $n, self $r) => $r->renderHeading($n),
			Nodes\BlockQuoteNode::class => fn(Nodes\BlockQuoteNode $n, self $r) => $r->renderBlockQuote($n),
			Nodes\CodeBlockNode::class => fn(Nodes\CodeBlockNode $n) => self::renderCodeBlock($n),
			Nodes\SectionNode::class => fn(Nodes\SectionNode $n, self $r) => '/--' . ($n->type ?? 'div') . self::formatModifier($n->modifier)
				. "\n" . $r->renderBlocks($n->content->children) . "\n\\--",
			Nodes\CommentNode::class => fn(Nodes\CommentNode $n) => "/--comment\n" . $n->text . "\n\\--",
			Nodes\HorizontalRuleNode::class => fn(Nodes\HorizontalRuleNode $n) => str_repeat($n->type, 3)
				. self::formatModifier($n->modifier),
			Nodes\FigureNode::class => fn(Nodes\FigureNode $n, self $r) => $r->renderNode($n->image)
				. ($n->caption === null ? '' : ' *** ' . $r->renderInline($n->caption))
				. self::formatModifier($n->modifier),

			// List nodes
			Nodes\ListNode::class => fn(Nodes\ListNode $n, self $r) => $r->renderList($n),
			Nodes\DefinitionListNode::class => fn(Nodes\DefinitionListNode $n, self $r) => $r->renderDefinitionList($n),

			// Table nodes
			Nodes\TableNode::class => fn(Nodes\TableNode $n, self $r) => $r->renderTable($n),

			// Definitions
			Nodes\LinkDefinitionNode::class => fn(Nodes\LinkDefinitionNode $n) => '[' . $n->reference . ']: ' . $n->url,
			Nodes\ImageDefinitionNode::class => fn(Nodes\ImageDefinitionNode $n) => '[*' . $n->reference . '*]: '
				. self::formatImageSource($n->url, $n->width, $n->height) . self::formatModifier($n->modifier),

			// Inline nodes
			Nodes\PhraseNode::class => fn(Nodes\PhraseNode $n, self $r) => $r->renderPhrase($n),
			Nodes\LinkNode::class => fn(Nodes\LinkNode $n, self $r) => $r->renderLink($n),
			Nodes\ImageNode::class => fn(Nodes\ImageNode $n) => self::renderImage($n),
			Nodes\RawTextNode::class => fn(Nodes\RawTextNode $n) => "''" . $n->text . "''",
			Nodes\AnnotationNode::class => fn(Nodes\AnnotationNode $n) => self::escape($n->text) . '((' . $n->annotation . '))',
			Nodes\EmoticonNode::class => fn(Nodes\EmoticonNode $n) => $n->emoticon,
			Nodes\LineBreakNode::class => fn() => "\n ",
			Nodes\UrlNode::class => fn(Nodes\UrlNode $n) => $n->url,
			Nodes\EmailNode::class => fn(Nodes\EmailNode $n) => $n->email,
			Nodes\DirectiveNode::class => fn(Nodes\DirectiveNode $n) => '{{' . self::restoreBreaks($n->text) . '}}',

			// HTML passthrough nodes
			Nodes\HtmlTagNode::class => fn(Nodes\HtmlTagNode $n) => self::renderTag($n),
			Nodes\HtmlElementNode::class => fn(Nodes\HtmlElementNode $n, self $r) => self::renderTag(
				new Nodes\HtmlTagNode($n->name, $n->attributes),
			)
				. $r->renderInline($n->content)
				. ($n->closingTag === null ? '' : self::renderTag($n->closingTag)),
			Nodes\HtmlCommentNode::class => fn(Nodes\HtmlCommentNode $n) => '<!--' . self::restoreBreaks($n->text) . '-->',
		];
	}


	/**
	 * Prints the syntax tree as Texy source.
	 */
	public function render(Nodes\DocumentNode $document): string
	{
		$source = $this->renderNode($document);
		return $source === '' ? '' : $source . "\n";
	}


	protected function renderUnknown(Node $node): string
	{
		throw new \LogicException('No renderer prints node class ' . $node::class . ' as Texy; add one with addRenderer().');
	}


	private function renderParagraph(Nodes\ParagraphNode $node): string
	{
		$children = $node->content->children;
		$text = $this->renderInline($node->content);
		if (($children[0] ?? null) instanceof Nodes\TextNode) {
			$text = self::escapeBlockStart($text);
		}

		// a first line ending with a colon and an indented bullet below make a definition list
		$text = Regexp::replace($text, '~\A([^\n]*):(?=[ \t]*\n[ \t]+[*+\x{2013}-])~u', '$1&#58;');

		if (count($children) === 1 && !$children[0] instanceof Nodes\TextNode && str_starts_with($text, '[*')) {
			$text = ' ' . $text; // an image alone at the start of a line is a figure
		} elseif (str_starts_with($text, '<')) {
			// a paragraph starting with an HTML block element reads no modifier at its end
			return self::formatModifierLine($node->modifier) . $text;
		}

		// the modifier is read from the end of the first line that has one, together with
		// the spaces before it, so a space the text ends with needs a line in between
		$eol = strpos($text, "\n");
		$eol = $eol === false ? strlen($text) : $eol;
		$modifier = self::formatModifier($node->modifier);
		if ($modifier !== '' && $eol > 0 && ctype_space($text[$eol - 1])) {
			$modifier = "\n" . ltrim($modifier);
		}

		return substr_replace($text, $modifier, $eol, 0);
	}


	private function renderHeading(Nodes\HeadingNode $node): string
	{
		$text = $this->renderInline($node->content);
		$modifier = self::formatModifier($node->modifier);
		if ($node->type === Nodes\HeadingType::Surrounded) {
			// the closing marker keeps the text whole even when empty; a marker character
			// the text ends with would be taken for part of it
			$marker = $node->marker ?? str_repeat('#', max(2, 7 - $node->level));
			if (str_ends_with($text, $marker[0])) {
				$text = substr($text, 0, -1) . self::escapeChar($marker[0]);
			}

			return $marker . ' ' . $text . ' ' . $marker . $modifier;
		}

		$marker = $node->marker ?? self::HeadingMarkers[min($node->level, count(self::HeadingMarkers) - 1)];
		$text .= $modifier;
		return $text . "\n" . str_repeat($marker, max(3, strlen($text)));
	}


	private function renderBlockQuote(Nodes\BlockQuoteNode $node): string
	{
		$lines = explode("\n", $this->renderBlocks($node->content->children));
		return self::formatModifierLine($node->modifier)
			. implode("\n", array_map(fn($line) => $line === '' ? '>' : '> ' . $line, $lines));
	}


	private static function renderCodeBlock(Nodes\CodeBlockNode $node): string
	{
		$code = $node->code;
		$modifier = self::formatModifier($node->modifier);
		if ($node->type === 'code' && (self::isOutdentable($code) || preg_match('~^(?:\\\\--|/--)~m', $code))) {
			// /-- would strip the indentation and blank edge lines a fence keeps, and its
			// own markers in the code would nest or close it
			preg_match_all('~^`+~m', $code, $m);
			$fence = str_repeat('`', max([3, ...array_map(fn($s) => strlen($s) + 1, $m[0])]));
			return $fence . ($node->language ?? '') . $modifier . "\n" . $code . "\n" . $fence;
		}

		return '/--' . ($node->type === 'default' ? '' : $node->type) . ($node->language === null ? '' : ' ' . $node->language)
			. $modifier . "\n" . $code . "\n\\--";
	}


	/** Would /-- change the code, by stripping its shared indentation or its blank edge lines? */
	private static function isOutdentable(string $code): bool
	{
		[$outdented] = OffsetMap::outdentLines(OffsetMap::linesOf($code, 0));
		return $outdented !== $code;
	}


	private function renderList(Nodes\ListNode $node): string
	{
		$res = [];
		foreach ($node->items as $i => $item) {
			$bullet = $item->bullet ?? self::createBullet($node, $i);
			$res[] = $bullet . ' ' . $this->renderItemContent($item, self::Indent);
		}

		return self::formatModifierLine($node->modifier) . implode("\n", $res);
	}


	private function renderDefinitionList(Nodes\DefinitionListNode $node): string
	{
		$res = [];
		foreach ($node->items as $item) {
			$res[] = $item->term
				? $this->renderInline($item->content) . ':' . self::formatModifier($item->modifier)
				: self::Indent . ($item->bullet ?? '-') . ' ' . $this->renderItemContent($item, self::Indent . self::Indent);
		}

		return self::formatModifierLine($node->modifier) . implode("\n", $res);
	}


	/** The content of an item: its first line goes after the bullet, the others are indented. */
	private function renderItemContent(Nodes\ListItemNode $item, string $indent): string
	{
		$blocks = $item->content->children;
		$lines = explode("\n", $this->renderBlocks($blocks));
		if (($blocks[0] ?? null) instanceof Nodes\ParagraphNode && str_starts_with($lines[1] ?? '', ' ')) {
			$blocks[0] = self::breakFirstLine($blocks[0]);
			$lines = explode("\n", $this->renderBlocks($blocks));
		}

		$lines[0] .= self::formatModifier($item->modifier);
		foreach (array_slice($lines, 1, preserve_keys: true) as $i => $line) {
			$lines[$i] = $line === '' ? '' : $indent . $line;
		}

		return implode("\n", $lines);
	}


	/**
	 * The first line after the bullet sets how deep the other lines of an item are
	 * indented, so a hard break, which is an indented line, cannot follow it directly.
	 * A space of the text before the break becomes a newline, which reads as the space.
	 */
	private static function breakFirstLine(Nodes\ParagraphNode $paragraph): Nodes\ParagraphNode
	{
		$children = $paragraph->content->children;
		$end = count($children);
		foreach ($children as $i => $child) {
			if ($child instanceof Nodes\LineBreakNode) {
				$end = $i;
				break;
			}
		}

		for ($i = $end - 1; $i >= 0; $i--) {
			$text = $children[$i] instanceof Nodes\TextNode ? $children[$i]->text : '';
			preg_match_all('~(?<=\S) (?=\S)~', $text, $spaces, PREG_OFFSET_CAPTURE);
			foreach (array_reverse($spaces[0]) as [, $pos]) {
				$rest = substr($text, $pos + 1);
				if (self::escapeBlockStart($rest) === $rest) {
					$text = substr_replace($text, "\n", $pos, 1);
					$broken = clone $paragraph;
					$broken->content = clone $paragraph->content;
					$broken->content->children[$i] = new Nodes\TextNode($text, new Range(0, strlen($text)));
					return $broken;
				}
			}
		}

		return $paragraph;
	}


	/** Bullet of an item that does not record its own: the canonical one of the list type. */
	private static function createBullet(Nodes\ListNode $list, int $index): string
	{
		$n = ($list->start ?? 1) + $index;
		return match ($list->type) {
			Nodes\ListType::Unordered => '-',
			// "1." needs two items and cannot start elsewhere, "1)" can
			Nodes\ListType::Decimal => $list->start === null && count($list->items) > 1 ? $n . '.' : $n . ')',
			Nodes\ListType::UpperRoman => self::toRoman($n) . ')',
			Nodes\ListType::LowerRoman => strtolower(self::toRoman($n)) . ')',
			Nodes\ListType::UpperAlpha => (range('A', 'Z')[$n - 1] ?? $n) . ')',
			Nodes\ListType::LowerAlpha => (range('a', 'z')[$n - 1] ?? $n) . ')',
		};
	}


	private static function toRoman(int $n): string
	{
		$res = '';
		foreach (self::RomanDigits as $digit => $value) {
			$res .= str_repeat($digit, intdiv($n, $value));
			$n %= $value;
		}

		return $res;
	}


	private function renderTable(Nodes\TableNode $node): string
	{
		$lines = [];
		if ($node->caption !== null) {
			$caption = $this->renderInline($node->caption);
			$lines[] = '|## ' . $caption . (preg_match('~[|#=]$~', $caption) ? ' |' : '');
		}

		$separator = '|' . str_repeat('-', 30);
		$isHead = false;
		$spans = []; // column => [cell spanning into the next rows, rows left, lines left]
		foreach ($node->rows as $row) {
			if ($row->header !== $isHead) {
				$lines[] = $separator;
				$isHead = $row->header;
			}

			$line = '';
			$col = 0;
			$cells = $row->cells;
			while ($cells || isset($spans[$col])) {
				if (isset($spans[$col])) {
					// a line of a cell above that continues in this row
					[$cell, $left, $cellLines] = $spans[$col];
					$line .= '|' . (array_shift($cellLines) ?? '') . ' ^';
					$spans[$col] = [$cell, $left - 1, $cellLines];
					if ($left <= 1) {
						unset($spans[$col]);
					}

					$col += $cell->colspan;
					continue;
				}

				$cell = array_shift($cells);
				if ($cell === null) {
					break; // a column no cell of this row reaches
				}

				$cellLines = explode("\n", $this->renderCell($cell));
				$line .= (!$row->header && $cell->header ? '|* ' : '| ')
					. array_shift($cellLines)
					. self::formatModifier($cell->modifier, vertical: true)
					. ' ' . str_repeat('|', $cell->colspan - 1);
				if ($cell->rowspan > 1) {
					$spans[$col] = [$cell, $cell->rowspan - 1, $cellLines];
				}

				$col += $cell->colspan;
			}

			$lines[] = $line . '|' . self::formatModifier($row->modifier, vertical: true);
		}

		if ($isHead) {
			$lines[] = $separator;
		}

		return self::formatModifierLine($node->modifier, vertical: true) . implode("\n", $lines);
	}


	private function renderCell(Nodes\TableCellNode $cell): string
	{
		$children = $cell->content->children;
		if (count($children) === 1 && $children[0] instanceof Nodes\TextNode && $children[0]->text === "\u{A0}") {
			return '';
		} elseif (($children[0] ?? null) instanceof Nodes\BlockNode) {
			// a blank line takes a whole row of the table, so only two paragraphs get one
			$text = '';
			foreach ($children as $i => $block) {
				$text .= ($i === 0
					? ''
					: ($block instanceof Nodes\ParagraphNode && $children[$i - 1] instanceof Nodes\ParagraphNode ? "\n\n" : "\n"))
					. $this->renderNode($block);
			}
		} else {
			$text = $this->renderInline($cell->content);
		}

		// a dot at the start would read as the modifier of the column
		return str_starts_with($text, '.') ? self::escapeChar('.') . substr($text, 1) : $text;
	}


	private function renderPhrase(Nodes\PhraseNode $node): string
	{
		$modifier = $node->modifier;
		if ($node->type === Syntax::Code) {
			return '`' . self::textOf($node->content) . self::formatModifier($modifier) . '`';

		} elseif ($node->type === Syntax::SuperscriptShort || $node->type === Syntax::SubscriptShort) {
			$text = str_replace("\u{2212}", '-', self::textOf($node->content));
			return ($node->type === Syntax::SuperscriptShort ? '^' : '_') . $text;

		} elseif ($node->type === Syntax::Abbreviation || $node->type === Syntax::AbbreviationQuotes) {
			// the explanation is the title of the modifier
			$rest = $modifier === null ? null : clone $modifier;
			if ($rest !== null) {
				$rest->title = null;
			}

			$text = self::escape(self::textOf($node->content));
			return ($node->type === Syntax::Abbreviation ? $text : '"' . $text . self::formatModifier($rest) . '"')
				. '((' . ($modifier->title ?? '') . '))';
		}

		[$open, $close] = self::Phrases[$node->type] ?? self::Phrases[Syntax::SpanQuotes];
		$modifier = self::formatModifier($modifier);
		if ($modifier === '' && ($node->type === Syntax::SpanQuotes || $node->type === Syntax::SpanTilde)) {
			$modifier = ' .{ }'; // a span is a phrase only with a modifier, even an empty one
		}

		return $open . $this->renderInline($node->content) . $modifier . $close;
	}


	private function renderLink(Nodes\LinkNode $node): string
	{
		$target = $node->url ?? ($node->isImageLink ? '[*' . $node->ref . '*]' : '[' . $node->ref . ']');
		$first = $node->content->children[0] ?? null;
		$quote = $node->syntax === Syntax::SpanTilde ? '~' : '"';
		return match (true) {
			$node->syntax === Syntax::LinkReference => '[' . $node->ref . ']',
			$node->syntax === Syntax::WikiLink => '[' . $this->renderInline($node->content) . '|' . $target . ']',
			$node->syntax === Syntax::MarkdownLink => '[' . $this->renderInline($node->content) . '](' . $target . ')',
			$node->syntax === Syntax::QuickLink => $this->renderInline($node->content) . ':' . $target,
			($node->syntax === Syntax::Image || $node->syntax === Syntax::Figure) && $first instanceof Nodes\ImageNode
				=> self::renderImage($first) . ':' . ($node->isImageLink && $node->ref === null ? ':' : $target),
			$first instanceof Nodes\PhraseNode && count($node->content->children) === 1 && $node->syntax === $first->type
				=> $this->renderPhrase($first) . ':' . $target,
			default => $quote . $this->renderInline($node->content) . self::formatModifier($node->modifier) . $quote . ':' . $target,
		};
	}


	private static function renderImage(Nodes\ImageNode $node): string
	{
		// alignment to the left or right is written as the closing character
		$modifier = $node->modifier === null ? null : clone $node->modifier;
		$align = match ($modifier?->hAlign) {
			'left' => '<',
			'right' => '>',
			default => '*',
		};
		if ($align !== '*') {
			$modifier->hAlign = null;
		}

		return '[* '
			. self::formatImageSource($node->url, $node->width, $node->height)
			. self::formatModifier($modifier)
			. ' '
			. $align
			. ']';
	}


	private static function renderTag(Nodes\HtmlTagNode $node): string
	{
		if ($node->closing) {
			return '</' . $node->name . '>';
		}

		$attrs = '';
		foreach ($node->attributes as $name => $value) {
			foreach ((array) $value as $item) {
				$attrs .= ' ' . $name
					. ($item === true ? '' : '="' . htmlspecialchars(self::restoreBreaks((string) $item), ENT_QUOTES | ENT_HTML5) . '"');
			}
		}

		return '<' . $node->name . $attrs . ($node->selfClosing ? ' />' : '>');
	}


	/**
	 * Blocks separated by blank lines; a list by two of them where its neighbour would
	 * read as one more of its items.
	 * @param  array<Node>  $blocks
	 */
	private function renderBlocks(array $blocks): string
	{
		$res = '';
		$previous = $previousPrinted = null;
		foreach ($blocks as $block) {
			$printed = $this->renderNode($block);
			if ($previous !== null) {
				$eol = strrpos($res, "\n");
				$lastLine = $eol === false ? $res : substr($res, $eol + 1);
				$joins = ($previous instanceof Nodes\ListNode && self::continuesList($previousPrinted, $printed))
					|| ($block instanceof Nodes\ListNode && self::continuesList($lastLine, $printed));
				$res .= $joins || $previous instanceof Nodes\DefinitionListNode ? "\n\n\n" : "\n\n";
			}

			$res .= $printed;
			$previous = $block;
			$previousPrinted = $printed;
		}

		return $res;
	}


	/** Would a list started by the first line go on with the second one, as ListModule reads them? */
	private static function continuesList(string $first, string $second): bool
	{
		foreach (self::ListBullets as [$start, $next]) {
			if (preg_match('~' . $start . '~Au', $first)) {
				return (bool) preg_match('~' . $next . '~Au', $second);
			}
		}

		return false;
	}


	private function renderInline(Nodes\ContentNode $content): string
	{
		return implode('', array_map($this->renderNode(...), $content->children));
	}


	/**
	 * Text as it was written: a single character whose notation is longer was an entity
	 * or an escaped asterisk and is escaped again.
	 */
	private static function renderText(Nodes\TextNode $node): string
	{
		$text = self::restoreBreaks($node->text);
		if ($node->range === null) {
			return self::escape($text);
		} elseif ($node->range->length === strlen($text) || mb_strlen($text) !== 1) {
			return $text;
		}

		return self::escapeChar($text);
	}


	/** The literal text of a node whose content is not parsed: code, abbreviations. */
	private static function textOf(Nodes\ContentNode $content): string
	{
		$text = '';
		foreach ($content->children as $child) {
			$text .= $child instanceof Nodes\TextNode ? $child->text : '';
		}

		return $text;
	}


	private static function formatImageSource(?string $url, ?int $width, ?int $height): string
	{
		return ($url ?? '')
			. ($width === null && $height === null ? '' : ' ' . ($width ?? '?') . 'x' . ($height ?? '?'));
	}


	/** Modifier written after content, with the space it needs. */
	private static function formatModifier(?Modifier $modifier, bool $vertical = false): string
	{
		$s = self::formatModifierBody($modifier, $vertical);
		return $s === '' ? '' : ' .' . $s;
	}


	/** Modifier written on a line of its own before a block. */
	private static function formatModifierLine(?Modifier $modifier, bool $vertical = false): string
	{
		$s = self::formatModifierBody($modifier, $vertical);
		return $s === '' ? '' : '.' . $s . "\n";
	}


	private static function formatModifierBody(?Modifier $modifier, bool $vertical): string
	{
		if ($modifier === null) {
			return '';
		}

		$s = '';
		if ($modifier->title !== null) {
			$s .= '(' . ($modifier->title === '' ? ' ' : str_replace(')', '\)', self::escape($modifier->title))) . ')';
		}

		$classes = array_keys($modifier->classes);
		if ($modifier->id !== null) {
			$classes[] = '#' . $modifier->id;
		}

		if ($classes) {
			$s .= '[' . implode(' ', $classes) . ']';
		}

		$styles = [];
		foreach ($modifier->attrs + $modifier->styles as $name => $value) {
			foreach ((array) $value as $item) {
				$styles[] = $name . ': ' . $item;
			}
		}

		if ($styles) {
			$s .= '{' . implode('; ', $styles) . '}';
		}

		$s .= match ($modifier->hAlign) {
			'left' => '<',
			'right' => '>',
			'center' => '<>',
			'justify' => '=',
			default => '',
		};
		if ($vertical) {
			$s .= match ($modifier->vAlign) {
				'top' => '^',
				'middle' => '-',
				'bottom' => '_',
				default => '',
			};
		}

		return $s;
	}


	/** Escapes text that would otherwise read as syntax. */
	private static function escape(string $text): string
	{
		return Regexp::replace(
			$text,
			'~&(?=\#?[a-z0-9]+;)|<(?=[a-z/!])~i',
			fn($m) => self::escapeChar($m[0] ?? ''),
		);
	}


	/** A character written so that it cannot read as syntax. */
	private static function escapeChar(string $char): string
	{
		return match ($char) {
			'*' => '\*',
			'&' => '&amp;',
			'<' => '&lt;',
			'>' => '&gt;',
			'"' => '&quot;',
			default => '&#' . mb_ord($char) . ';',
		};
	}


	/** A paragraph must not start the way a block does, or it would be read as one. */
	private static function escapeBlockStart(string $text): string
	{
		return preg_match(self::BlockStart, $text)
			? self::escapeChar(mb_substr($text, 0, 1)) . mb_substr($text, 1)
			: $text;
	}


	/**
	 * A paragraph joins its lines and marks a hard break, a line indented in the source,
	 * with \r; nodes the break falls into keep the mark.
	 */
	private static function restoreBreaks(string $text): string
	{
		return str_replace("\r", "\n ", $text);
	}
}
