<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\{Helpers, HtmlPolicy, Modifier, Node, Nodes, UrlPolicy};
use Texy\Output\NodeRenderer;
use function count, is_array, is_string;
use const ENT_HTML5, ENT_NOQUOTES, ENT_QUOTES;


/**
 * Renders the AST to the output HTML string; needs no live Texy instance.
 * Configuration is read from the Config object; the per-document fact (nofollow)
 * is derived from the document itself at render time, so the instance is a
 * configuration snapshot reusable across documents.
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

	/** the list whose items are being rendered */
	private Nodes\ListNode|Nodes\DefinitionListNode|null $list = null;

	private ImageDimensions $imageDimensions;

	/** @var array<string, string>  Protection markup table */
	private array $marks = [];


	public function __construct(
		public readonly Config $config,
		private HtmlPolicy $htmlPolicy,
		private UrlPolicy $urlPolicy,
		/** @var list<\Closure(string): string> post-processing of the document text */
		private array $postHandlers = [],
	) {
		$this->noFollow = $config->linkNoFollow;
		$this->imageDimensions = new ImageDimensions($config);
		$this->decorator = new ElementDecorator($htmlPolicy, $config);

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
			Nodes\PreformattedNode::class => fn(Nodes\PreformattedNode $n, self $g) => $g->renderPreformatted($n),
			Nodes\HtmlBlockNode::class => fn(Nodes\HtmlBlockNode $n, self $g) => $g->renderHtmlBlock($n),
			Nodes\TextBlockNode::class => fn(Nodes\TextBlockNode $n, self $g) => $g->renderTextBlock($n),
			Nodes\TexySourceNode::class => fn(Nodes\TexySourceNode $n, self $g) => $g->renderTexySource($n),
			Nodes\DivNode::class => fn(Nodes\DivNode $n, self $g) => $g->renderDiv($n),
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
			Nodes\InlineCodeNode::class => fn(Nodes\InlineCodeNode $n, self $g) => $g->renderInlineCode($n),
			Nodes\RawTextNode::class => fn(Nodes\RawTextNode $n) => $n->text,
			Nodes\AnnotationNode::class => fn(Nodes\AnnotationNode $n) => new Element('abbr', ['title' => $n->annotation])->setText(
				$n->text,
			),
			Nodes\EmoticonNode::class => fn(Nodes\EmoticonNode $n, self $g) => $g->renderEmoticon($n),
			Nodes\LineBreakNode::class => fn() => $this->protect('<br>', self::ContentReplaced),

			// Autolink nodes
			Nodes\UrlNode::class => fn(Nodes\UrlNode $n, self $g) => $g->renderUrl($n),
			Nodes\EmailNode::class => fn(Nodes\EmailNode $n, self $g) => $g->renderEmail($n),

			// HTML passthrough nodes
			Nodes\HtmlTagNode::class => fn(Nodes\HtmlTagNode $n, self $g) => $g->renderHtmlTag($n),
			Nodes\HtmlCommentNode::class => fn(Nodes\HtmlCommentNode $n, self $g) => $g->renderHtmlComment($n),

			// Directive nodes
			Nodes\DirectiveNode::class => fn(Nodes\DirectiveNode $n, self $g) => $g->renderDirective($n),

			// Definition nodes (output empty string)
			Nodes\ImageDefinitionNode::class => fn() => '',
			Nodes\LinkDefinitionNode::class => fn() => '',
			Nodes\CommentNode::class => fn() => '',
		];

		foreach ($config->getRenderers() as $handler) {
			$this->addRenderer($handler);
		}
	}


	/**
	 * Render document AST to final HTML string. Per-document facts are read
	 * from the document itself: the {{texy: nofollow}} directive.
	 */
	public function render(Nodes\DocumentNode $document): string
	{
		$this->noFollow = $this->config->linkNoFollow || !empty($document->meta['nofollow']);
		$this->marks = [];
		$s = $this->renderNode($document);
		assert(is_string($s));

		// line-postprocessing
		$blocks = explode(self::ContentBlock, $s);
		foreach ($this->postHandlers as $handler) {
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
		$wellFormer = new WellFormer($this->config);
		$wellFormer->raw($s);
		return ltrim($wellFormer->finish(), "\n");
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
	 * Checks whether rendered children produce no visible output (only empty strings/whitespace).
	 * @param array<Element|string> $children
	 */
	private function renderedToNothing(array $children): bool
	{
		return array_all($children, fn($child) => is_string($child) && trim($child) === '');
	}


	/**
	 * Well-forms a standalone fragment (texysource).
	 * @param array<Element|string> $content
	 */
	private function finalizeFragment(array $content): string
	{
		$wellFormer = new WellFormer($this->config);
		$wellFormer->raw($this->unprotect(htmlspecialchars($this->serialize($content, "\n"), ENT_NOQUOTES, 'UTF-8')));
		return trim($wellFormer->finish());
	}


	/**
	 * Serializes the parsed markup of a block (html, pre) to HTML.
	 * @param array<Node> $content
	 */
	private function renderMarkup(array $content): string
	{
		return $this->unprotect(htmlspecialchars($this->serialize($this->renderNodes($content)), ENT_NOQUOTES, 'UTF-8'));
	}


	/**
	 * Serialize Element/string array to string.
	 * @param array<Element|string> $content
	 */
	private function serialize(array $content, string $separator = ''): string
	{
		$html = [];
		foreach ($content as $child) {
			$html[] = $child instanceof Element ? $this->serializeElement($child) : $child;
		}

		return implode($separator, $html);
	}


	/**
	 * Serialize a single Element to string, its tags protected.
	 */
	private function serializeElement(Element $el): string
	{
		$ct = $this->getElementContentType($el);
		$s = $this->protect($el->startTag(), $ct);
		if ($el->isVoid()) {
			return $s;
		}

		foreach ($el->children as $child) {
			$s .= $child instanceof Element ? $this->serializeElement($child) : $child;
		}

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
		return $this->serialize($this->renderNodes($node->content->children));
	}


	/********************* block node renderers ****************d*g**/


	private function renderParagraph(Nodes\ParagraphNode $node): Element
	{
		$children = $this->renderNodes($node->content->children);

		// Block HTML content - skip <p> wrapper entirely
		if ($node->blockHtml) {
			return $this->wrapChildren($children);
		}

		// Whole content rendered to nothing (consumed directives, comments) → no paragraph at all
		if ($node->modifier === null && $this->renderedToNothing($children)) {
			return $this->wrapChildren([]);
		}

		$info = $this->analyzeContent($node->content->children);

		// Only markup (HTML tags/comments) without text → no <p> wrapper
		if (!$info['hasText'] && !$info['hasOther'] && $info['hasMarkup'] && $node->modifier === null) {
			return $this->wrapChildren($children);
		}

		// Only replaced content (images) → use imageParagraphTag
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


	private function renderCodeBlock(Nodes\CodeBlockNode $node): Element
	{
		$el = new Element('pre');
		$this->decorateElement($node->modifier, $el);
		// Language class prepended before modifier classes
		if ($node->language) {
			$el->attrs['class'] = [$node->language, ...(array) ($el->attrs['class'] ?? [])];
		}
		$el->create('code')->add($this->protect(htmlspecialchars($node->code, ENT_NOQUOTES, 'UTF-8'), self::ContentTextual));
		return $el;
	}


	/**
	 * Preformatted text passes its HTML tags and decoded entities through.
	 */
	private function renderPreformatted(Nodes\PreformattedNode $node): Element
	{
		$el = new Element('pre');
		$this->decorateElement($node->modifier, $el);
		$el->setText($this->protect($this->renderMarkup($node->content->children), self::ContentBlock));
		return $el;
	}


	private function renderHtmlBlock(Nodes\HtmlBlockNode $node): string
	{
		return $node->code === ''
			? "\n"
			: $this->protect($this->renderMarkup($node->content->children) . ' ', self::ContentBlock);
	}


	/**
	 * Plain text with <br> for newlines, no wrapper.
	 */
	private function renderTextBlock(Nodes\TextBlockNode $node): string
	{
		$content = htmlspecialchars($node->code, ENT_NOQUOTES, 'UTF-8');
		return $this->protect(str_replace("\n", '<br>', $content) . ' ', self::ContentBlock);
	}


	/**
	 * The HTML of the parsed demo displayed as source code.
	 */
	private function renderTexySource(Nodes\TexySourceNode $node): Element
	{
		$html = $this->finalizeFragment($this->renderNodes($node->content->children));
		$el = new Element('pre');
		$this->decorateElement($node->modifier, $el);
		$el->attrs['class'] = ['html', ...(array) ($el->attrs['class'] ?? [])];
		$el->create('code')->add($this->protect(htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8'), self::ContentTextual));
		return $el;
	}


	private function renderDiv(Nodes\DivNode $node): Element
	{
		$el = new Element('div');
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
			$el->addClass($class);
		}

		return $el;
	}


	private function renderFigure(Nodes\FigureNode $node): Element
	{
		$el = new Element($this->config->figureTag);

		// Build image via generator - this allows custom ImageNode handlers to work
		$el->children = $this->renderNodes([$node->image]);

		// Caption
		if ($node->caption !== null) {
			$el->create($this->config->figureTag === 'figure' ? 'figcaption' : 'p')
				->children = $this->renderNodes($node->caption->children);
		}

		// Modifier classes/styles
		$this->decorateElement($node->modifier, $el);

		if ($this->config->figureClass) {
			$el->addClass($this->config->figureClass);
		}
		$this->applyFloat($el, $node->float);

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

		$this->renderItems($node, $el);
		return $el;
	}


	private function renderListItem(Nodes\ListItemNode $node): Element
	{
		$list = $this->list;
		$this->list = null; // the content of the item belongs to no list, a nested one rendered by a handler included
		if ($list instanceof Nodes\DefinitionListNode) {
			return $this->renderDefinitionItem($node);
		}

		$el = new Element('li');
		$this->decorateElement($node->modifier, $el);
		$el->children = $this->renderItemContent($node->content->children);
		return $el;
	}


	private function renderDefinitionList(Nodes\DefinitionListNode $node): Element
	{
		$el = new Element('dl');
		$this->decorateElement($node->modifier, $el);

		$this->renderItems($node, $el);
		return $el;
	}


	/**
	 * Renders the items of a list into its element; an item learns from the list how it is rendered.
	 */
	private function renderItems(Nodes\ListNode|Nodes\DefinitionListNode $list, Element $el): void
	{
		$outer = $this->list;
		try {
			foreach ($list->items as $item) {
				$this->list = $list;
				$el->add($this->renderNode($item));
			}
		} finally {
			$this->list = $outer;
		}
	}


	private function renderDefinitionItem(Nodes\ListItemNode $node): Element
	{
		// Definition list item (dt for term=true, dd for term=false)
		$el = new Element($node->term ? 'dt' : 'dd');
		$this->decorateElement($node->modifier, $el);
		$el->children = $node->term
			? $this->renderNodes($node->content->children)
			: $this->renderItemContent($node->content->children);
		return $el;
	}


	/**
	 * The item is a block container itself, so a wrapper on the block that opens it
	 * adds nothing: <li>text<ul>…</ul></li> needs no <p> around the text. A second
	 * paragraph does get one, because there the wrapper is what separates them.
	 * @param  array<Node>  $content
	 * @return list<Element|string>
	 */
	private function renderItemContent(array $content): array
	{
		$first = $content[0] ?? null;
		$rest = array_slice($content, 1);

		$opening = match (true) {
			$first instanceof Nodes\ParagraphNode && $first->modifier === null => $this->renderNodes($first->content->children),
			!$rest && $first instanceof Nodes\FigureNode && $first->caption === null && $first->modifier === null
				=> $this->renderNodes([$first->image]),
			default => null,
		};

		return $opening === null
			? $this->renderNodes($content)
			: [...$opening, ...$this->renderNodes($rest)];
	}


	/********************* table node renderers ****************d*g**/


	private function renderTable(Nodes\TableNode $node): Element
	{
		$el = new Element('table');
		$this->decorateElement($node->modifier, $el);

		if ($node->caption !== null) {
			$el->create('caption')->children = $this->renderNodes($node->caption->children);
		}

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
			$elPart->add($this->renderNode($row));
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
			$el->add($this->renderNode($cell));
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
		// Check URL scheme (security - filter dangerous schemes like javascript:)
		if ($node->url === null || !$this->urlPolicy->isImageAllowed($node->url)) {
			// URL is missing or fails scheme filter, return just the alternative text
			return $this->wrapChildren([$node->alt ?? '']);
		}

		// Detect dimensions from file system
		[$width, $height] = $this->imageDimensions->detect($node);

		$el = new Element('img');
		$this->decorateElement($node->modifier, $el);
		$el->attrs = [
			'src' => Helpers::prependRoot($node->url, $this->config->imageBaseUrl),
			'alt' => $node->alt ?? '',
		] + $el->attrs;
		$this->applyFloat($el, $node->float);

		// dimensions
		$el->attrs['width'] = $width;
		$el->attrs['height'] = $height;

		return $el;
	}


	private function renderLink(Nodes\LinkNode $node): Element
	{
		// Check URL scheme (security - filter dangerous schemes like javascript:)
		$rawUrl = $node->url ?? '';
		if (!$this->urlPolicy->isLinkAllowed($rawUrl)) {
			// URL fails scheme filter, return just the content without link
			return $this->wrapChildren($this->renderNodes($node->content->children));
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

		$rawUrl = Helpers::normalizeWww($rawUrl);

		// a link to the image itself is relative to the images
		$root = $node->isImageLink ? $this->config->imageBaseUrl : $this->config->linkBaseUrl;
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
		$tags = $this->config->phraseTags;
		if ($node->type === Nodes\PhraseType::StrongEmphasis) {
			$el = new Element($tags[Nodes\PhraseType::Strong->value] ?? 'strong');
			$this->decorateElement($node->modifier, $el);
			$inner = $el->create($tags[Nodes\PhraseType::Emphasis->value] ?? 'em');
			$inner->children = $this->renderNodes($node->content->children);
			return $el;
		}

		$el = new Element($tags[$node->type->value] ?? 'span');
		$this->decorateElement($node->modifier, $el);
		$el->children = $this->renderNodes($node->content->children);
		return $el;
	}


	private function renderInlineCode(Nodes\InlineCodeNode $node): Element
	{
		$el = new Element($this->config->phraseTags['code'] ?? 'code');
		$this->decorateElement($node->modifier, $el);
		// Only escape <, >, & - not quotes (ENT_NOQUOTES)
		$el->children = [$this->protect(htmlspecialchars($node->code, ENT_NOQUOTES | ENT_HTML5, 'UTF-8'), self::ContentTextual)];
		return $el;
	}


	private function renderEmoticon(Nodes\EmoticonNode $node): Element|string
	{
		$emoji = $node->resolved ?? $node->emoticon;

		return $this->config->emoticonClass
			? new Element('span', ['class' => $this->config->emoticonClass])->setText($emoji)
			: $emoji;
	}


	/********************* autolink node renderers ****************d*g**/


	private function renderUrl(Nodes\UrlNode $node): Element|string
	{
		$url = Helpers::normalizeWww($node->url);
		if (!$this->urlPolicy->isLinkAllowed($url)) {
			return $node->url;
		}

		$el = new Element('a', ['href' => $url]);
		if ($this->noFollow && str_contains($url, '//')) {
			$el->attrs['rel'] = 'nofollow';
		}

		// Protect URL text from typography/longwords processing
		$textualUrl = $this->config->shortenUrls ? Helpers::shortenUrl($node->url) : $node->url;
		return $el->add($this->protect(htmlspecialchars($textualUrl, ENT_NOQUOTES | ENT_HTML5, 'UTF-8'), self::ContentTextual));
	}


	private function renderEmail(Nodes\EmailNode $node): Element|string
	{
		$href = 'mailto:' . $node->email;
		$text = $this->config->obfuscateEmail
			? $this->protect(
				str_replace('@', '&#64;<!-- -->', htmlspecialchars($node->email, ENT_NOQUOTES | ENT_HTML5, 'UTF-8')),
				self::ContentTextual,
			)
			: $node->email;
		return $this->urlPolicy->isLinkAllowed($href)
			? new Element('a', ['href' => $href])->add($text)
			: $text;
	}


	/********************* HTML passthrough node renderers ****************d*g**/


	private function renderHtmlTag(Nodes\HtmlTagNode $node): string
	{
		$tagName = strtolower($node->name);
		$attrs = $node->attributes;

		if (!$this->htmlPolicy->isTagAcceptable($tagName, $attrs, $node->closing)) {
			return Helpers::decodeEntities($node->toSource()); // shown as text
		}

		// an accepted tag must not smuggle in attributes the whitelist does not allow
		$attrs = $this->htmlPolicy->filterUrls($this->htmlPolicy->filterAttrs($tagName, $attrs));

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

		// Value 0 = inline markup, 1 = inline replaced, not present = block
		$type = match (Schema::inlineElements()[$tagName] ?? null) {
			null => self::ContentBlock,
			1 => self::ContentReplaced,
			default => self::ContentMarkup,
		};

		if ($node->closing) {
			return $this->protect('</' . htmlspecialchars($tagName, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '>', $type);
		}

		return $this->protect(
			'<' . htmlspecialchars($tagName, ENT_QUOTES | ENT_HTML5, 'UTF-8')
			. Element::formatAttrs($attrs)
			. '>',
			$type,
		);
	}


	private function renderHtmlComment(Nodes\HtmlCommentNode $node): string
	{
		if (!$this->config->passHtmlComments) {
			return '';
		}

		// Sanitize comment content (security: prevent nested comments)
		$content = preg_replace('~-{2,}~', ' - ', $node->text);
		$content = trim($content, '-');
		$content = str_starts_with($content, '>') ? ' ' . $content : $content; // <!--> would end the comment
		return $this->protect('<!--' . $content . '-->', self::ContentMarkup);
	}


	/********************* directive node renderers ****************d*g**/


	private function renderDirective(Nodes\DirectiveNode $node): string
	{
		// {{texy: ...}} directives are normally consumed by DirectiveModule::processDirectives()
		// in the transform phase; swallow any that reach the renderer (hand-built AST)
		if ($node->name === 'texy' && $node->args) {
			return '';
		}

		// Unknown directives - preserve original text (entities decoded like any display text)
		return Helpers::decodeEntities('{{' . $node->text . '}}');
	}


	/********************* utility methods ****************d*g**/


	/**
	 * Analyze paragraph content to determine what types of nodes it contains.
	 * @param  array<Nodes\InlineNode|Nodes\BlockNode>  $content
	 * @return array{hasText: bool, hasReplaced: bool, hasMarkup: bool, hasOther: bool}
	 */
	private function analyzeContent(array $content): array
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
				$inlineType = Schema::inlineElements()[strtolower($node->name)] ?? null;
				match ($inlineType) {
					1 => $hasReplaced = true,    // replaced element (img, br, input, ...)
					0 => $hasMarkup = true,      // inline markup (span, a, strong, ...)
					null => $hasMarkup = true,   // block element
				};

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
	private function wrapChildren(array $children): Element
	{
		$el = new Element;
		$el->children = $children;
		return $el;
	}


	protected function renderUnknown(Node $node): Element
	{
		return $this->wrapChildren($this->renderChildrenOf($node));
	}


	/**
	 * Create paragraph for non-text content (images only).
	 * @param list<Element|string> $children
	 */
	private function createNontextParagraph(array $children, ?Modifier $modifier): Element
	{
		$el = new Element($this->config->imageParagraphTag);
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


	/**
	 * Applies the float of an image or a figure: its class from $floatClasses, or the inline style.
	 * @param  'left'|'right'|null  $float
	 */
	public function applyFloat(Element $el, ?string $float): void
	{
		if ($float !== null) {
			$this->decorator->applyFloat($el, $float);
		}
	}
}
