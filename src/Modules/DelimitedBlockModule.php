<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Helpers, Range, Syntax};
use Texy\Nodes\{
	BlockNode, CodeBlockNode, CommentNode, ContentNode, DivNode, HtmlBlockNode, PreformattedNode, TextBlockNode, TexySourceNode, UnknownDelimitedNode,
};
use Texy\Parsing\{OffsetMap, ParseContext};
use function strlen;


/**
 * Processes delimited blocks (/-- code, html, text, div, etc.).
 */
final class DelimitedBlockModule extends Texy\Module
{
	/** built-in type => its syntax name */
	private const Types = [
		'' => Syntax::DelimitedBare,
		'pre' => Syntax::DelimitedPre,
		'code' => Syntax::DelimitedCode,
		'html' => Syntax::DelimitedHtml,
		'text' => Syntax::DelimitedText,
		'texysource' => Syntax::DelimitedTexySource,
		'comment' => Syntax::DelimitedComment,
		'div' => Syntax::DelimitedDiv,
	];


	public function __construct(
		private Texy\Config $config,
	) {
	}


	public function register(Texy\Parsing\Engine $engine): void
	{
		foreach (self::Types as $name) {
			$engine->declare($name, enabled: $name !== Syntax::DelimitedTexySource);
		}

		$engine->addBlock(
			Syntax::Delimited,
			'~^
				/--++ [ \t]*+                # opening tag /--
				(.*)                         # content type (1)
				' . Texy\Patterns::ModifierHAlign . '? # modifier (2)
				$
				((?:                         # content (3)
					\n (?0) |                # recursive nested blocks
					\n.*+                    # or any content
				)*)
				(?:
					\n \\\--.* $ |           # closing tag
					\z                       # or end of input
				)
			~mUix',
			$this->parse(...),
		);
	}


	/**
	 * Parses blocks /--foo
	 * @param  array{string, string, ?string, string}  $matches
	 * @param  array{int, int, ?int, int}  $offsets
	 */
	public function parse(ParseContext $context, array $matches, array $offsets): ?BlockNode
	{
		$parts = Texy\Regexp::split($matches[1], '~\s+~', limit: 2);
		// the opening pattern matches case-insensitively, so /--PRE must mean the same as /--pre
		$type = Helpers::toLower($parts[0]);
		if (isset(self::Types[$type]) && empty($context->allowedSyntaxes[self::Types[$type]])) {
			return null;
		}

		$param = empty($parts[1]) ? null : $parts[1];
		$mod = Texy\Modifier::parse($matches[2], $offsets[2] ?? null);
		$built = null;
		$next = function () use ($context, $type, $param, $mod, $matches, $offsets, &$built): ?BlockNode {
			return $built = $this->createNode($context, $type, $param, $mod, $matches, $offsets);
		};

		$handler = $this->config->delimitedBlockHandler;
		if ($handler === null) {
			return $next();
		}

		[$content, $map] = OffsetMap::outdentLines(OffsetMap::linesOf($matches[3], $offsets[3]));
		$node = $handler($context, $type, $content, $param, $mod, $next);
		if ($node !== null && $node !== $built) {
			foreach ($node->getChildren() as $child) {
				$map->applyTo($child, except: $built); // the handler read the outdented content
			}
			$node->range ??= new Range($offsets[0], strlen($matches[0]));
		}
		return $node;
	}


	/**
	 * Creates the node of a built-in type, or the unknown node of any other type.
	 * @param  array{string, string, ?string, string}  $matches
	 * @param  array{int, int, ?int, int}  $offsets
	 */
	private function createNode(
		ParseContext $context,
		string $type,
		?string $param,
		?Texy\Modifier $mod,
		array $matches,
		array $offsets,
	): ?BlockNode
	{
		$mContent = $matches[3];
		[$content, $map] = OffsetMap::outdentLines(OffsetMap::linesOf($mContent, $offsets[3]));
		$range = new Range($offsets[0], strlen($matches[0]));

		switch ($type) {
			case 'code':
				return new CodeBlockNode($content, $param, $mod, $range, $map);

			case 'pre':
				return new PreformattedNode(
					$content,
					$this->parseVerbatim($context, $content, $map),
					$mod,
					$range,
					$map,
					Syntax::DelimitedPre,
				);

			case '':
				$text = $this->parseVerbatim($context, $content, $map, []);
				return new PreformattedNode($content, $text, $mod, $range, $map, Syntax::DelimitedBare);

			case 'comment':
				return new CommentNode($content, $range);

			case 'html':
			case 'text':
				// html/text blocks don't use outdent - preserve original indentation
				$content = trim($mContent, "\n");
				$map = OffsetMap::fromLines(OffsetMap::linesOf($content, $offsets[3] + strspn($mContent, "\n")));
				return $type === 'html'
					? new HtmlBlockNode($content, $this->parseVerbatim($context, $content, $map), $mod, $range, $map)
					: new TextBlockNode($content, $mod, $range, $map);

			case 'div':
				[$content, $map] = OffsetMap::outdentLines(OffsetMap::linesOf($mContent, $offsets[3]), firstLine: true);
				if ($content === '') {
					return null;
				}
				$parsed = $context->parseBlock($content);
				$map->applyTo($parsed);
				return new DivNode($parsed, $mod, $range);

			case 'texysource':
				// parsed as Texy, its HTML is displayed as source code
				if ($content === '') {
					return null;
				}
				$parsed = $context->parseBlock($content);
				$map->applyTo($parsed);
				return new TexySourceNode($content, $parsed, $mod, $range, $map);

			default:
				return new UnknownDelimitedNode(
					$matches[0],
					$type,
					$range,
					OffsetMap::fromLines(OffsetMap::linesOf($matches[0], $offsets[0])),
				);
		}
	}


	/**
	 * Parses the markup of a verbatim block, which knows the given syntaxes only: HTML tags,
	 * comments and entities in html and pre blocks, none in a bare /--.
	 * @param  list<string>  $syntaxes
	 */
	private function parseVerbatim(
		ParseContext $context,
		string $text,
		OffsetMap $map,
		array $syntaxes = [Syntax::HtmlTag, Syntax::HtmlComment, Syntax::HtmlEntity],
	): ContentNode
	{
		$parsed = $context->getInlineParser()
			->withPatterns($syntaxes)
			->parse($context, $text);
		$map->applyTo($parsed);
		return $parsed;
	}
}
