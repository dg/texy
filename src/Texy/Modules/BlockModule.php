<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\{BlockNode, CodeBlockNode, CommentNode, ContentNode, SectionNode};
use Texy\Parsing\{OffsetMap, ParseContext};
use Texy\{Range, Syntax};
use function strlen;


/**
 * Processes special blocks (/-- code, html, text, div, etc.).
 */
final class BlockModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed[Syntax::BlockDefault] = true;
		$texy->allowed[Syntax::BlockPre] = true;
		$texy->allowed[Syntax::BlockCode] = true;
		$texy->allowed[Syntax::BlockHtml] = true;
		$texy->allowed[Syntax::BlockText] = true;
		$texy->allowed[Syntax::BlockTexySource] = true;
		$texy->allowed[Syntax::BlockComment] = true;
		$texy->allowed[Syntax::BlockDiv] = true;
	}


	public function beforeParse(): void
	{
		$this->texy->registerBlockPattern(
			$this->parse(...),
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
			Syntax::Blocks,
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

		if ($blocktype === Syntax::BlockCode) {
			return new CodeBlockNode('code', $content, $param, $mod, $range, codeMap: $map);

		} elseif ($blocktype === Syntax::BlockDefault) {
			return new CodeBlockNode('default', $content, $param, $mod, $range, codeMap: $map);

		} elseif ($blocktype === Syntax::BlockPre) {
			return new CodeBlockNode('pre', $content, $param, $mod, $range, $this->parseHtml($context, $content, $map), $map);

		} elseif ($blocktype === Syntax::BlockComment) {
			return new CommentNode($content, $range);

		} elseif ($blocktype === Syntax::BlockHtml || $blocktype === Syntax::BlockText) {
			// html/text blocks don't use outdent - preserve original indentation
			$content = trim($mContent, "\n");
			$map = OffsetMap::fromLines(OffsetMap::linesOf($content, $offsets[3] + strspn($mContent, "\n")));
			return $blocktype === Syntax::BlockHtml
				? new CodeBlockNode('html', $content, null, $mod, $range, $this->parseHtml($context, $content, $map), $map)
				: new CodeBlockNode('text', $content, null, $mod, $range, codeMap: $map);

		} elseif ($blocktype === Syntax::BlockDiv) {
			[$content, $map] = OffsetMap::outdentLines(OffsetMap::linesOf($mContent, $offsets[3]), firstLine: true);
			if ($content === '') {
				return null;
			}
			$parsed = $context->parseBlock($content);
			$map->applyTo($parsed);
			return new SectionNode($parsed, 'div', $mod, $range);

		} elseif ($blocktype === Syntax::BlockTexySource) {
			// parsed as Texy, its HTML is displayed as source code
			if ($content === '') {
				return null;
			}
			$parsed = $context->parseBlock($content);
			$map->applyTo($parsed);
			return new CodeBlockNode('texysource', $content, null, $mod, $range, $parsed, $map);
		}

		return null;
	}


	/**
	 * Parses the markup of html and pre blocks, which knows HTML tags, comments and entities only.
	 */
	private function parseHtml(ParseContext $context, string $text, OffsetMap $map): ContentNode
	{
		$parsed = $context->getInlineParser()
			->withPatterns([Syntax::HtmlTag, Syntax::HtmlComment, Syntax::HtmlEntity])
			->parse($context, $text);
		$map->applyTo($parsed);
		return $parsed;
	}
}
