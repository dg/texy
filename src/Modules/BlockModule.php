<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Helpers, Syntax};
use Texy\Nodes\{
	BlockNode, CodeBlockNode, CommentNode, ContentNode, DivNode, HtmlBlockNode, PreformattedNode, TextBlockNode, TexySourceNode,
};
use Texy\Parsing\ParseContext;


/**
 * Processes special blocks (/-- code, html, text, div, etc.).
 */
final class BlockModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
	}


	public function beforeParse(): void
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
			$this->texy->declare($name);
		}

		$this->texy->addBlock(
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
	 */
	public function parse(ParseContext $context, array $matches): ?BlockNode
	{
		[, $mParam, $mMod, $mContent] = $matches;

		$mod = Texy\Modifier::parse($mMod);
		$parts = Texy\Regexp::split($mParam, '~\s+~', limit: 2);
		$blocktype = empty($parts[0]) ? Syntax::BlockDefault : 'block/' . $parts[0];
		$param = empty($parts[1]) ? null : $parts[1];

		$content = Helpers::outdent($mContent);

		// a disallowed type stays as source text
		if (empty($this->texy->allowed[$blocktype])) {
			return null;
		}

		if ($blocktype === Syntax::BlockCode) {
			return new CodeBlockNode($content, $param, $mod);

		} elseif ($blocktype === Syntax::BlockPre) {
			return new PreformattedNode($content, $this->parseVerbatim($context, $content), $mod, Syntax::BlockPre);

		} elseif ($blocktype === Syntax::BlockDefault) {
			return new PreformattedNode($content, $this->parseVerbatim($context, $content, []), $mod, Syntax::BlockDefault);

		} elseif ($blocktype === Syntax::BlockComment) {
			return new CommentNode($content);

		} elseif ($blocktype === Syntax::BlockHtml || $blocktype === Syntax::BlockText) {
			// html/text blocks don't use outdent - preserve original indentation
			$content = trim($mContent, "\n");
			return $blocktype === Syntax::BlockHtml
				? new HtmlBlockNode($content, $this->parseVerbatim($context, $content), $mod)
				: new TextBlockNode($content, $mod);

		} elseif ($blocktype === Syntax::BlockDiv) {
			$content = Helpers::outdent($mContent, firstLine: true);
			if ($content === '') {
				return null;
			}
			return new DivNode($context->parseBlock($content), $mod);

		} elseif ($blocktype === Syntax::BlockTexySource) {
			// parsed as Texy, its HTML is displayed as source code
			if ($content === '') {
				return null;
			}
			return new TexySourceNode($content, $context->parseBlock($content), $mod);
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
		array $syntaxes = [Syntax::HtmlTag, Syntax::HtmlComment, Syntax::HtmlEntity],
	): ContentNode
	{
		return $context->getInlineParser()
			->withPatterns($syntaxes)
			->parse($context, $text);
	}
}
