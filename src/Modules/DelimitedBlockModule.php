<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\{
	BlockNode, CodeBlockNode, CommentNode, ContentNode, DivNode, HtmlBlockNode, PreformattedNode, TextBlockNode, TexySourceNode,
};
use Texy\Parsing\{OffsetMap, ParseContext};
use Texy\{Range, Syntax};
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


	public function register(Texy\Texy $texy): void
	{
		foreach (self::Types as $name) {
			$texy->declare($name, enabled: $name !== Syntax::DelimitedTexySource);
		}

		$texy->addBlock(
			Syntax::Delimited,
			'~^
				/--++ \ *+                    # opening tag /--
				(.*)                         # content type (1)
				' . Texy\Patterns::MODIFIER_H . '? # modifier (2)
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
		$type = $parts[0];
		if (isset(self::Types[$type]) && empty($context->allowedSyntaxes[self::Types[$type]])) {
			return null;
		}

		$param = empty($parts[1]) ? null : $parts[1];
		$mod = Texy\Modifier::parse($matches[2], $offsets[2] ?? null);
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
				return null; // an unknown type stays as source text
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
