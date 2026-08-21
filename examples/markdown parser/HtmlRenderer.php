<?php declare(strict_types=1);

/**
 * Renders the AST as HTML byte-identically to the CommonMark reference
 * implementation, so the output can be compared against the spec suite.
 */

namespace Texy\Examples\Markdown;

use Texy\{Node, Nodes};


final class HtmlRenderer
{
	/** characters the reference implementation percent-encodes nothing of inside a URL */
	private const HrefSafe = "-_.+!*'(),%#@?=;:/,&$~";


	public function render(Nodes\DocumentNode $document): string
	{
		return $this->renderBlocks($document->content->children);
	}


	/** @param  array<Node>  $nodes */
	private function renderBlocks(array $nodes, bool $tight = false): string
	{
		$res = '';
		foreach ($nodes as $node) {
			$res .= $this->renderBlock($node, $tight);
		}
		return $res;
	}


	private function renderBlock(Node $node, bool $tight = false): string
	{
		switch (true) {
			case $node instanceof Nodes\ParagraphNode:
				$inner = $this->renderInlines($node->content->children);
				return $tight ? $inner . "\n" : "<p>$inner</p>\n";

			case $node instanceof Nodes\HeadingNode:
				$level = $node->level;
				return "<h$level>" . $this->renderInlines($node->content->children) . "</h$level>\n";

			case $node instanceof Nodes\HorizontalRuleNode:
				return "<hr />\n";

			case $node instanceof Nodes\CodeBlockNode:
				$class = $node->language === null ? '' : ' class="language-' . self::escape($node->language) . '"';
				return "<pre><code$class>" . self::escape($node->code) . "</code></pre>\n";

			case $node instanceof HtmlBlockNode:
				return $node->html . "\n";

			case $node instanceof Nodes\BlockQuoteNode:
				return "<blockquote>\n" . $this->renderBlocks($node->content->children) . "</blockquote>\n";

			case $node instanceof ListNode:
				return $this->renderList($node);

			case $node instanceof Nodes\CommentNode:
				return '';

			case $node instanceof Nodes\ContentNode:
				return $this->renderBlocks($node->children, $tight);

			default:
				return '';
		}
	}


	private function renderList(ListNode $node): string
	{
		$tag = $node->type->isOrdered() ? 'ol' : 'ul';
		$start = $node->start === null || $node->start === 1 ? '' : ' start="' . $node->start . '"';
		$res = "<$tag$start>\n";

		foreach ($node->items as $item) {
			$children = $item->content->children;
			$inner = $this->renderBlocks($children, $node->tight);
			if ($inner === '') {
				$res .= "<li></li>\n";
				continue;
			}

			// a tight item opens/closes on the content line only when the edge child is a paragraph
			$flatStart = $node->tight && $children[0] instanceof Nodes\ParagraphNode;
			$flatEnd = $node->tight && end($children) instanceof Nodes\ParagraphNode;
			$res .= '<li>' . ($flatStart ? '' : "\n")
				. ($flatEnd ? rtrim($inner, "\n") : rtrim($inner, "\n") . "\n")
				. "</li>\n";
		}

		return $res . "</$tag>\n";
	}


	/** @param  array<Node>  $nodes */
	private function renderInlines(array $nodes): string
	{
		$res = '';
		foreach ($nodes as $node) {
			$res .= $this->renderInline($node);
		}
		return $res;
	}


	private function renderInline(Node $node): string
	{
		switch (true) {
			case $node instanceof Nodes\TextNode:
			case $node instanceof Nodes\RawTextNode:
				return self::escape($node->text);

			case $node instanceof RawInlineNode:
				return $node->html;

			case $node instanceof SoftBreakNode:
				return "\n";

			case $node instanceof Nodes\LineBreakNode:
				return "<br />\n";

			case $node instanceof Nodes\PhraseNode:
				$inner = $this->renderInlines($node->content->children);
				return match ($node->type) {
					'em' => "<em>$inner</em>",
					'strong' => "<strong>$inner</strong>",
					'code' => "<code>$inner</code>",
					default => $inner,
				};

			case $node instanceof LinkNode:
				return '<a href="' . self::escapeHref($node->url) . '"' . self::title($node->title) . '>'
					. $this->renderInlines($node->content->children) . '</a>';

			case $node instanceof ImageNode:
				return '<img src="' . self::escapeHref($node->url) . '" alt="' . self::escape(self::plainText($node->alt)) . '"'
					. self::title($node->title) . ' />';

			case $node instanceof Nodes\ContentNode:
				return $this->renderInlines($node->children);

			default:
				return '';
		}
	}


	private static function title(?string $title): string
	{
		return $title === null ? '' : ' title="' . self::escape($title) . '"';
	}


	/** An image's alt text is plain text, so nested markup contributes only its content. */
	private static function plainText(Nodes\ContentNode $content): string
	{
		$res = '';
		foreach ($content->children as $node) {
			$res .= match (true) {
				$node instanceof Nodes\TextNode, $node instanceof Nodes\RawTextNode => $node->text,
				$node instanceof SoftBreakNode => "\n",
				$node instanceof Nodes\PhraseNode => self::plainText($node->content),
				$node instanceof LinkNode => self::plainText($node->content),
				$node instanceof ImageNode => self::plainText($node->alt),
				default => '',
			};
		}
		return $res;
	}


	private static function escape(string $s): string
	{
		return str_replace(['&', '<', '>', '"'], ['&amp;', '&lt;', '&gt;', '&quot;'], $s);
	}


	private static function escapeHref(string $s): string
	{
		$res = '';
		foreach (str_split($s) as $char) {
			$res .= ctype_alnum($char) || str_contains(self::HrefSafe, $char)
				? $char
				: sprintf('%%%02X', ord($char));
		}
		return str_replace(['&', '\'', '<', '>', '"'], ['&amp;', '&#x27;', '&lt;', '&gt;', '&quot;'], $res);
	}
}
