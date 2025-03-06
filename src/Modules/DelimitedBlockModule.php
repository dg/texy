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
		foreach (self::Types as $name) {
			$this->texy->declare($name);
		}

		$this->texy->addBlock(
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
	 */
	public function parse(ParseContext $context, array $matches): ?BlockNode
	{
		$parts = Texy\Regexp::split($matches[1], '~\s+~', limit: 2);
		$type = $parts[0];
		if (isset(self::Types[$type]) && empty($this->texy->allowed[self::Types[$type]])) {
			return null;
		}

		$param = empty($parts[1]) ? null : $parts[1];
		$mod = Texy\Modifier::parse($matches[2]);
		$mContent = $matches[3];
		$content = Helpers::outdent($mContent);

		switch ($type) {
			case 'code':
				return new CodeBlockNode($content, $param, $mod);

			case 'pre':
				return new PreformattedNode($content, $this->parseVerbatim($context, $content), $mod, Syntax::DelimitedPre);

			case '':
				return new PreformattedNode($content, $this->parseVerbatim($context, $content, []), $mod, Syntax::DelimitedBare);

			case 'comment':
				return new CommentNode($content);

			case 'html':
			case 'text':
				// html/text blocks don't use outdent - preserve original indentation
				$content = trim($mContent, "\n");
				return $type === 'html'
					? new HtmlBlockNode($content, $this->parseVerbatim($context, $content), $mod)
					: new TextBlockNode($content, $mod);

			case 'div':
				$content = Helpers::outdent($mContent, firstLine: true);
				if ($content === '') {
					return null;
				}
				return new DivNode($context->parseBlock($content), $mod);

			case 'texysource':
				// parsed as Texy, its HTML is displayed as source code
				if ($content === '') {
					return null;
				}
				return new TexySourceNode($content, $context->parseBlock($content), $mod);

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
