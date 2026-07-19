<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\Nodes\BlockNode;
use Texy\Nodes\CodeBlockNode;
use Texy\Nodes\CommentNode;
use Texy\Nodes\SectionNode;
use Texy\ParseContext;
use Texy\Range;
use Texy\Syntax;
use function strlen, trim;


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
		$texy->allowed[Syntax::CodeFenced] = true;
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerBlockPattern(
			$this->parse(...),
			'~^
				/--++ \ *+                    # opening tag /--
				(.*)                          # content type (1)
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
			Syntax::Blocks,
		);

		// ```language
		$this->texy->registerBlockPattern(
			$this->parseFenced(...),
			'~^
				(`{3,}) \ *+                        # opening fence (1)
				([^\n`]*)                           # info string (2)
				$
				((?: \n (?! \1 `*+ \ *+ $ ) .*+ )*) # content (3): lines that are not a valid closing fence
				(?:
					\n \1 `*+ \ *+ $ |              # closing fence (same length or longer)
					\z                              # or end of input
				)
			~mx',
			Syntax::CodeFenced,
		);
	}


	/**
	 * Parses ```language fenced code blocks; content is verbatim.
	 * @param  array{string, string, string, string}  $matches
	 * @param  array{int, int, int, int}  $offsets
	 */
	public function parseFenced(ParseContext $context, array $matches, array $offsets): CodeBlockNode
	{
		[, , $mInfo, $mContent] = $matches;

		// first word of the info string is the language (GFM)
		$language = Texy\Regexp::split(trim($mInfo), '~\s+~', limit: 2)[0];

		return new CodeBlockNode(
			'code',
			substr($mContent, 1), // drop the separator "\n"; intentional blank lines stay
			$language === '' ? null : $language,
			null,
			new Range($offsets[0], strlen($matches[0])),
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

		$content = Helpers::outdent($mContent);
		$range = new Range($offsets[0], strlen($matches[0]));

		if ($blocktype === Syntax::BlockCode) {
			return new CodeBlockNode('code', $content, $param, $mod, $range);

		} elseif ($blocktype === Syntax::BlockDefault || $blocktype === Syntax::BlockPre) {
			return new CodeBlockNode($blocktype === Syntax::BlockPre ? 'pre' : 'default', $content, $param, $mod, $range);

		} elseif ($blocktype === Syntax::BlockComment) {
			return new CommentNode($content, $range);

		} elseif ($blocktype === Syntax::BlockHtml) {
			// html/text blocks don't use outdent - preserve original indentation
			return new CodeBlockNode('html', trim($mContent, "\n"), null, $mod, $range);

		} elseif ($blocktype === Syntax::BlockText) {
			// html/text blocks don't use outdent - preserve original indentation
			return new CodeBlockNode('text', trim($mContent, "\n"), null, $mod, $range);

		} elseif ($blocktype === Syntax::BlockDiv) {
			[$content, $map] = Texy\OffsetMap::outdentLines(
				Texy\OffsetMap::linesOf($mContent, $offsets[3]),
				firstLine: true,
			);
			if ($content === '') {
				return null;
			}
			$parsed = $context->parseBlock($content);
			$map->applyTo($parsed);
			return new SectionNode($parsed, 'div', $mod, $range);

		} elseif ($blocktype === Syntax::BlockTexySource) {
			// Store raw texy content, will be parsed and displayed as HTML source in handler
			$content = Helpers::outdent($mContent);
			if ($content === '') {
				return null;
			}
			return new CodeBlockNode('texysource', $content, null, $mod, $range);
		}

		return null;
	}
}
