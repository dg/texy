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
 * Processes special blocks (/-- code, html, text, div, etc.).
 */
final class BlockModule extends Texy\Module
{
	public function register(Texy\Parsing\Registry $registry): void
	{
		foreach ([
			Syntax::BlockDefault,
			Syntax::BlockPre,
			Syntax::BlockCode,
			Syntax::BlockHtml,
			Syntax::BlockText,
			Syntax::BlockTexySource,
			Syntax::BlockComment,
			Syntax::BlockDiv,
		] as $name) {
			$registry->declare($name);
		}

		$registry->addBlock(
			Syntax::Blocks,
			'~^
				/--++ \ *+                    # opening tag /--
				(.*)                          # content type (1)
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
		[, $mParam, $mMod, $mContent] = $matches;

		$mod = Texy\Modifier::parse($mMod, $offsets[2] ?? null);
		$parts = Texy\Regexp::split($mParam, '~\s+~', limit: 2);
		$blocktype = empty($parts[0]) ? Syntax::BlockDefault : 'block/' . $parts[0];
		$param = empty($parts[1]) ? null : $parts[1];

		[$content, $map] = OffsetMap::outdentLines(OffsetMap::linesOf($mContent, $offsets[3]));
		$range = new Range($offsets[0], strlen($matches[0]));

		// a disallowed type stays as source text
		if (empty($context->allowedSyntaxes[$blocktype])) {
			return null;
		}

		if ($blocktype === Syntax::BlockCode) {
			return new CodeBlockNode($content, $param, $mod, $range, $map);

		} elseif ($blocktype === Syntax::BlockPre) {
			return new PreformattedNode($content, $this->parseVerbatim($context, $content, $map), $mod, $range, $map, Syntax::BlockPre);

		} elseif ($blocktype === Syntax::BlockDefault) {
			$text = $this->parseVerbatim($context, $content, $map, []);
			return new PreformattedNode($content, $text, $mod, $range, $map, Syntax::BlockDefault);

		} elseif ($blocktype === Syntax::BlockComment) {
			return new CommentNode($content, $range);

		} elseif ($blocktype === Syntax::BlockHtml || $blocktype === Syntax::BlockText) {
			// html/text blocks don't use outdent - preserve original indentation
			$content = trim($mContent, "\n");
			$map = OffsetMap::fromLines(OffsetMap::linesOf($content, $offsets[3] + strspn($mContent, "\n")));
			return $blocktype === Syntax::BlockHtml
				? new HtmlBlockNode($content, $this->parseVerbatim($context, $content, $map), $mod, $range, $map)
				: new TextBlockNode($content, $mod, $range, $map);

		} elseif ($blocktype === Syntax::BlockDiv) {
			[$content, $map] = OffsetMap::outdentLines(OffsetMap::linesOf($mContent, $offsets[3]), firstLine: true);
			if ($content === '') {
				return null;
			}
			$parsed = $context->parseBlock($content);
			$map->applyTo($parsed);
			return new DivNode($parsed, $mod, $range);

		} elseif ($blocktype === Syntax::BlockTexySource) {
			// parsed as Texy, its HTML is displayed as source code
			if ($content === '') {
				return null;
			}
			$parsed = $context->parseBlock($content);
			$map->applyTo($parsed);
			return new TexySourceNode($content, $parsed, $mod, $range, $map);
		}

		return null;
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
