<?php declare(strict_types=1);

use Texy\{Node, Nodes};


/**
 * Utility for dumping AST structure in readable format for tests.
 * Format: NodeName [start,end] extra_info
 */
final class AstDumper
{
	public static function dump(Node $node, int $indent = 0): string
	{
		$class = (new ReflectionClass($node))->getShortName();
		$pos = $node->range
			? "[{$node->range->offset},{$node->range->length}]"
			: '';

		$line = str_repeat('  ', $indent) . $class;
		if ($pos !== '') {
			$line .= ' ' . $pos;
		}

		$modifier = get_object_vars($node)['modifier'] ?? null;
		if ($modifier instanceof Texy\Modifier && $modifier->range) {
			$line .= " mod[{$modifier->range->offset},{$modifier->range->length}]";
		}

		// parts of the notation
		foreach (get_object_vars($node) as $name => $value) {
			if ($value instanceof Texy\Range && $name !== 'range') {
				$line .= " $name" . "[{$value->offset},{$value->length}]";
			} elseif ($value instanceof Texy\Parsing\OffsetMap) {
				$line .= " $name@" . $value->toAbsolute(0);
			}
		}

		// Add type-specific extra info
		$extra = self::getExtraInfo($node);
		if ($extra !== '') {
			$line .= ' ' . $extra;
		}

		$result = $line . "\n";

		// Recursively dump children
		foreach ($node->getChildren() as $child) {
			$result .= self::dump($child, $indent + 1);
		}

		return $result;
	}


	private static function getExtraInfo(Node $node): string
	{
		return match (true) {
			$node instanceof Nodes\TextNode => '"' . self::escape($node->text) . '"',
			$node instanceof Nodes\RawTextNode => '"' . self::escape($node->text) . '"',
			$node instanceof Nodes\PhraseNode => $node->type,
			$node instanceof Nodes\HeadingNode => 'level=' . $node->level . ($node->marker === null ? '' : ' marker=' . $node->marker),
			$node instanceof Nodes\ListNode => $node->type->isOrdered() ? 'ordered' : 'unordered',
			$node instanceof Nodes\ListItemNode => $node->bullet === null ? '' : 'bullet=' . $node->bullet,
			$node instanceof Nodes\CodeBlockNode => $node->language ? 'lang=' . $node->language : '',
			$node instanceof Nodes\LinkNode => implode(' ', array_filter([
				$node->syntax,
				$node->url === null ? null : 'url=' . $node->url,
				$node->ref === null ? null : 'ref=' . $node->ref,
			])),
			$node instanceof Nodes\ImageNode => $node->url ? 'src=' . $node->url : '',
			$node instanceof Nodes\HtmlTagNode => '<' . $node->name . '>',
			$node instanceof Nodes\CommentNode => '# ' . self::escape(substr($node->text, 0, 20)),
			$node instanceof Nodes\DirectiveNode => $node->parseContent()['name'],
			default => '',
		};
	}


	private static function escape(string $s): string
	{
		return addcslashes($s, "\n\r\t\"\\");
	}
}
