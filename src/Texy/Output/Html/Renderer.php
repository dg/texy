<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\Helpers;
use Texy\Modifier;
use Texy\Node;
use Texy\Nodes;
use Texy\Output\NodeRenderer;
use Texy\Syntax;
use Texy\Texy;
use function base_convert, count, explode, htmlspecialchars, implode, in_array, is_string, ltrim, str_contains, str_replace, strncasecmp, strtr;
use const ENT_HTML5, ENT_NOQUOTES, ENT_QUOTES;


/**
 * Renders the AST to the output HTML string. Created per render() call;
 * configuration is read from the Config object.
 * @extends NodeRenderer<Element|string>
 */
final class Renderer extends NodeRenderer
{
	// types of protection marks
	public const
		ContentMarkup = "\x17",
		ContentReplaced = "\x16",
		ContentTextual = "\x15",
		ContentBlock = "\x14";

	private ElementDecorator $decorator;

	private bool $noFollow;

	private ImageDimensions $imageDimensions;

	/** @var array<string, string>  Protection markup table */
	private array $marks = [];


	public function __construct(
		public readonly Config $config,
		private Texy $texy,
		?bool $noFollow = null,
		private bool $titleTypography = true,
	) {
		$this->noFollow = $noFollow ?? $config->linkNoFollow;
		$this->imageDimensions = new ImageDimensions($config);
		$this->decorator = new ElementDecorator($texy->htmlPolicy, $config, $this);

		$this->handlers = [
			// Core nodes
			Nodes\DocumentNode::class => fn(Nodes\DocumentNode $n, self $g) => $g->renderDocument($n),
			Nodes\TextNode::class => fn(Nodes\TextNode $n) => $n->text,
			Nodes\ContentNode::class => fn(Nodes\ContentNode $n, self $g) => $g->serialize($g->renderNodes($n->children)),

			// Block nodes
			Nodes\ParagraphNode::class => fn(Nodes\ParagraphNode $n, self $g) => $g->renderParagraph($n),
			Nodes\HeadingNode::class => fn(Nodes\HeadingNode $n, self $g) => $g->renderHeading($n),
			Nodes\BlockQuoteNode::class => fn(Nodes\BlockQuoteNode $n, self $g) => $g->renderBlockQuote($n),
			Nodes\CodeBlockNode::class => fn(Nodes\CodeBlockNode $n, self $g) => $g->renderCodeBlock($n),
			Nodes\SectionNode::class => fn(Nodes\SectionNode $n, self $g) => $g->renderSection($n),
			Nodes\HorizontalRuleNode::class => fn(Nodes\HorizontalRuleNode $n, self $g) => $g->renderHorizontalRule($n),
			Nodes\FigureNode::class => fn(Nodes\FigureNode $n, self $g) => $g->renderFigure($n),

			// List nodes
			Nodes\ListNode::class => fn(Nodes\ListNode $n, self $g) => $g->renderList($n),
			Nodes\ListItemNode::class => fn(Nodes\ListItemNode $n, self $g) => $g->renderListItem($n),
			Nodes\DefinitionListNode::class => fn(Nodes\DefinitionListNode $n, self $g) => $g->renderDefinitionList($n),

			// Table nodes
			Nodes\TableNode::class => fn(Nodes\TableNode $n, self $g) => $g->renderTable($n),
			Nodes\TableRowNode::class => fn(Nodes\TableRowNode $n, self $g) => $g->renderTableRow($n),
			Nodes\TableCellNode::class => fn(Nodes\TableCellNode $n, self $g) => $g->renderTableCell($n),

			// Inline nodes
			Nodes\ImageNode::class => fn(Nodes\ImageNode $n, self $g) => $g->renderImage($n),
			Nodes\LinkNode::class => fn(Nodes\LinkNode $n, self $g) => $g->renderLink($n),
			Nodes\PhraseNode::class => fn(Nodes\PhraseNode $n, self $g) => $g->renderPhrase($n),
			Nodes\RawTextNode::class => fn(Nodes\RawTextNode $n) => htmlspecialchars($n->text, ENT_NOQUOTES, 'UTF-8'),
			Nodes\AnnotationNode::class => fn(Nodes\AnnotationNode $n) => (new Element('abbr', ['title' => $n->annotation]))->setText($n->text),
			Nodes\EmoticonNode::class => fn(Nodes\EmoticonNode $n, self $g) => $g->renderEmoticon($n),
			Nodes\LineBreakNode::class => fn() => $this->protect('<br>', self::ContentReplaced),

			// Autolink nodes
			Nodes\UrlNode::class => fn(Nodes\UrlNode $n, self $g) => $g->renderUrl($n),
			Nodes\EmailNode::class => fn(Nodes\EmailNode $n, self $g) => $g->renderEmail($n),

			// HTML passthrough nodes
			Nodes\HtmlTagNode::class => fn(Nodes\HtmlTagNode $n, self $g) => $g->renderHtmlTag($n),
			Nodes\HtmlElementNode::class => fn(Nodes\HtmlElementNode $n, self $g) => $g->renderHtmlElement($n),
			Nodes\HtmlCommentNode::class => fn(Nodes\HtmlCommentNode $n, self $g) => $g->renderHtmlComment($n),

			// Directive nodes
			Nodes\DirectiveNode::class => fn(Nodes\DirectiveNode $n, self $g) => $g->renderDirective($n),

			// Definition nodes (output empty string)
			Nodes\ImageDefinitionNode::class => fn() => '',
			Nodes\LinkDefinitionNode::class => fn() => '',
			Nodes\CommentNode::class => fn() => '',
		];

		foreach ($config->getHandlers() as $handler) {
			$this->registerHandler($handler);
		}
	}


	/**
	 * Render document AST to final HTML string.
	 */
	public function render(Nodes\DocumentNode $document): string
	{
		$this->marks = [];
		$s = $this->renderNode($document);
		assert(is_string($s));

		// decode HTML entities to UTF-8
		$s = Helpers::unescapeHtml($s);

		// line-postprocessing (typography, long words); skipped when typography
		// already ran over the AST
		$blocks = explode(self::ContentBlock, $s);
		foreach ($this->titleTypography ? $this->texy->postHandlers : [] as $name => $handler) {
			if (empty($this->texy->allowed[$name])) {
				continue;
			}

			foreach ($blocks as $n => $s) {
				if ($n % 2 === 0 && $s !== '') {
					$blocks[$n] = $handler($s);
				}
			}
		}

		$s = implode(self::ContentBlock, $blocks);

		// encode < > &
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');

		// replace protected marks
		$s = $this->unprotect($s);

		// wellform and reformat HTML
		$s = (new WellFormer($this->config))->format($s);

		// unfreeze spaces
		$s = Helpers::unfreezeSpaces($s);
		$s = ltrim($s, "\n");

		return $s;
	}


	/**
	 * Render children as array (for Element->children).
	 * @param array<Node> $content
	 * @return list<Element|string>
	 */
	public function renderNodes(array $content): array
	{
		$result = [];
		foreach ($content as $child) {
			$result[] = $this->renderNode($child);
		}
		return $result;
	}


	/**
	 * Render content with only HTML tag/comment patterns (passthrough).
	 */
	private function renderHtmlPassthrough(string $content): string
	{
		$context = $this->texy->createParseContext();
		$htmlParser = $context->getInlineParser()->withPatterns([Syntax::HtmlTag, Syntax::HtmlComment]);
		$parsed = $this->serialize($this->renderNodes($htmlParser->parse($context, $content)->children));
		$parsed = Helpers::unescapeHtml($parsed);
		$parsed = htmlspecialchars($parsed, ENT_NOQUOTES, 'UTF-8');
		return $this->unprotect($parsed);
	}


	/**
	 * Serialize Element/string array to string.
	 * @param array<Element|string> $content
	 */
	private function serialize(array $content, string $separator = ''): string
	{
		$html = [];
		foreach ($content as $child) {
			if ($child instanceof Element) {
				$html[] = $this->serializeElement($child);
			} else {
				$html[] = $child;
			}
		}
		return implode($separator, $html);
	}


	/**
	 * Serialize a single Element to string.
	 */
	private function serializeElement(Element $el): string
	{
		$ct = $this->getElementContentType($el);
		$s = $this->protect($el->startTag(), $ct);

		// empty elements are finished now
		if ($el->isEmpty()) {
			return $s;
		}

		// add content
		foreach ($el->children as $child) {
			if ($child instanceof Element) {
				$s .= $this->serializeElement($child);
			} else {
				$s .= $child;
			}
		}

		// add end tag
		return $s . $this->protect($el->endTag(), $ct);
	}


	/**
	 * Determine content type for an element.
	 */
	private function getElementContentType(Element $el): string
	{
		$inlineType = Schema::inlineElements()[$el->name ?? ''] ?? null;
		return $inlineType === null
			? self::ContentBlock
			: ($inlineType ? self::ContentReplaced : self::ContentMarkup);
	}


	/**
	 * Checks whether rendered children produce no visible output (only empty strings/whitespace).
	 * @param array<Element|string> $children
	 */
	private function renderedToNothing(array $children): bool
	{
		foreach ($children as $child) {
			if (!is_string($child) || trim($child) !== '') {
				return false;
			}
		}
		return true;
	}


	/**
	 * Applies typography to an isolated string (title, alt) unless the AST
	 * was already typographed in the transform phase or typography is disabled.
	 * @internal
	 */
	public function postLineText(string $s): string
	{
		return $this->titleTypography && !empty($this->texy->allowed[Syntax::Typography])
			? $this->texy->typographyModule->postLine($s)
			: $s;
	}


	/********************* protection mechanism ****************d*g**/


	/**
	 * Generate unique mark - useful for freezing (folding) some substrings.
	 */
	public function protect(string $child, string $contentType): string
	{
		if ($child === '') {
			return '';
		}

		$key = $contentType
			. strtr(base_convert((string) count($this->marks), 10, 8), '01234567', "\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F")
			. $contentType;

		$this->marks[$key] = $child;

		return $key;
	}


	/**
	 * Replace protection marks back to original content.
	 */
	public function unprotect(string $html): string
	{
		return strtr($html, $this->marks);
	}


	/********************* core node renderers ****************d*g**/


	private function renderDocument(Nodes\DocumentNode $node): string
	{
		return $this->serialize($this->renderNodes($node->content->children), '');
	}


	/********************* block node renderers ****************d*g**/


	private function renderParagraph(Nodes\ParagraphNode $node): Element
	{
		$children = $this->renderNodes($node->content->children);

		// Block HTML content - skip <p> wrapper entirely. The parse-time flag is
		// stale when the sanitize pass turned the block tags into text, so its
		// own predicate is re-checked over the transformed content: paragraphs
		// degraded to visible text get a normal <p> again.
		if ($node->blockHtml && $this->containsBlockPassthrough($node->content->children)) {
			return $this->wrapChildren($children);
		}

		// Whole content rendered to nothing (consumed directives, comments) → no paragraph at all
		if ($node->modifier === null && $this->renderedToNothing($children)) {
			return $this->wrapChildren([]);
		}

		$info = $this->analyzeContent($node->content->children);

		// Only markup (HTML tags/comments) without text ? no <p> wrapper
		if (!$info['hasText'] && !$info['hasOther'] && $info['hasMarkup'] && $node->modifier === null) {
			return $this->wrapChildren($children);
		}

		// Only replaced content (images) ? use nontextParagraph
		if (!$info['hasText'] && !$info['hasOther'] && $info['hasReplaced']) {
			return $this->createNontextParagraph($children, $node->modifier);
		}

		// Normal paragraph
		$el = new Element('p');
		$this->decorateElement($node->modifier, $el);
		$el->children = $children;
		return $el;
	}


	private function renderHeading(Nodes\HeadingNode $node): Element
	{
		$el = new Element('h' . $node->level);
		$this->decorateElement($node->modifier, $el);
		$el->children = $this->renderNodes($node->content->children);
		return $el;
	}


	private function renderBlockQuote(Nodes\BlockQuoteNode $node): Element
	{
		$el = new Element('blockquote');
		$this->decorateElement($node->modifier, $el);
		$el->children = $this->renderNodes($node->content->children);
		return $el;
	}


	private function renderCodeBlock(Nodes\CodeBlockNode $node): Element|string
	{
		// block/texysource - parse as texy, then display resulting HTML as source code
		if ($node->type === 'texysource') {
			$context = $this->texy->createParseContext();
			$parsed = $context->parseBlock($node->code);
			$content = $this->serialize($this->renderNodes($parsed->children), "\n");
			$html = Helpers::unescapeHtml($content);
			$html = htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8');
			$html = $this->unprotect($html);
			$html = (new WellFormer($this->config))->format($html);
			$html = Helpers::unfreezeSpaces($html);
			$html = trim($html);
			// Now escape the final HTML to show as source code
			$escaped = htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8');
			$escaped = $this->protect($escaped, self::ContentTextual);

			$el = new Element('pre');
			$this->decorateElement($node->modifier, $el);
			$el->attrs['class'] = array_merge(['html'], (array) ($el->attrs['class'] ?? []));
			$el->create('code', $escaped);
			return $el;
		}

		// block/html - parse HTML tags/comments, escape unknown ones
		if ($node->type === 'html') {
			$content = $node->code;
			if ($content === '') {
				return "\n";
			}

			$parsed = $this->renderHtmlPassthrough($content);
			return $this->protect($parsed . ' ', self::ContentBlock);
		}

		// block/text - plain text with <br> for newlines (no wrapper)
		if ($node->type === 'text') {
			$content = htmlspecialchars($node->code, ENT_NOQUOTES, 'UTF-8');
			$content = str_replace("\n", '<br>', $content);
			return $this->protect($content . ' ', self::ContentBlock);
		}

		// Types that use <pre> wrapper
		$el = new Element('pre');
		$this->decorateElement($node->modifier, $el);

		// Language class prepended before modifier classes
		if ($node->language) {
			$el->attrs['class'] = array_merge([$node->language], (array) ($el->attrs['class'] ?? []));
		}

		// PRE block - parse HTML tags, unescape entities
		if ($node->type === 'pre') {
			$parsed = $this->renderHtmlPassthrough($node->code);
			$content = $this->protect($parsed, self::ContentBlock);
			$el->setText($content);
			return $el;
		}

		$content = htmlspecialchars($node->code, ENT_NOQUOTES, 'UTF-8');
		$content = $this->protect($content, self::ContentTextual);

		if ($node->type === 'code') {
			$el->create('code', $content);
		} else {
			$el->setText($content);
		}

		return $el;
	}


	private function renderSection(Nodes\SectionNode $node): Element
	{
		$el = new Element($node->type === 'div' ? 'div' : 'section');
		$this->decorateElement($node->modifier, $el);
		$el->children = $this->renderNodes($node->content->children);
		return $el;
	}


	private function renderHorizontalRule(Nodes\HorizontalRuleNode $node): Element
	{
		$el = new Element('hr');
		$this->decorateElement($node->modifier, $el);

		// Add default class if not already set via modifier
		$class = $this->config->horizontalRuleClasses[$node->type] ?? null;
		if ($class && empty($node->modifier?->classes[$class])) {
			$el->attrs['class'] = (array) ($el->attrs['class'] ?? []);
			$el->attrs['class'][] = $class;
		}

		return $el;
	}


	private function renderFigure(Nodes\FigureNode $node): Element
	{
		$el = new Element($this->config->figureTagName);

		// Get the actual ImageNode (might be wrapped in LinkNode)
		$image = $node->image;
		$inner = $image instanceof Nodes\LinkNode ? ($image->content->children[0] ?? null) : $image;

		// Extract alignment from ImageNode - we'll apply it to the figure wrapper instead;
		// clear it on a copy so generator won't add it to <img> (the node must stay untouched)
		$hAlign = null;
		if ($inner instanceof Nodes\ImageNode && $inner->modifier?->hAlign !== null) {
			$hAlign = $inner->modifier->hAlign;
			$cleared = clone $inner;
			$cleared->modifier = clone $inner->modifier;
			$cleared->modifier->hAlign = null;
			if ($image instanceof Nodes\LinkNode) {
				$image = clone $image;
				$image->content = clone $image->content;
				$image->content->children[0] = $cleared;
			} else {
				$image = $cleared;
			}
		}

		// Build image via generator - this allows custom ImageNode handlers to work
		$el->children = $this->renderNodes([$image]);

		// Caption
		if ($node->caption !== null) {
			$el->create($this->config->figureTagName === 'figure' ? 'figcaption' : 'p')
				->children = $this->renderNodes($node->caption->children);
		}

		// Modifier classes/styles
		$this->decorateElement($node->modifier, $el);

		// Figure class based on alignment (extracted from ImageNode above)
		$class = $this->config->figureClass;
		if ($hAlign) {
			$hAlignClass = match ($hAlign) {
				'left' => $this->config->figureLeftClass,
				'right' => $this->config->figureRightClass,
				default => null,
			};
			if ($hAlignClass) {
				$class = $hAlignClass;
			} elseif (empty($this->config->alignClasses[$hAlign])) {
				$el->attrs['style'] = (array) ($el->attrs['style'] ?? []);
				$el->attrs['style']['float'] = $hAlign;
			} else {
				$class .= '-' . $this->config->alignClasses[$hAlign];
			}
		}

		if ($class) {
			$el->attrs['class'] = (array) ($el->attrs['class'] ?? []);
			$el->attrs['class'][] = $class;
		}

		return $el;
	}


	/********************* list node renderers ****************d*g**/


	private function renderList(Nodes\ListNode $node): Element
	{
		$el = new Element($node->type->isOrdered() ? 'ol' : 'ul');
		$this->decorateElement($node->modifier, $el);

		if ($node->start !== null && $node->start > 1) {
			$el->attrs['start'] = $node->start;
		}

		if ($style = $node->type->getStyleType()) {
			// modifier may already have set styles, so merge instead of overwriting
			$styles = $el->attrs['style'] ?? [];
			$styles = is_array($styles) ? $styles : [];
			$styles['list-style-type'] = $style;
			$el->attrs['style'] = $styles;
		}

		foreach ($node->items as $item) {
			$el->add($this->renderListItem($item));
		}

		return $el;
	}


	private function renderListItem(Nodes\ListItemNode $node): Element
	{
		// Regular list item (li)
		$el = new Element('li');
		$this->decorateElement($node->modifier, $el);

		// If first child is a simple ParagraphNode (no modifier), unwrap it
		$content = $node->content->children;
		if (
			count($content) === 1
			&& $content[0] instanceof Nodes\ParagraphNode
			&& $content[0]->modifier === null
		) {
			$el->children = $this->renderNodes($content[0]->content->children);
		} else {
			$el->children = $this->renderNodes($content);
		}

		return $el;
	}


	private function renderDefinitionList(Nodes\DefinitionListNode $node): Element
	{
		$el = new Element('dl');
		$this->decorateElement($node->modifier, $el);

		foreach ($node->items as $item) {
			$el->add($this->renderDefinitionItem($item));
		}

		return $el;
	}


	private function renderDefinitionItem(Nodes\ListItemNode $node): Element
	{
		// Definition list item (dt for term=true, dd for term=false)
		$el = new Element($node->term ? 'dt' : 'dd');
		$this->decorateElement($node->modifier, $el);

		// If content is a single simple ParagraphNode (no modifier), unwrap it
		$content = $node->content->children;
		if (
			!$node->term
			&& count($content) === 1
			&& $content[0] instanceof Nodes\ParagraphNode
			&& $content[0]->modifier === null
		) {
			$el->children = $this->renderNodes($content[0]->content->children);
		} else {
			$el->children = $this->renderNodes($content);
		}

		return $el;
	}


	/********************* table node renderers ****************d*g**/


	private function renderTable(Nodes\TableNode $node): Element
	{
		$el = new Element('table');
		$this->decorateElement($node->modifier, $el);

		$elPart = null;
		foreach ($node->rows as $row) {
			if ($elPart === null) {
				$elPart = new Element($row->header ? 'thead' : 'tbody');
				$el->add($elPart);
			} elseif (!$row->header && $elPart->name === 'thead') {
				// switch from thead to tbody
				$elPart = new Element('tbody');
				$el->add($elPart);
			}
			$elPart->add($this->renderTableRow($row));
		}

		// if only thead without tbody, rename to tbody (tbody is required, thead is optional)
		if ($elPart !== null && $elPart->name === 'thead') {
			$elPart->name = 'tbody';
		}

		return $el;
	}


	private function renderTableRow(Nodes\TableRowNode $node): Element
	{
		$el = new Element('tr');
		$this->decorateElement($node->modifier, $el);
		foreach ($node->cells as $cell) {
			$el->add($this->renderTableCell($cell));
		}
		return $el;
	}


	private function renderTableCell(Nodes\TableCellNode $node): Element
	{
		$el = new Element($node->header ? 'th' : 'td');
		$this->decorateElement($node->modifier, $el);

		if ($node->colspan > 1) {
			$el->attrs['colspan'] = $node->colspan;
		}
		if ($node->rowspan > 1) {
			$el->attrs['rowspan'] = $node->rowspan;
		}

		$el->children = $this->renderNodes($node->content->children);
		return $el;
	}


	/********************* inline node renderers ****************d*g**/


	private function renderImage(Nodes\ImageNode $node): Element
	{
		// Detect dimensions from file system
		[$width, $height] = $this->imageDimensions->detect($node);

		$el = new Element('img');
		$mod = $node->modifier;

		// Extract title/hAlign and clear them on a copy before decorate (the node must stay untouched)
		$alt = $mod?->title;
		$hAlign = $mod?->hAlign;
		if ($mod !== null) {
			$mod = clone $mod;
			$mod->title = null;
			$mod->hAlign = null;
		}

		// Custom attrs from modifier (like {alt:...; title:...})
		$hasCustomAlt = isset($mod?->attrs['alt']);
		if ($hasCustomAlt) {
			$el->attrs['alt'] = $mod->attrs['alt'];
		}
		if (isset($mod?->attrs['title'])) {
			$el->attrs['title'] = $mod->attrs['title'];
		}

		// Reserve src position (decorate() may overwrite attrs array)
		$el->attrs['src'] = null;

		// class/style from modifier
		$this->decorateElement($mod, $el);

		// src
		$el->attrs['src'] = $node->url !== null ? Helpers::prependRoot($node->url, $this->config->imageRoot) : null;

		// alt: from title or empty (if not set by custom attrs)
		if (!$hasCustomAlt && !isset($el->attrs['alt'])) {
			$el->attrs['alt'] = $alt !== null ? $this->postLineText($alt) : '';
		}

		// hAlign ? float class or style
		if ($hAlign) {
			$class = match ($hAlign) {
				'left' => $this->config->imageLeftClass,
				'right' => $this->config->imageRightClass,
				default => null,
			};
			if ($class) {
				$el->attrs['class'] = (array) ($el->attrs['class'] ?? []);
				$el->attrs['class'][] = $class;
			} elseif (!empty($this->config->alignClasses[$hAlign])) {
				$el->attrs['class'] = (array) ($el->attrs['class'] ?? []);
				$el->attrs['class'][] = $this->config->alignClasses[$hAlign];
			} else {
				$el->attrs['style'] = (array) ($el->attrs['style'] ?? []);
				$el->attrs['style']['float'] = $hAlign;
			}
		}

		// dimensions
		$el->attrs['width'] = $width;
		$el->attrs['height'] = $height;

		return $el;
	}


	private function renderLink(Nodes\LinkNode $node): Element|string
	{
		// Check URL scheme (security - filter dangerous schemes like javascript:)
		$rawUrl = $node->url ?? '';
		if (!$this->texy->urlPolicy->isLinkAllowed($rawUrl)) {
			// URL fails scheme filter, return just the content without link
			return $this->serialize($this->renderNodes($node->content->children));
		}

		$el = new Element('a');

		// Handle nofollow class (work on a copy, the node must stay untouched)
		$mod = $node->modifier;
		$nofollow = false;
		if ($mod !== null && isset($mod->classes['nofollow'])) {
			$nofollow = true;
			$mod = clone $mod;
			unset($mod->classes['nofollow']);
		}

		// Apply modifier (title, class, id, style, etc.) before href
		$el->attrs['href'] = null; // trick - reserve position at front
		$this->decorateElement($mod, $el);

		// Normalize www. to http://
		if (strncasecmp($rawUrl, 'www.', 4) === 0) {
			$rawUrl = 'http://' . $rawUrl;
		}

		// Prepend root to relative URLs
		// Use imageModule.root for image links, linkModule.root otherwise
		$root = $node->isImageLink ? $this->config->imageRoot : $this->config->linkRoot;
		$el->attrs['href'] = Helpers::prependRoot($rawUrl, $root);

		// rel="nofollow"
		if ($nofollow || ($this->noFollow && str_contains($el->attrs['href'], '//'))) {
			$el->attrs['rel'] = 'nofollow';
		}

		// Add content
		$el->children = $this->renderNodes($node->content->children);

		return $el;
	}


	private function renderPhrase(Nodes\PhraseNode $node): Element
	{
		$tag = $this->config->phraseTags[$node->type] ?? 'span';

		if ($node->type === Syntax::StrongEmphasis) {
			$el = new Element($this->config->phraseTags[Syntax::Strong] ?? 'strong');
			$this->decorateElement($node->modifier, $el);
			$inner = $el->create($this->config->phraseTags[Syntax::Emphasis] ?? 'em');
			$inner->children = $this->renderNodes($node->content->children);
			return $el;
		}

		// Code phrases - escape and protect content from typography
		if ($node->type === Syntax::Code) {
			$el = new Element($tag);
			$this->decorateElement($node->modifier, $el);
			$content = $this->serialize($this->renderNodes($node->content->children));
			// Only escape <, >, & - not quotes (ENT_NOQUOTES)
			$content = htmlspecialchars($content, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
			$el->children = [$this->protect($content, self::ContentTextual)];
			return $el;
		}

		// Normal phrases
		$el = new Element($tag);
		$this->decorateElement($node->modifier, $el);
		$el->children = $this->renderNodes($node->content->children);
		return $el;
	}


	private function renderEmoticon(Nodes\EmoticonNode $node): Element|string
	{
		$emoji = $this->texy->emoticonModule->icons[$node->emoticon];
		return $this->config->emoticonClass
			? (new Element('span', ['class' => $this->config->emoticonClass]))->setText($emoji)
			: $emoji;
	}


	/********************* autolink node renderers ****************d*g**/


	private function renderUrl(Nodes\UrlNode $node): Element
	{
		$url = strncasecmp($node->url, 'www.', 4) === 0
			? 'http://' . $node->url
			: $node->url;

		$el = new Element('a', ['href' => $url]);
		if ($this->noFollow && str_contains($url, '//')) {
			$el->attrs['rel'] = 'nofollow';
		}

		// Protect URL text from typography/longwords processing
		$textualUrl = $this->config->shortenUrls ? Helpers::shortenUrl($node->url) : $node->url;
		return $el->add($this->protect($textualUrl, self::ContentTextual));
	}


	private function renderEmail(Nodes\EmailNode $node): Element
	{
		$el = new Element('a', ['href' => 'mailto:' . $node->email]);
		$text = $this->config->obfuscateEmail
			? $this->protect(str_replace('@', '&#64;<!-- -->', $node->email), self::ContentTextual)
			: $node->email;
		return $el->add($text);
	}


	/********************* HTML passthrough node renderers ****************d*g**/


	private function renderHtmlTag(Nodes\HtmlTagNode $node): string
	{
		$tagName = strtolower($node->name);
		$attrs = $node->attributes;

		// Check if tag is allowed
		if (!$this->texy->htmlPolicy->isTagAllowed($tagName)) {
			return $this->texy->htmlPolicy->escapeHtmlTag($node);
		}

		// Validate tag - reject if validation fails
		$validation = $this->texy->htmlPolicy->validateTag($tagName, $attrs, $node->closing);
		if ($validation === false || $validation === 'drop') {
			// Invalid tag - escape as text (shows original tag in output)
			return $this->texy->htmlPolicy->escapeHtmlTag($node);
		}

		// Add rel="nofollow" for external links when linkNoFollow is enabled
		if ($tagName === 'a' && !$node->closing && isset($attrs['href']) && is_string($attrs['href'])) {
			if ($this->noFollow && str_contains($attrs['href'], '//')) {
				$existingRel = isset($attrs['rel']) && is_string($attrs['rel']) ? $attrs['rel'] : '';
				$relParts = $existingRel ? explode(' ', $existingRel) : [];
				if (!in_array('nofollow', $relParts, true)) {
					$relParts[] = 'nofollow';
				}
				$attrs['rel'] = implode(' ', $relParts);
			}
		}

		// Determine content type based on Schema::inlineElements()
		// Value 0 = inline markup, 1 = inline replaced, not present = block
		$inlineType = Schema::inlineElements()[$tagName] ?? null;
		if ($inlineType === null) {
			$type = self::ContentBlock;
		} elseif ($inlineType === 1) {
			$type = self::ContentReplaced;
		} else {
			$type = self::ContentMarkup;
		}

		if ($node->closing) {
			$html = '</' . htmlspecialchars($tagName, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '>';
			return $this->protect($html, $type);
		}

		$html = '<' . htmlspecialchars($tagName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$html .= Element::formatAttrs($attrs);
		$html .= '>';

		return $this->protect($html, $type);
	}


	private function renderHtmlComment(Nodes\HtmlCommentNode $node): string
	{
		if (!$this->config->passHtmlComments) {
			return '';
		}

		// Sanitize comment content (security: prevent nested comments)
		$content = preg_replace('~-{2,}~', ' - ', $node->text);
		$content = trim($content, '-');
		return $this->protect('<!--' . $content . '-->', self::ContentMarkup);
	}


	/**
	 * Renders paired passthrough element as open tag + children + close tag,
	 * delegating to renderHtmlTag() so sanitization and escaping behave
	 * exactly like for unpaired tags.
	 */
	private function renderHtmlElement(Nodes\HtmlElementNode $node): string
	{
		$open = new Nodes\HtmlTagNode($node->name, $node->attributes, range: $node->range);
		$close = $node->closingTag ?? new Nodes\HtmlTagNode($node->name, closing: true);
		return $this->renderHtmlTag($open)
			. $this->serialize($this->renderNodes($node->content->children))
			. $this->renderHtmlTag($close);
	}


	/********************* directive node renderers ****************d*g**/


	private function renderDirective(Nodes\DirectiveNode $node): string
	{
		$parsed = $node->parseContent();

		// {{texy: ...}} directives are normally consumed by DirectiveModule::processDirectives()
		// in the transform phase; swallow any that reach the renderer (hand-built AST)
		if ($parsed['name'] === 'texy' && $parsed['args']) {
			return '';
		}

		// Unknown directives - preserve original text
		return '{{' . $node->text . '}}';
	}


	/********************* utility methods ****************d*g**/


	/**
	 * Does the content still contain block-level passthrough markup?
	 * The same predicate ParagraphModule uses when setting the blockHtml flag,
	 * re-evaluated after transforms (sanitization may have turned tags to text).
	 * @param  array<Node>  $content
	 */
	private function containsBlockPassthrough(array $content): bool
	{
		foreach ($content as $node) {
			if ($node instanceof Nodes\HtmlTagNode) {
				if (!$node->closing && !isset(Schema::inlineElements()[strtolower($node->name)])) {
					return true;
				}
			} elseif ($node instanceof Nodes\HtmlElementNode) {
				// block element, or a transparent inline wrapper (<a>, <ins>, ...) holding one
				if (
					!isset(Schema::inlineElements()[strtolower($node->name)])
					|| $this->containsBlockPassthrough($node->content->children)
				) {
					return true;
				}
			}
		}

		return false;
	}


	/**
	 * Analyze paragraph content to determine what types of nodes it contains.
	 * @param  array<Nodes\InlineNode|Nodes\BlockNode>  $content
	 * @return array{hasText: bool, hasReplaced: bool, hasMarkup: bool, hasOther: bool}
	 */
	public function analyzeContent(array $content): array
	{
		$hasText = false;
		$hasReplaced = false;
		$hasMarkup = false;
		$hasOther = false;

		foreach ($content as $node) {
			if ($node instanceof Nodes\TextNode) {
				$hasText = $hasText || trim($node->text) !== '';

			} elseif ($node instanceof Nodes\ImageNode) {
				$hasReplaced = true;

			} elseif ($node instanceof Nodes\HtmlCommentNode) {
				$hasMarkup = true;

			} elseif ($node instanceof Nodes\HtmlTagNode) {
				if ($node->closing) {
					continue;
				}
				$inlineType = Schema::inlineElements()[strtolower($node->name)] ?? null;
				match ($inlineType) {
					1 => $hasReplaced = true,    // replaced element (img, br, input, ...)
					0 => $hasMarkup = true,      // inline markup (span, a, strong, ...)
					null => $hasMarkup = true,   // block element
				};

			} elseif ($node instanceof Nodes\HtmlElementNode) {
				$inlineType = Schema::inlineElements()[strtolower($node->name)] ?? null;
				match ($inlineType) {
					1 => $hasReplaced = true,    // replaced element (script, object, ...)
					default => $hasMarkup = true,
				};
				// flat pre-pairing analysis saw all descendants at top level - propagate everything
				$inner = $this->analyzeContent($node->content->children);
				$hasText = $hasText || $inner['hasText'];
				$hasReplaced = $hasReplaced || $inner['hasReplaced'];
				$hasMarkup = $hasMarkup || $inner['hasMarkup'];
				$hasOther = $hasOther || $inner['hasOther'];

			} elseif ($node instanceof Nodes\LinkNode) {
				$inner = $this->analyzeContent($node->content->children);
				if ($inner['hasText'] || $inner['hasOther']) {
					$hasOther = true;
				} elseif ($inner['hasReplaced']) {
					$hasReplaced = true;
				}

			} else {
				$hasOther = true;
			}
		}

		return compact('hasText', 'hasReplaced', 'hasMarkup', 'hasOther');
	}


	/**
	 * Wrap children in a null element (no tag wrapper).
	 * @param list<Element|string> $children
	 */
	public function wrapChildren(array $children): Element
	{
		$el = new Element(null);
		$el->children = $children;
		return $el;
	}


	/**
	 * Create paragraph for non-text content (images only).
	 * @param list<Element|string> $children
	 */
	public function createNontextParagraph(array $children, ?Modifier $modifier): Element
	{
		$nontextParagraph = $this->config->nontextParagraph;
		if ($nontextParagraph instanceof Element) {
			$el = clone $nontextParagraph;
			$this->decorateElement($modifier, $el);
			$el->children = $children;
			return $el;
		}
		$el = new Element($nontextParagraph);
		$this->decorateElement($modifier, $el);
		$el->children = $children;
		return $el;
	}


	/**
	 * Decorate element with modifier's classes, styles, and attributes.
	 */
	public function decorateElement(?Modifier $modifier, Element $el): void
	{
		$this->decorator->decorate($modifier, $el);
	}
}
