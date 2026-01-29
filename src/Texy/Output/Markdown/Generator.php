<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Output\Markdown;

use Texy\Helpers as TexyHelpers;
use Texy\Node;
use Texy\Nodes;
use Texy\Syntax;
use Texy\Texy;
use function array_map, count, explode, implode, is_string, max, mb_strlen, preg_match, rtrim, str_repeat, strlen, strncasecmp, trim;


/**
 * Generates Markdown (GFM) output from AST.
 */
class Generator
{
	// =========================================================================
	// Markdown rendering options
	// =========================================================================

	/** Heading style: 'atx' (###) or 'setext' (===) */
	public string $headingStyle = 'atx';

	/** Code block style: 'fenced' (```) or 'indented' */
	public string $codeBlockStyle = 'fenced';

	/** Fence character for code blocks */
	public string $codeFence = '```';

	/** Marker for unordered lists */
	public string $unorderedListMarker = '-';

	/** Delimiter for ordered lists */
	public string $orderedListDelimiter = '.';

	/** Emphasis marker */
	public string $emphasisMarker = '*';

	/** Strong marker */
	public string $strongMarker = '**';

	/** Link style: 'inline' or 'reference' */
	public string $linkStyle = 'inline';

	/** Horizontal rule style */
	public string $horizontalRule = '---';

	/** Escape special characters in text */
	public bool $escapeSpecialChars = true;

	/** Shorten URLs in autolinks */
	public bool $shortenUrls = false;

	/** @var array<class-string<Node>, \Closure(Node, self): string> */
	private array $handlers = [];

	/** @var array<int|string, array{url: string, title: ?string}> collected link references */
	private array $linkReferences = [];

	private int $referenceCounter = 0;


	public function __construct(
		private Texy $texy,
	) {
		$this->handlers = [
			// Core nodes
			Nodes\DocumentNode::class => fn(Nodes\DocumentNode $n, self $g) => $g->renderDocument($n),
			Nodes\TextNode::class => fn(Nodes\TextNode $n, self $g) => $g->renderText($n),
			Nodes\ContentNode::class => fn(Nodes\ContentNode $n, self $g) => $g->renderNodes($n->children),

			// Block nodes
			Nodes\ParagraphNode::class => fn(Nodes\ParagraphNode $n, self $g) => $g->renderParagraph($n),
			Nodes\HeadingNode::class => fn(Nodes\HeadingNode $n, self $g) => $g->renderHeading($n),
			Nodes\BlockQuoteNode::class => fn(Nodes\BlockQuoteNode $n, self $g) => $g->renderBlockQuote($n),
			Nodes\CodeBlockNode::class => fn(Nodes\CodeBlockNode $n, self $g) => $g->renderCodeBlock($n),
			Nodes\SectionNode::class => fn(Nodes\SectionNode $n, self $g) => $g->renderSection($n),
			Nodes\HorizontalRuleNode::class => fn(Nodes\HorizontalRuleNode $n, self $g) => $g->renderHorizontalRule($n),
			Nodes\FigureNode::class => fn(Nodes\FigureNode $n, self $g) => $g->renderFigure($n),

			// List nodes
			Nodes\ListNode::class => fn(Nodes\ListNode $n, self $g) => $g->renderList($n),
			Nodes\ListItemNode::class => fn(Nodes\ListItemNode $n, self $g) => $g->renderListItem($n),
			Nodes\DefinitionListNode::class => fn(Nodes\DefinitionListNode $n, self $g) => $g->renderDefinitionList($n),

			// Table nodes
			Nodes\TableNode::class => fn(Nodes\TableNode $n, self $g) => $g->renderTable($n),
			Nodes\TableRowNode::class => fn(Nodes\TableRowNode $n, self $g) => $g->renderTableRow($n),
			Nodes\TableCellNode::class => fn(Nodes\TableCellNode $n, self $g) => $g->renderTableCell($n),

			// Inline nodes
			Nodes\ImageNode::class => fn(Nodes\ImageNode $n, self $g) => $g->renderImage($n),
			Nodes\LinkNode::class => fn(Nodes\LinkNode $n, self $g) => $g->renderLink($n),
			Nodes\PhraseNode::class => fn(Nodes\PhraseNode $n, self $g) => $g->renderPhrase($n),
			Nodes\RawTextNode::class => fn(Nodes\RawTextNode $n) => Helpers::escapeText($n->content),
			Nodes\AnnotationNode::class => fn(Nodes\AnnotationNode $n) => '<abbr title="' . htmlspecialchars($n->annotation, ENT_QUOTES, 'UTF-8') . '">' . $n->content . '</abbr>',
			Nodes\EmoticonNode::class => fn(Nodes\EmoticonNode $n, self $g) => $g->texy->emoticonModule->icons[$n->emoticon] ?? $n->emoticon,
			Nodes\LineBreakNode::class => fn() => "  \n",

			// Autolink nodes
			Nodes\UrlNode::class => fn(Nodes\UrlNode $n, self $g) => $g->renderUrl($n),
			Nodes\EmailNode::class => fn(Nodes\EmailNode $n, self $g) => $g->renderEmail($n),

			// HTML passthrough nodes
			Nodes\HtmlTagNode::class => fn(Nodes\HtmlTagNode $n, self $g) => $g->renderHtmlTag($n),
			Nodes\HtmlCommentNode::class => fn(Nodes\HtmlCommentNode $n) => '<!-- ' . trim($n->content) . ' -->',

			// Directive nodes
			Nodes\DirectiveNode::class => fn(Nodes\DirectiveNode $n) => '{{' . $n->content . '}}',

			// Definition nodes (output empty string - handled in document)
			Nodes\ImageDefinitionNode::class => fn() => '',
			Nodes\LinkDefinitionNode::class => fn() => '',
			Nodes\CommentNode::class => fn() => '',
		];
	}


	/**
	 * Register a handler for a node class.
	 * Return null to delegate to previous handler.
	 * @param \Closure(Node, self, ?\Closure): ?string $handler
	 */
	public function registerHandler(\Closure $handler): void
	{
		/** @var class-string<Node> $nodeClass */
		$nodeClass = (string) (new \ReflectionFunction($handler))->getParameters()[0]->getType();
		$previous = $this->handlers[$nodeClass] ?? null;
		$this->handlers[$nodeClass] = static function (Node $node, self $gen) use ($handler, $previous): string {
			/** @var \Closure(Node, self, \Closure|null): ?string $handler */
			$result = $handler($node, $gen, $previous);
			if ($result !== null) {
				return $result;
			}
			if ($previous === null) {
				throw new \LogicException('No handler for node class ' . $node::class);
			}
			return $previous($node, $gen);
		};
	}


	/**
	 * Generate Markdown from AST.
	 */
	public function render(Nodes\DocumentNode $document): string
	{
		$this->linkReferences = [];
		$this->referenceCounter = 0;

		$content = $this->renderNode($document);

		// Append link references at the end if using reference style
		if ($this->linkReferences) {
			$content .= "\n";
			foreach ($this->linkReferences as $ref => $data) {
				$title = $data['title'] ? ' "' . Helpers::escapeTitle($data['title']) . '"' : '';
				$content .= "\n[" . $ref . ']: ' . $data['url'] . $title;
			}
		}

		return rtrim($content) . "\n";
	}


	/**
	 * Generate Markdown from a node.
	 */
	public function renderNode(Node $node): string
	{
		$handler = $this->handlers[$node::class] ?? null;
		if ($handler === null) {
			throw new \LogicException('No handler for node class ' . $node::class);
		}
		return $handler($node, $this);
	}


	/**
	 * Generate inline content as string.
	 * @param array<Node> $content
	 */
	public function renderNodes(array $content): string
	{
		$result = [];
		foreach ($content as $child) {
			$result[] = $this->renderNode($child);
		}
		return implode('', $result);
	}


	/**
	 * Generate block content as string with double newlines.
	 * @param array<Node> $content
	 */
	public function renderBlockContent(array $content): string
	{
		$result = [];
		foreach ($content as $child) {
			$generated = $this->renderNode($child);
			if ($generated !== '') {
				$result[] = $generated;
			}
		}
		return implode("\n", $result);
	}


	// =========================================================================
	// Core node generators
	// =========================================================================

	private function renderDocument(Nodes\DocumentNode $node): string
	{
		return $this->renderBlockContent($node->content->children);
	}


	private function renderText(Nodes\TextNode $node): string
	{
		return $this->escapeSpecialChars
			? Helpers::escapeText($node->content)
			: $node->content;
	}


	// =========================================================================
	// Block node generators
	// =========================================================================

	private function renderParagraph(Nodes\ParagraphNode $node): string
	{
		$content = $this->renderNodes($node->content->children);
		return $content . "\n";
	}


	private function renderHeading(Nodes\HeadingNode $node): string
	{
		$content = $this->renderNodes($node->content->children);

		if ($this->headingStyle === 'setext' && $node->level <= 2) {
			$underline = $node->level === 1 ? '=' : '-';
			$len = mb_strlen($content);
			return $content . "\n" . str_repeat($underline, max(3, $len)) . "\n";
		}

		return str_repeat('#', $node->level) . ' ' . $content . "\n";
	}


	private function renderBlockQuote(Nodes\BlockQuoteNode $node): string
	{
		$content = $this->renderBlockContent($node->content->children);
		return Helpers::prefixLines(rtrim($content), '> ') . "\n";
	}


	private function renderCodeBlock(Nodes\CodeBlockNode $node): string
	{
		$content = $node->content;

		// Skip special types that don't translate well to Markdown
		if ($node->type === 'html' || $node->type === 'text' || $node->type === 'texysource') {
			// Fallback to fenced code block
			$lang = $node->type === 'html' ? 'html' : '';
			return $this->codeFence . $lang . "\n" . $content . "\n" . $this->codeFence . "\n";
		}

		if ($this->codeBlockStyle === 'indented') {
			$lines = explode("\n", $content);
			return implode("\n", array_map(fn($l) => '    ' . $l, $lines)) . "\n";
		}

		// Fenced code block
		$lang = $node->language ?? '';
		return $this->codeFence . $lang . "\n" . $content . "\n" . $this->codeFence . "\n";
	}


	private function renderSection(Nodes\SectionNode $node): string
	{
		// Sections are transparent in Markdown - just output children
		return $this->renderBlockContent($node->content->children);
	}


	private function renderHorizontalRule(Nodes\HorizontalRuleNode $node): string
	{
		return $this->horizontalRule . "\n";
	}


	private function renderFigure(Nodes\FigureNode $node): string
	{
		$image = $this->renderNode($node->image);

		if ($node->caption === null) {
			return $image . "\n";
		}

		$caption = $this->renderNodes($node->caption->children);
		return $image . "\n\n*" . trim($caption) . "*\n";
	}


	// =========================================================================
	// List node generators
	// =========================================================================

	private function renderList(Nodes\ListNode $node, int $indent = 0): string
	{
		$result = [];
		$counter = $node->start ?? 1;
		$indentStr = str_repeat(' ', $indent);

		foreach ($node->items as $item) {
			$marker = $node->type->isOrdered()
				? $counter . $this->orderedListDelimiter . ' '
				: $this->unorderedListMarker . ' ';

			$content = $this->renderListItemContent($item, strlen($marker));
			$result[] = $indentStr . $marker . $content;
			$counter++;
		}

		return implode("\n", $result) . "\n";
	}


	private function renderListItem(Nodes\ListItemNode $node): string
	{
		// This is used when ListItemNode is generated standalone
		$marker = $this->unorderedListMarker . ' ';
		return $marker . $this->renderListItemContent($node, strlen($marker));
	}


	private function renderListItemContent(Nodes\ListItemNode $node, int $markerWidth): string
	{
		$children = $node->content->children;

		// Simple case: single paragraph without nested blocks
		if (count($children) === 1 && $children[0] instanceof Nodes\ParagraphNode) {
			return trim($this->renderNodes($children[0]->content->children));
		}

		// Complex case: multiple blocks or nested lists
		$lines = [];
		$first = true;
		foreach ($children as $child) {
			$content = $this->renderNode($child);
			if ($first) {
				$lines[] = rtrim($content);
				$first = false;
			} else {
				// Indent continuation lines
				$indented = Helpers::indent(rtrim($content), $markerWidth);
				$lines[] = $indented;
			}
		}

		return implode("\n", $lines);
	}


	private function renderDefinitionList(Nodes\DefinitionListNode $node): string
	{
		// GFM doesn't support definition lists natively - use HTML fallback
		$result = ['<dl>'];

		foreach ($node->items as $item) {
			if ($item->term) {
				$content = $this->renderNodes($item->content->children);
				$result[] = '<dt>' . trim($content) . '</dt>';
			} else {
				$content = $this->renderBlockContent($item->content->children);
				$result[] = '<dd>' . trim($content) . '</dd>';
			}
		}

		$result[] = '</dl>';
		return implode("\n", $result) . "\n";
	}


	// =========================================================================
	// Table node generators (GFM)
	// =========================================================================

	private function renderTable(Nodes\TableNode $node): string
	{
		$rows = [];
		$headerDone = false;
		$columnAligns = [];

		foreach ($node->rows as $rowIndex => $row) {
			$cells = [];
			foreach ($row->cells as $cellIndex => $cell) {
				$content = Helpers::escapeTableCell(
					trim($this->renderNodes($cell->content->children)),
				);
				$cells[] = $content;

				// Collect alignment from first row
				if ($rowIndex === 0) {
					$columnAligns[$cellIndex] = $cell->modifier?->hAlign;
				}
			}

			$rows[] = '| ' . implode(' | ', $cells) . ' |';

			// Add separator after header row
			if ($row->header && !$headerDone) {
				$sep = [];
				foreach ($cells as $i => $_) {
					$sep[] = Helpers::tableAlignmentSeparator($columnAligns[$i] ?? null);
				}
				$rows[] = '| ' . implode(' | ', $sep) . ' |';
				$headerDone = true;
			}
		}

		// If no header was found, add a separator after first row
		if (!$headerDone && $rows) {
			$firstRowCells = count($node->rows[0]->cells ?? []);
			$sep = array_fill(0, $firstRowCells, '---');
			array_splice($rows, 1, 0, ['| ' . implode(' | ', $sep) . ' |']);
		}

		return implode("\n", $rows) . "\n";
	}


	private function renderTableRow(Nodes\TableRowNode $node): string
	{
		$cells = [];
		foreach ($node->cells as $cell) {
			$cells[] = $this->renderTableCell($cell);
		}
		return '| ' . implode(' | ', $cells) . ' |';
	}


	private function renderTableCell(Nodes\TableCellNode $node): string
	{
		return Helpers::escapeTableCell(
			trim($this->renderNodes($node->content->children)),
		);
	}


	// =========================================================================
	// Inline node generators
	// =========================================================================

	private function renderImage(Nodes\ImageNode $node): string
	{
		$alt = $node->modifier !== null ? ($node->modifier->title ?? '') : '';
		$url = $node->url ?? '';

		return '![' . Helpers::escapeAlt($alt) . '](' . Helpers::escapeUrl($url) . ')';
	}


	private function renderLink(Nodes\LinkNode $node): string
	{
		$text = $this->renderNodes($node->content->children);
		$url = $node->url ?? '';
		$title = $node->modifier?->title;

		// Check URL scheme (security)
		if (!$this->texy->checkURL($url, Texy::FILTER_ANCHOR)) {
			return $text; // Just return text without link
		}

		// Normalize www. URLs
		if (strncasecmp($url, 'www.', 4) === 0) {
			$url = 'http://' . $url;
		}

		if ($this->linkStyle === 'reference') {
			$ref = $this->addLinkReference($url, $title);
			return '[' . $text . '][' . $ref . ']';
		}

		// Inline style
		$titlePart = $title ? ' "' . Helpers::escapeTitle($title) . '"' : '';
		return '[' . $text . '](' . Helpers::escapeUrl($url) . $titlePart . ')';
	}


	/**
	 * Add link reference and return reference key.
	 */
	private function addLinkReference(string $url, ?string $title): string
	{
		// Check if URL already exists
		foreach ($this->linkReferences as $ref => $data) {
			if ($data['url'] === $url && $data['title'] === $title) {
				return (string) $ref;
			}
		}

		$ref = (string) ++$this->referenceCounter;
		$this->linkReferences[$ref] = ['url' => $url, 'title' => $title];
		return $ref;
	}


	private function renderPhrase(Nodes\PhraseNode $node): string
	{
		$content = $this->renderNodes($node->content->children);

		return match ($node->type) {
			Syntax::Strong => $this->strongMarker . $content . $this->strongMarker,
			Syntax::Emphasis,
			Syntax::EmphasisSingleAsterisk,
			Syntax::EmphasisSingleAsterisk2 => $this->emphasisMarker . $content . $this->emphasisMarker,
			Syntax::StrongEmphasis => '***' . $content . '***',
			Syntax::Code => '`' . $content . '`',
			Syntax::Deleted => '~~' . $content . '~~',
			Syntax::Inserted => '<ins>' . $content . '</ins>',
			Syntax::Superscript,
			Syntax::SuperscriptShort => '<sup>' . $content . '</sup>',
			Syntax::Subscript,
			Syntax::SubscriptShort => '<sub>' . $content . '</sub>',
			Syntax::SpanQuotes,
			Syntax::SpanTilde => $content,
			Syntax::Quote => '"' . $content . '"',
			default => $content,
		};
	}


	// =========================================================================
	// Autolink node generators
	// =========================================================================

	private function renderUrl(Nodes\UrlNode $node): string
	{
		$url = $node->url;

		// Autolink URLs that start with protocol
		if (preg_match('~^https?://~i', $url)) {
			return '<' . $url . '>';
		}

		// www. URLs need full link syntax
		if (strncasecmp($url, 'www.', 4) === 0) {
			$displayUrl = $this->shortenUrls ? TexyHelpers::shortenUrl($url) : $url;
			return '[' . $displayUrl . '](http://' . $url . ')';
		}

		return $url;
	}


	private function renderEmail(Nodes\EmailNode $node): string
	{
		return '<' . $node->email . '>';
	}


	// =========================================================================
	// HTML passthrough node generators
	// =========================================================================

	private function renderHtmlTag(Nodes\HtmlTagNode $node): string
	{
		$tag = '<';
		if ($node->closing) {
			$tag .= '/';
		}
		$tag .= $node->name;

		foreach ($node->attributes as $name => $value) {
			if ($value === true) {
				$tag .= ' ' . $name;
			} elseif (is_string($value)) {
				$tag .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
			}
		}

		if ($node->selfClosing) {
			$tag .= ' /';
		}
		$tag .= '>';

		return $tag;
	}
}
