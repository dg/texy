<?php

declare(strict_types=1);

use Texy\Node;
use Texy\Nodes;


/**
 * Utility for dumping AST structure in readable format for tests.
 * Format: NodeName [start,end] extra_info
 */
final class AstDumper
{
	public static function dump(Node $node, int $indent = 0): string
	{
		$class = (new ReflectionClass($node))->getShortName();
		$pos = $node->position
			? "[{$node->position->offset},{$node->position->length}]"
			: '';

		$line = str_repeat('  ', $indent) . $class;
		if ($pos !== '') {
			$line .= ' ' . $pos;
		}

		// Add type-specific extra info
		$extra = self::getExtraInfo($node);
		if ($extra !== '') {
			$line .= ' ' . $extra;
		}

		$result = $line . "\n";

		// Recursively dump children
		foreach ($node->getNodes() as $child) {
			$result .= self::dump($child, $indent + 1);
		}

		return $result;
	}


	private static function getExtraInfo(Node $node): string
	{
		return match (true) {
			$node instanceof Nodes\TextNode => '"' . self::escape($node->content) . '"',
			$node instanceof Nodes\RawTextNode => '"' . self::escape($node->content) . '"',
			$node instanceof Nodes\PhraseNode => $node->type,
			$node instanceof Nodes\HeadingNode => 'level=' . $node->level,
			$node instanceof Nodes\ListNode => $node->ordered ? 'ordered' : 'unordered',
			$node instanceof Nodes\CodeBlockNode => $node->language ? 'lang=' . $node->language : '',
			$node instanceof Nodes\LinkNode => $node->url ? 'url=' . $node->url : '',
			$node instanceof Nodes\ImageNode => $node->image->URL ? 'src=' . $node->image->URL : '',
			$node instanceof Nodes\HtmlTagNode => '<' . $node->name . '>',
			$node instanceof Nodes\CommentNode => '# ' . self::escape(substr($node->content, 0, 20)),
			$node instanceof Nodes\DirectiveNode => $node->name,
			default => '',
		};
	}


	private static function escape(string $s): string
	{
		return addcslashes($s, "\n\r\t\"\\");
	}
}
