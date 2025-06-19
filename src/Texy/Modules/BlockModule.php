<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Helpers, Syntax};
use Texy\Nodes\{BlockNode, CodeBlockNode, CommentNode, ContentNode, SectionNode};
use Texy\Output\Html;
use Texy\Parsing\ParseContext;


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
		$texy->htmlOutput->registerHandler($this->solveCodeBlock(...));
		$texy->htmlOutput->registerHandler($this->solveSection(...));
		$texy->htmlOutput->registerHandler(fn(CommentNode $node) => '');
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
	 * @param  array<?string>  $matches
	 */
	public function parse(ParseContext $context, array $matches): ?BlockNode
	{
		/** @var array{string, string, ?string, string} $matches */
		[, $mParam, $mMod, $mContent] = $matches;

		$mod = Texy\Modifier::parse($mMod);
		$parts = Texy\Regexp::split($mParam, '~\s+~', limit: 2);
		$blocktype = empty($parts[0]) ? Syntax::BlockDefault : 'block/' . $parts[0];
		$param = empty($parts[1]) ? null : $parts[1];

		$content = Helpers::outdent($mContent);

		if ($blocktype === Syntax::BlockCode) {
			return new CodeBlockNode('code', $content, $param, $mod);

		} elseif ($blocktype === Syntax::BlockDefault) {
			return new CodeBlockNode('default', $content, $param, $mod);

		} elseif ($blocktype === Syntax::BlockPre) {
			return new CodeBlockNode('pre', $content, $param, $mod, $this->parseHtml($context, $content));

		} elseif ($blocktype === Syntax::BlockComment) {
			return new CommentNode($content);

		} elseif ($blocktype === Syntax::BlockHtml) {
			// html/text blocks don't use outdent - preserve original indentation
			$content = trim($mContent, "\n");
			return new CodeBlockNode('html', $content, null, $mod, $this->parseHtml($context, $content));

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
			// parsed as Texy, its HTML is displayed as source code
			if ($content === '') {
				return null;
			}
			return new CodeBlockNode('texysource', $content, null, $mod, $context->parseBlock($content));
		}

		return null;
	}


	/**
	 * Parses the markup of html and pre blocks, which knows HTML tags, comments and entities only.
	 */
	private function parseHtml(ParseContext $context, string $text): ContentNode
	{
		return $context->getInlineParser()
			->withPatterns([Syntax::HtmlTag, Syntax::HtmlComment, Syntax::HtmlEntity])
			->parse($context, $text);
	}


	public function solveCodeBlock(CodeBlockNode $node, Html\Renderer $generator): Html\Element|string
	{
		// block/texysource - the HTML of the parsed demo displayed as source code
		if ($node->type === 'texysource') {
			$html = $generator->serialize($generator->renderNodes($node->content->children ?? []), "\n");
			$wellFormer = new Html\WellFormer($this->texy->htmlOutput);
			$wellFormer->raw($this->texy->unprotect(htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8')));
			$html = trim($wellFormer->finish());
			// Now escape the final HTML to show as source code
			$escaped = htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8');
			$escaped = $this->texy->protect($escaped, $this->texy::CONTENT_TEXTUAL);

			$el = new Html\Element('pre');
			$node->modifier?->decorate($this->texy, $el);
			$el->attrs['class'] = ['html', ...(array) ($el->attrs['class'] ?? [])];
			$el->create('code', $escaped);
			return $el;
		}

		// block/html - passthrough HTML tags/comments
		if ($node->type === 'html') {
			if ($node->code === '') {
				return "\n";
			}

			return $this->texy->protect($this->renderMarkup($node, $generator) . ' ', $this->texy::CONTENT_BLOCK);
		}

		// block/text - plain text with <br> for newlines (no wrapper)
		// Include trailing space inside protection for proper separation from next block
		if ($node->type === 'text') {
			$content = htmlspecialchars($node->code, ENT_NOQUOTES, 'UTF-8');
			$content = str_replace("\n", '<br>', $content);
			return $this->texy->protect($content . ' ', $this->texy::CONTENT_BLOCK);
		}

		// Types that use <pre> wrapper
		$el = new Html\Element('pre');
		$node->modifier?->decorate($this->texy, $el);

		// Language class prepended before modifier classes
		if ($node->language) {
			$el->attrs['class'] = [$node->language, ...(array) ($el->attrs['class'] ?? [])];
		}

		// PRE block - passthrough HTML tags and decoded entities
		if ($node->type === 'pre') {
			$el->setText($this->texy->protect($this->renderMarkup($node, $generator), $this->texy::CONTENT_BLOCK));
			return $el;
		}

		$content = htmlspecialchars($node->code, ENT_NOQUOTES, 'UTF-8');
		$content = $this->texy->protect($content, $this->texy::CONTENT_TEXTUAL);

		if ($node->type === 'code') {
			$el->create('code', $content);
		} else {
			$el->setText($content);
		}

		return $el;
	}


	public function solveSection(SectionNode $node, Html\Renderer $generator): Html\Element
	{
		$el = new Html\Element($node->type === 'div' ? 'div' : 'section');
		$node->modifier?->decorate($this->texy, $el);
		$el->children = $generator->renderNodes($node->content->children);
		return $el;
	}


	/**
	 * Serialize the parsed markup of a code block (html, pre) to HTML.
	 */
	private function renderMarkup(CodeBlockNode $node, Html\Renderer $generator): string
	{
		$html = $generator->serialize($generator->renderNodes($node->content->children ?? []));
		return $this->texy->unprotect(htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8'));
	}
}
