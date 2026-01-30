<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\Nodes\BlockNode;
use Texy\Nodes\CodeBlockNode;
use Texy\Nodes\CommentNode;
use Texy\Nodes\SectionNode;
use Texy\ParseContext;
use Texy\Syntax;


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


	public function beforeParse(string &$text): void
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
			~mUi',
			Syntax::Blocks,
		);
	}


	/**
	 * Parses blocks /--foo
	 * @param  array<?string>  $matches
	 */
	public function parse(ParseContext $context, array $matches): ?BlockNode
	{
		[, $mParam, $mMod, $mContent] = $matches;

		$mod = Texy\Modifier::parse($mMod);
		$parts = Texy\Regexp::split($mParam, '~\s+~', limit: 2);
		$blocktype = empty($parts[0]) ? Syntax::BlockDefault : 'block/' . $parts[0];
		$param = empty($parts[1]) ? null : $parts[1];

		$content = Helpers::outdent($mContent);

		if ($blocktype === Syntax::BlockCode) {
			return new CodeBlockNode('code', $content, $param, $mod);

		} elseif ($blocktype === Syntax::BlockDefault || $blocktype === Syntax::BlockPre) {
			return new CodeBlockNode($blocktype === Syntax::BlockPre ? 'pre' : 'default', $content, $param, $mod);

		} elseif ($blocktype === Syntax::BlockComment) {
			return new CommentNode($content);

		} elseif ($blocktype === Syntax::BlockHtml) {
			// html/text blocks don't use outdent - preserve original indentation
			return new CodeBlockNode('html', trim($mContent, "\n"), null, $mod);

		} elseif ($blocktype === Syntax::BlockText) {
			// html/text blocks don't use outdent - preserve original indentation
			return new CodeBlockNode('text', trim($mContent, "\n"), null, $mod);

		} elseif ($blocktype === Syntax::BlockDiv) {
			$content = Helpers::outdent($mContent, firstLine: true);
			if ($content === '') {
				return null;
			}
			return new SectionNode($context->parseBlock($content), 'div', $mod);

		} elseif ($blocktype === Syntax::BlockTexySource) {
			// Store raw texy content, will be parsed and displayed as HTML source in handler
			$content = Helpers::outdent($mContent);
			if ($content === '') {
				return null;
			}
			return new CodeBlockNode('texysource', $content, null, $mod);
		}

		return null;
	}
}
