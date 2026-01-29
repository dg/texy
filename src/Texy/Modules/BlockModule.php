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
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Syntax;
use function htmlspecialchars, strlen, trim;
use const ENT_NOQUOTES;


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
		$texy->htmlGenerator->registerHandler($this->solveCodeBlock(...));
		$texy->htmlGenerator->registerHandler($this->solveSection(...));
		$texy->htmlGenerator->registerHandler(fn(CommentNode $node) => '');
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


	public function solveCodeBlock(CodeBlockNode $node, Html\Generator $generator): Html\Element|string
	{
		// block/texysource - parse as texy, then display resulting HTML as source code
		if ($node->type === 'texysource') {
			$context = $this->texy->createParseContext();
			$parsed = $context->parseBlock($node->content);
			$content = $generator->serialize($generator->renderNodes($parsed->children), "\n");
			$html = Helpers::unescapeHtml($content);
			$html = htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8');
			$html = $this->texy->unprotect($html);
			$html = $this->texy->htmlOutputModule->format($html);
			$html = Helpers::unfreezeSpaces($html);
			$html = trim($html);
			// Now escape the final HTML to show as source code
			$escaped = htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8');
			$escaped = $this->texy->protect($escaped, $this->texy::CONTENT_TEXTUAL);

			$el = new Html\Element('pre');
			$node->modifier?->decorate($this->texy, $el);
			$el->attrs['class'] = array_merge(['html'], (array) ($el->attrs['class'] ?? []));
			$el->create('code', $escaped);
			return $el;
		}

		// block/html - parse HTML tags/comments, escape unknown ones
		if ($node->type === 'html') {
			$content = $node->content;
			if ($content === '') {
				return "\n";
			}

			$parsed = $this->parseHtmlOnly($generator, $content);
			return $this->texy->protect($parsed . ' ', $this->texy::CONTENT_BLOCK);
		}

		// block/text - plain text with <br> for newlines (no wrapper)
		// Include trailing space inside protection for proper separation from next block
		if ($node->type === 'text') {
			$content = htmlspecialchars($node->content, ENT_NOQUOTES, 'UTF-8');
			$content = str_replace("\n", '<br>', $content);
			return $this->texy->protect($content . ' ', $this->texy::CONTENT_BLOCK);
		}

		// Types that use <pre> wrapper
		$el = new Html\Element('pre');
		$node->modifier?->decorate($this->texy, $el);

		// Language class prepended before modifier classes
		if ($node->language) {
			$el->attrs['class'] = array_merge([$node->language], (array) ($el->attrs['class'] ?? []));
		}

		// PRE block - parse HTML tags, unescape entities
		if ($node->type === 'pre') {
			$parsed = $this->parseHtmlOnly($generator, $node->content);
			$content = $this->texy->protect($parsed, $this->texy::CONTENT_BLOCK);
			$el->setText($content);
			return $el;
		}

		$content = htmlspecialchars($node->content, ENT_NOQUOTES, 'UTF-8');
		$content = $this->texy->protect($content, $this->texy::CONTENT_TEXTUAL);

		if ($node->type === 'code') {
			$el->create('code', $content);
		} else {
			$el->setText($content);
		}

		return $el;
	}


	public function solveSection(SectionNode $node, Html\Generator $generator): Html\Element
	{
		$el = new Html\Element($node->type === 'div' ? 'div' : 'section');
		$node->modifier?->decorate($this->texy, $el);
		$el->children = $generator->renderNodes($node->content->children);
		return $el;
	}


	/**
	 * Parse content with only HTML tag/comment patterns.
	 */
	private function parseHtmlOnly(Html\Generator $generator, string $content): string
	{
		$context = $this->texy->createParseContext();
		$htmlParser = $context->getInlineParser()->withPatterns([Syntax::HtmlTag, Syntax::HtmlComment]);
		$parsed = $generator->serialize($generator->renderNodes($htmlParser->parse(null, $content)->children));
		$parsed = Helpers::unescapeHtml($parsed);
		$parsed = htmlspecialchars($parsed, ENT_NOQUOTES, 'UTF-8');
		return $this->texy->unprotect($parsed);
	}
}
