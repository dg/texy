<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\{Helpers, HtmlPolicy, Modifier, Node, Nodes, Syntax, UrlPolicy};
use Texy\Output\NodeRenderer;
use function is_array, is_string;
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
	private ElementDecorator $decorator;

	private bool $noFollow;

	private bool $plainBreaks = false;

	private ImageDimensions $imageDimensions;


	public function __construct(
		public readonly Config $config,
		private HtmlPolicy $htmlPolicy,
		private UrlPolicy $urlPolicy,
	) {
		$this->noFollow = $config->linkNoFollow;
		$this->imageDimensions = new ImageDimensions($config);
		$this->decorator = new ElementDecorator($htmlPolicy, $urlPolicy, $config);

		$this->handlers = [
			// Core nodes
			Nodes\DocumentNode::class => fn(Nodes\DocumentNode $n, self $g) => $g->renderDocument($n),
			Nodes\TextNode::class => fn(Nodes\TextNode $n) => $n->text,
			Nodes\ContentNode::class => fn(Nodes\ContentNode $n, self $g) => $g->wrapChildren($g->renderNodes($n->children)),

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
			Nodes\LineBreakNode::class => fn(Nodes\LineBreakNode $n, self $g) => $g->plainBreaks ? "\n" : new Raw('<br>'),

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

		foreach ($config->getRenderers() as $renderer) {
			$this->addRenderer($renderer);
		}
	}


	/**
	 * Render document AST to final HTML string. Per-document facts are read
	 * from the document itself: the {{texy: nofollow}} directive.
	 */
	public function render(Nodes\DocumentNode $document): string
	{
		$this->noFollow = $this->config->linkNoFollow || !empty($document->meta['nofollow']);
		return $this->finalize($this->renderNode($document));
	}


	/**
	 * Render children as array (for Element->children).
	 * @param array<Node> $content
	 * @return list<Element|Raw|string>
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
	 * @param array<Element|Raw|string> $children
	 */
	private function renderedToNothing(array $children): bool
	{
		return array_all($children, fn($child) => is_string($child) && trim($child) === '');
	}


	/**
	 * Turns the rendered document into the final HTML string: walks the
	 * Element tree straight into the well-forming engine.
	 */
	private function finalize(Element|Raw|string $root): string
	{
		$wellFormer = new WellFormer($this->config);
		$wellFormer->feed($root);
		return ltrim($wellFormer->finish(), "\n");
	}


	/**
	 * Well-forms a standalone fragment (texysource).
	 * @param array<Element|Raw|string> $content
	 */
	private function finalizeFragment(array $content): string
	{
		$wellFormer = new WellFormer($this->config);
		$first = true;
		foreach ($content as $child) {
			if (!$first) {
				$wellFormer->text("\n");
			}
			$first = false;
			$wellFormer->feed($child);
		}

		return trim($wellFormer->finish());
	}


	/********************* core node renderers ****************d*g**/


	private function renderDocument(Nodes\DocumentNode $node): Element
	{
		return $this->wrapChildren($this->renderNodes($node->content->children));
	}


	/********************* block node renderers ****************d*g**/


	private function renderParagraph(Nodes\ParagraphNode $node): Element
	{
		// Block HTML content - skip <p> wrapper entirely. The parse-time flag is
		// stale when a pass turned the block tags into something else, so its
		// own predicate is re-checked over the transformed content: paragraphs
		// degraded to visible text get a normal <p> again.
		if ($node->blockHtml && $this->containsBlockPassthrough($node->content->children)) {
			return $this->renderWithoutBreaks($node->content->children);
		}

		$info = $this->analyzeContent($node->content->children);

		// Only markup (HTML tags/comments) without text → no <p> wrapper. Replaced content
		// with a hard line break gets one for its <br>, unless it stands beside a block tag.
		if (
			!$info['hasText'] && !$info['hasOther'] && $info['hasMarkup'] && $node->modifier === null
			&& ($info['hasBlock'] || !$info['hasReplaced'] || !$info['hasBreak'])
		) {
			return $this->renderWithoutBreaks($node->content->children);
		}

		$children = $this->renderNodes($node->content->children);

		// Whole content rendered to nothing (consumed directives, comments) → no paragraph at all
		if ($node->modifier === null && $this->renderedToNothing($children)) {
			return $this->wrapChildren([]);
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
		if ($node->id !== null && $node->modifier?->id === null) {
			$el->attrs['id'] = $node->id; // generated, so not subject to the modifier policy
		}

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
		$el->create('code')->add(new Raw(htmlspecialchars($node->code, ENT_NOQUOTES, 'UTF-8')));
		return $el;
	}


	/**
	 * Preformatted text passes its HTML tags and decoded entities through.
	 */
	private function renderPreformatted(Nodes\PreformattedNode $node): Element
	{
		$el = new Element('pre');
		$this->decorateElement($node->modifier, $el);
		$el->children = [$this->wrapChildren($this->renderNodes($node->content->children))];
		return $el;
	}


	private function renderHtmlBlock(Nodes\HtmlBlockNode $node): Element|string
	{
		return $node->code === ''
			? "\n"
			: $this->wrapChildren($this->renderNodes($node->content->children))->add(' ');
	}


	/**
	 * Plain text with <br> for newlines, no wrapper.
	 */
	private function renderTextBlock(Nodes\TextBlockNode $node): Raw
	{
		$content = htmlspecialchars($node->code, ENT_NOQUOTES, 'UTF-8');
		return new Raw(str_replace("\n", '<br>', $content) . ' ');
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
		$el->create('code')->add(new Raw(htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8')));
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
		$el->children = $this->renderItemContent($node->content->children);
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
	 * @return list<Element|Raw|string>
	 */
	private function renderItemContent(array $content): array
	{
		$first = $content[0] ?? null;
		$rest = array_slice($content, 1);

		$opening = match (true) {
			$first instanceof Nodes\ParagraphNode && $first->modifier === null => $this->renderParagraph($first)->children,
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
		$tag = $this->config->phraseTags[$node->type] ?? 'span';

		if ($node->type === Syntax::StrongEmphasis) {
			$el = new Element($this->config->phraseTags[Syntax::Strong] ?? 'strong');
			$this->decorateElement($node->modifier, $el);
			$inner = $el->create($this->config->phraseTags[Syntax::Emphasis] ?? 'em');
			$inner->children = $this->renderNodes($node->content->children);
			return $el;
		}

		$el = new Element($tag);
		$this->decorateElement($node->modifier, $el);
		$el->children = $this->renderNodes($node->content->children);
		return $el;
	}


	private function renderInlineCode(Nodes\InlineCodeNode $node): Element
	{
		$el = new Element($this->config->phraseTags[Syntax::Code] ?? 'code');
		$this->decorateElement($node->modifier, $el);
		// Only escape <, >, & - not quotes (ENT_NOQUOTES)
		$el->children = [new Raw(htmlspecialchars($node->code, ENT_NOQUOTES | ENT_HTML5, 'UTF-8'))];
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


	private function renderUrl(Nodes\UrlNode $node): Element
	{
		$url = Helpers::normalizeWww($node->url);

		$el = new Element('a', ['href' => $url]);
		if ($this->noFollow && str_contains($url, '//')) {
			$el->attrs['rel'] = 'nofollow';
		}

		// Protect URL text from typography/longwords processing
		$textualUrl = $this->config->shortenUrls ? Helpers::shortenUrl($node->url) : $node->url;
		return $el->add(new Raw($textualUrl));
	}


	private function renderEmail(Nodes\EmailNode $node): Element
	{
		$el = new Element('a', ['href' => 'mailto:' . $node->email]);
		$text = $this->config->obfuscateEmail
			? new Raw(str_replace('@', '&#64;<!-- -->', $node->email))
			: $node->email;
		return $el->add($text);
	}


	/********************* HTML passthrough node renderers ****************d*g**/


	private function renderHtmlTag(Nodes\HtmlTagNode $node): Raw|string
	{
		$tagName = strtolower($node->name);
		$attrs = $node->attributes;

		// Check if tag is allowed
		if (!$this->htmlPolicy->isTagAllowed($tagName)) {
			return Helpers::decodeEntities($this->htmlPolicy->reconstructTag($node)); // shown as text
		}

		// Validate tag - reject if validation fails
		$validation = $this->htmlPolicy->validateTag($tagName, $attrs, $node->closing);
		if ($validation === false || $validation === 'drop') {
			// Invalid tag - shown as text in the output
			return Helpers::decodeEntities($this->htmlPolicy->reconstructTag($node));
		}

		// defense in depth: HtmlSanitizePass has already done this, but a hand-built
		// AST or a policy tightened after parsing must not slip attributes through
		$attrs = $this->htmlPolicy->filterAttrs($tagName, $attrs);

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

		if ($node->closing) {
			return new Raw('</' . htmlspecialchars($tagName, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '>');
		}

		return new Raw(
			'<' . htmlspecialchars($tagName, ENT_QUOTES | ENT_HTML5, 'UTF-8')
			. Element::formatAttrs($attrs)
			. '>',
		);
	}


	private function renderHtmlComment(Nodes\HtmlCommentNode $node): Raw|string
	{
		if (!$this->config->passHtmlComments) {
			return '';
		}

		// Sanitize comment content (security: prevent nested comments)
		$content = preg_replace('~-{2,}~', ' - ', $node->text);
		$content = trim($content, '-');
		return new Raw('<!--' . $content . '-->');
	}


	/**
	 * Renders paired passthrough element as open tag + children + close tag,
	 * delegating to renderHtmlTag() so sanitization and escaping behave
	 * exactly like for unpaired tags.
	 */
	private function renderHtmlElement(Nodes\HtmlElementNode $node): Element
	{
		$open = new Nodes\HtmlTagNode($node->name, $node->attributes, range: $node->range, source: $node->source);
		$close = $node->closingTag ?? new Nodes\HtmlTagNode($node->name, closing: true);
		return $this->wrapChildren([
			$this->renderHtmlTag($open),
			...$this->renderNodes($node->content->children),
			$this->renderHtmlTag($close),
		]);
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
	 * Does the content still contain block-level passthrough markup?
	 * The same predicate ParagraphModule uses when setting the blockHtml flag,
	 * re-evaluated after transforms (a pass may have replaced the tags).
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
	 * @return array{hasText: bool, hasReplaced: bool, hasMarkup: bool, hasBlock: bool, hasBreak: bool, hasOther: bool}
	 */
	private function analyzeContent(array $content): array
	{
		$hasText = false;
		$hasReplaced = false;
		$hasMarkup = false;
		$hasBlock = false;
		$hasBreak = false;
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
					null => $hasMarkup = $hasBlock = true, // block element
				};

			} elseif ($node instanceof Nodes\HtmlElementNode) {
				$inlineType = Schema::inlineElements()[strtolower($node->name)] ?? null;
				match ($inlineType) {
					1 => $hasReplaced = true,    // replaced element (script, object, ...)
					0 => $hasMarkup = true,
					null => $hasMarkup = $hasBlock = true,
				};
				// the element's descendants count as content of the paragraph
				$inner = $this->analyzeContent($node->content->children);
				$hasText = $hasText || $inner['hasText'];
				$hasReplaced = $hasReplaced || $inner['hasReplaced'];
				$hasMarkup = $hasMarkup || $inner['hasMarkup'];
				$hasBlock = $hasBlock || $inner['hasBlock'];
				$hasBreak = $hasBreak || $inner['hasBreak'];
				$hasOther = $hasOther || $inner['hasOther'];

			} elseif ($node instanceof Nodes\LinkNode) {
				$inner = $this->analyzeContent($node->content->children);
				if ($inner['hasText'] || $inner['hasOther']) {
					$hasOther = true;
				} elseif ($inner['hasReplaced']) {
					$hasReplaced = true;
				}

			} elseif ($node instanceof Nodes\LineBreakNode) {
				$hasBreak = true;

			} else {
				$hasOther = true;
			}
		}

		return compact('hasText', 'hasReplaced', 'hasMarkup', 'hasBlock', 'hasBreak', 'hasOther');
	}


	/**
	 * Renders the content of a paragraph without a wrapper, where a hard line break is only whitespace.
	 * @param array<Node> $content
	 */
	private function renderWithoutBreaks(array $content): Element
	{
		$this->plainBreaks = true;
		try {
			return $this->wrapChildren($this->renderNodes($content));
		} finally {
			$this->plainBreaks = false;
		}
	}


	/**
	 * Wrap children in a null element (no tag wrapper).
	 * @param list<Element|Raw|string> $children
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
	 * @param list<Element|Raw|string> $children
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
