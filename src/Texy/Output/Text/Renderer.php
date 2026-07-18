<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Text;

use Texy\Node;
use Texy\Nodes;
use Texy\Output\NodeRenderer;
use Texy\Regexp;
use function array_filter, array_map, implode, strtr, trim;


/**
 * Generates a plain-text rendition from AST: markup is dropped, visible text
 * and block structure (blank lines between blocks) are kept.
 * @extends NodeRenderer<string>
 */
class Renderer extends NodeRenderer
{
	public function __construct()
	{
		$this->handlers = [
			// Core nodes
			Nodes\DocumentNode::class => fn(Nodes\DocumentNode $n, self $g) => $g->renderBlocks($n->content->children),
			Nodes\TextNode::class => fn(Nodes\TextNode $n) => $n->text,
			Nodes\ContentNode::class => fn(Nodes\ContentNode $n, self $g) => $g->renderChildren($n),

			// Block nodes
			Nodes\ParagraphNode::class => fn(Nodes\ParagraphNode $n, self $g) => $g->renderChildren($n->content),
			Nodes\HeadingNode::class => fn(Nodes\HeadingNode $n, self $g) => $g->renderChildren($n->content),
			Nodes\BlockQuoteNode::class => fn(Nodes\BlockQuoteNode $n, self $g) => $g->renderBlocks($n->content->children),
			Nodes\CodeBlockNode::class => fn(Nodes\CodeBlockNode $n) => $n->type === 'comment' ? '' : trim($n->code, "\n"),
			Nodes\SectionNode::class => fn(Nodes\SectionNode $n, self $g) => $g->renderBlocks($n->content->children),
			Nodes\HorizontalRuleNode::class => fn() => '',
			Nodes\FigureNode::class => fn(Nodes\FigureNode $n, self $g) => $n->caption ? $g->renderChildren($n->caption) : '',

			// List nodes
			Nodes\ListNode::class => fn(Nodes\ListNode $n, self $g) => $g->renderLines($n->items),
			Nodes\ListItemNode::class => fn(Nodes\ListItemNode $n, self $g) => $g->renderBlocks($n->content->children, "\n"),
			Nodes\DefinitionListNode::class => fn(Nodes\DefinitionListNode $n, self $g) => $g->renderLines($n->items),

			// Table nodes
			Nodes\TableNode::class => fn(Nodes\TableNode $n, self $g) => $g->renderLines($n->rows),
			Nodes\TableRowNode::class => fn(Nodes\TableRowNode $n, self $g) => implode("\t", array_map($g->renderNode(...), $n->cells)),
			Nodes\TableCellNode::class => fn(Nodes\TableCellNode $n, self $g) => $g->renderBlocks($n->content->children, "\n"),

			// Inline nodes
			Nodes\ImageNode::class => fn(Nodes\ImageNode $n) => $n->modifier->title ?? '',
			Nodes\LinkNode::class => fn(Nodes\LinkNode $n, self $g) => $g->renderChildren($n->content),
			Nodes\PhraseNode::class => fn(Nodes\PhraseNode $n, self $g) => $g->renderChildren($n->content),
			Nodes\RawTextNode::class => fn(Nodes\RawTextNode $n) => $n->text,
			Nodes\AnnotationNode::class => fn(Nodes\AnnotationNode $n) => $n->text,
			Nodes\EmoticonNode::class => fn(Nodes\EmoticonNode $n) => $n->resolved ?? $n->emoticon,
			Nodes\LineBreakNode::class => fn() => "\n",

			// Autolink nodes
			Nodes\UrlNode::class => fn(Nodes\UrlNode $n) => $n->url,
			Nodes\EmailNode::class => fn(Nodes\EmailNode $n) => $n->email,

			// HTML passthrough nodes - tags are dropped, content is kept
			Nodes\HtmlTagNode::class => fn() => '',
			Nodes\HtmlElementNode::class => fn(Nodes\HtmlElementNode $n, self $g) => $g->renderChildren($n->content),
			Nodes\HtmlCommentNode::class => fn() => '',

			// Directive nodes
			Nodes\DirectiveNode::class => fn(Nodes\DirectiveNode $n) => '{{' . $n->text . '}}',

			// Definition nodes (no output)
			Nodes\ImageDefinitionNode::class => fn() => '',
			Nodes\LinkDefinitionNode::class => fn() => '',
			Nodes\CommentNode::class => fn() => '',
		];
	}


	/**
	 * Render document AST to plain text.
	 */
	public function render(Nodes\DocumentNode $document): string
	{
		$text = $this->renderNode($document);

		$text = strtr($text, [
			"\u{AD}" => '',  // soft hyphens from the hyphenation pass
			"\u{A0}" => ' ', // non-breaking spaces from the typography pass
		]);

		// collapse excess blank lines left by empty blocks
		$text = Regexp::replace($text, '~\n{3,}~', "\n\n");
		return trim($text, "\n") . "\n";
	}


	private function renderChildren(Nodes\ContentNode $content): string
	{
		$s = '';
		foreach ($content->children as $child) {
			$s .= $this->renderNode($child);
		}

		return $s;
	}


	/**
	 * Blocks separated by blank lines (or the given separator), empty ones dropped.
	 * @param  array<Node>  $blocks
	 */
	private function renderBlocks(array $blocks, string $separator = "\n\n"): string
	{
		$parts = array_filter(
			array_map($this->renderNode(...), $blocks),
			fn(string $s) => trim($s) !== '',
		);
		return implode($separator, $parts);
	}


	/**
	 * Items one per line.
	 * @param  array<Node>  $items
	 */
	private function renderLines(array $items): string
	{
		return implode("\n", array_map($this->renderNode(...), $items));
	}
}
