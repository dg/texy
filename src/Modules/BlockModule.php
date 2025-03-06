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
		$texy->htmlOutput->addRenderer($this->renderCode(...));
		$texy->htmlOutput->addRenderer($this->renderPreformatted(...));
		$texy->htmlOutput->addRenderer($this->renderHtml(...));
		$texy->htmlOutput->addRenderer($this->renderText(...));
		$texy->htmlOutput->addRenderer($this->renderTexySource(...));
		$texy->htmlOutput->addRenderer($this->renderDiv(...));
		$texy->htmlOutput->addRenderer(fn(CommentNode $node) => '');
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


	public function renderCode(CodeBlockNode $node, Html\Renderer $renderer): Html\Element
	{
		$el = new Html\Element('pre');
		$renderer->decorateElement($node->modifier, $el);
		// Language class prepended before modifier classes
		if ($node->language) {
			$el->attrs['class'] = [$node->language, ...(array) ($el->attrs['class'] ?? [])];
		}
		$el->create('code')->add($renderer->protect(htmlspecialchars($node->code, ENT_NOQUOTES, 'UTF-8'), $renderer::ContentTextual));
		return $el;
	}


	/**
	 * Preformatted text passes its HTML tags and decoded entities through.
	 */
	public function renderPreformatted(PreformattedNode $node, Html\Renderer $renderer): Html\Element
	{
		$el = new Html\Element('pre');
		$renderer->decorateElement($node->modifier, $el);
		$el->setText($renderer->protect($this->renderMarkup($node->content->children, $renderer), $renderer::ContentBlock));
		return $el;
	}


	public function renderHtml(HtmlBlockNode $node, Html\Renderer $renderer): string
	{
		return $node->code === ''
			? "\n"
			: $renderer->protect($this->renderMarkup($node->content->children, $renderer) . ' ', $renderer::ContentBlock);
	}


	/**
	 * Plain text with <br> for newlines, no wrapper.
	 */
	public function renderText(TextBlockNode $node, Html\Renderer $renderer): string
	{
		$content = htmlspecialchars($node->code, ENT_NOQUOTES, 'UTF-8');
		return $renderer->protect(str_replace("\n", '<br>', $content) . ' ', $renderer::ContentBlock);
	}


	/**
	 * The HTML of the parsed demo displayed as source code.
	 */
	public function renderTexySource(TexySourceNode $node, Html\Renderer $renderer): Html\Element
	{
		$wellFormer = new Html\WellFormer($renderer->config);
		$wellFormer->raw($renderer->unprotect(htmlspecialchars(
			$renderer->serialize($renderer->renderNodes($node->content->children), "\n"),
			ENT_NOQUOTES,
			'UTF-8',
		)));
		$html = trim($wellFormer->finish());

		$el = new Html\Element('pre');
		$renderer->decorateElement($node->modifier, $el);
		$el->attrs['class'] = ['html', ...(array) ($el->attrs['class'] ?? [])];
		$el->create('code')->add($renderer->protect(htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8'), $renderer::ContentTextual));
		return $el;
	}


	public function renderDiv(DivNode $node, Html\Renderer $renderer): Html\Element
	{
		$el = new Html\Element('div');
		$renderer->decorateElement($node->modifier, $el);
		$el->children = $renderer->renderNodes($node->content->children);
		return $el;
	}


	/**
	 * Serializes the parsed markup of a block (html, pre) to HTML.
	 * @param  array<Texy\Node>  $content
	 */
	private function renderMarkup(array $content, Html\Renderer $renderer): string
	{
		return $renderer->unprotect(htmlspecialchars($renderer->serialize($renderer->renderNodes($content)), ENT_NOQUOTES, 'UTF-8'));
	}
}
