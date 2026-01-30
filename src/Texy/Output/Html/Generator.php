<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Output\Html;

use Texy\Helpers;
use Texy\Modifier;
use Texy\Node;
use Texy\Nodes;
use Texy\Regexp;
use Texy\Syntax;
use Texy\Texy;
use function array_flip, base_convert, count, explode, htmlspecialchars, implode, is_array, ltrim, settype, str_contains, str_replace, strncasecmp, strtr;


/**
 * Generates HTML output from AST.
 */
class Generator
{
	// types of protection marks
	public const
		ContentMarkup = "\x17",
		ContentReplaced = "\x16",
		ContentTextual = "\x15",
		ContentBlock = "\x14";

	/** @var array<string, ?string>  CSS classes for align modifiers */
	public array $alignClasses = [
		'left' => null,
		'right' => null,
		'center' => null,
		'justify' => null,
		'top' => null,
		'middle' => null,
		'bottom' => null,
	];

	/** Do obfuscate e-mail addresses? */
	public bool $obfuscateEmail = true;

	/** Element for image-only paragraphs */
	public string|Element $nontextParagraph = 'div';

	// AutolinkModule options
	/** Shorten URLs to more readable form? */
	public bool $shortenUrls = true;

	/** CSS class for emoticons */
	public ?string $emoticonClass = null;

	// FigureModule options
	/** Figure wrapper tag name */
	public string $figureTagName = 'div';

	/** Non-floated figure CSS class */
	public ?string $figureClass = 'figure';

	/** Left-floated figure CSS class */
	public ?string $figureLeftClass = null;

	/** Right-floated figure CSS class */
	public ?string $figureRightClass = null;

	// HorizontalRuleModule options
	/** @var array<string, ?string>  default CSS class for HR types */
	public array $horizontalRuleClasses = [
		'-' => null,
		'*' => null,
	];

	// HtmlModule options
	/** Pass HTML comments to output? */
	public bool $passHtmlComments = true;

	// ImageModule options
	/** Left-floated images CSS class */
	public ?string $imageLeftClass = null;

	/** Right-floated images CSS class */
	public ?string $imageRightClass = null;

	/** Root path for image URLs */
	public ?string $imageRoot = 'images/';

	/** File system root for image dimension detection */
	public ?string $imageFileRoot = null;

	// LinkModule options
	/** Always use rel="nofollow" for absolute links? */
	public bool $linkNoFollow = false;

	/** Root path for link URLs */
	public ?string $linkRoot = null;

	// PhraseModule options
	/** @var array<string, string> syntax → HTML tag mapping */
	public array $phraseTags = [
		Syntax::Strong => 'strong',
		Syntax::Emphasis => 'em',
		Syntax::EmphasisSingleAsterisk => 'em',
		Syntax::EmphasisSingleAsterisk2 => 'em',
		Syntax::Inserted => 'ins',
		Syntax::Deleted => 'del',
		Syntax::Superscript => 'sup',
		Syntax::SuperscriptShort => 'sup',
		Syntax::Subscript => 'sub',
		Syntax::SubscriptShort => 'sub',
		Syntax::SpanQuotes => 'span',
		Syntax::SpanTilde => 'span',
		Syntax::AbbreviationQuotes => 'abbr',
		Syntax::Abbreviation => 'abbr',
		Syntax::Code => 'code',
		Syntax::Quote => 'q',
		Syntax::QuickLink => 'a',
	];

	/** @var bool|array<string, bool|array<int, string>>  Allowed HTML tags */
	public bool|array $allowedTags;

	/** @var array<class-string<Node>, \Closure(Node, self): (Element|string)> */
	private array $handlers = [];

	private Support $support;

	/** @var array<string, string>  Protection markup table */
	private array $marks = [];


	public function __construct(
		private Texy $texy,
	) {
		$this->support = new Support($texy, $this);
		$this->initAllowedTags();

		$this->handlers = [
			// Core nodes
			Nodes\DocumentNode::class => fn(Nodes\DocumentNode $n, self $g) => $g->renderDocument($n),
			Nodes\TextNode::class => fn(Nodes\TextNode $n) => $n->content,
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
			Nodes\RawTextNode::class => fn(Nodes\RawTextNode $n) => htmlspecialchars($n->content, ENT_NOQUOTES, 'UTF-8'),
			Nodes\AnnotationNode::class => fn(Nodes\AnnotationNode $n) => (new Element('abbr', ['title' => $n->annotation]))->setText($n->content),
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
	}


	/**
	 * Register a handler for a node class.
	 * Return null to delegate to previous handler.
	 * @param \Closure(Node, self, ?\Closure): (Element|string|null) $handler
	 */
	public function registerHandler(\Closure $handler): void
	{
		$nodeClass = (string) (new \ReflectionFunction($handler))->getParameters()[0]->getType();
		$previous = $this->handlers[$nodeClass] ?? null;
		$this->handlers[$nodeClass] = static function (Node $node, self $gen) use ($handler, $previous): Element|string {
			$result = $handler($node, $gen, $previous);
			if ($result !== null) {
				return $result;
			}
			if ($previous === null) {
				throw new \LogicException('No handler for node class ' . $node::class);
			}
			return $previous($node, $gen);
		};
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

		// line-postprocessing (typography, long words)
		$blocks = explode(self::ContentBlock, $s);
		foreach ($this->texy->postHandlers as $name => $handler) {
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
		$s = $this->texy->htmlOutputModule->format($s);

		// unfreeze spaces
		$s = Helpers::unfreezeSpaces($s);
		$s = ltrim($s, "\n");

		return $s;
	}


	/**
	 * Converts internal string representation to final HTML code.
	 */
	final public function stringToText(string $s): string
	{
		// TODO

		// remove tags
		$s = Regexp::replace($s, '~<(script|style)(.*)</\1>~Uis', '');
		$s = strip_tags($s);
		$s = Regexp::replace($s, '~\n\s*\n\s*\n[\n\s]*\n~', "\n\n");

		// entities -> chars
		$s = Helpers::unescapeHtml($s);

		// convert nbsp to normal space and remove shy
		$s = strtr($s, [
			"\u{AD}" => '', // shy
			"\u{A0}" => ' ', // nbsp
		]);

		return $s;
	}


	private function initAllowedTags(): void
	{
		// accept all valid HTML tags and attributes by default
		$this->allowedTags = [];
		foreach (Element::$inlineElements as $tag => $_) {
			$this->allowedTags[$tag] = Texy::ALL;
		}
		foreach (Element::$emptyElements as $tag => $_) {
			$this->allowedTags[$tag] = Texy::ALL;
		}
		// common block elements
		foreach (['div', 'p', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'table', 'thead', 'tbody',
			'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col', 'blockquote',
			'pre', 'figure', 'figcaption', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
			'header', 'footer', 'main', 'article', 'section', 'nav', 'aside', 'address',
			'form', 'fieldset', 'legend'] as $tag) {
			$this->allowedTags[$tag] = Texy::ALL;
		}
	}


	/**
	 * Render node to HTML Element or string.
	 */
	public function renderNode(Node $node): Element|string
	{
		$handler = $this->handlers[$node::class] ?? null;
		return $handler($node, $this);
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
		$parsed = $this->serialize($this->renderNodes($htmlParser->parse(null, $content)->children));
		$parsed = Helpers::unescapeHtml($parsed);
		$parsed = htmlspecialchars($parsed, ENT_NOQUOTES, 'UTF-8');
		return $this->unprotect($parsed);
	}


	/**
	 * Serialize HtmlElement/string array to string.
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
		$inlineType = Element::$inlineElements[$el->name ?? ''] ?? null;
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
		return $this->serialize($this->renderNodes($node->content->children), '');
	}


	/********************* block node renderers ****************d*g**/


	private function renderParagraph(Nodes\ParagraphNode $node): Element
	{
		$children = $this->renderNodes($node->content->children);

		// Block HTML content - skip <p> wrapper entirely
		if ($node->blockHtml) {
			return $this->support->wrapChildren($children);
		}

		$info = $this->support->analyzeContent($node->content->children);

		// Only markup (HTML tags/comments) without text → no <p> wrapper
		if (!$info['hasText'] && !$info['hasOther'] && $info['hasMarkup'] && $node->modifier === null) {
			return $this->support->wrapChildren($children);
		}

		// Only replaced content (images) → use nontextParagraph
		if (!$info['hasText'] && !$info['hasOther'] && $info['hasReplaced']) {
			return $this->support->createNontextParagraph($children, $node->modifier);
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
			$parsed = $context->parseBlock($node->content);
			$content = $this->serialize($this->renderNodes($parsed->children), "\n");
			$html = Helpers::unescapeHtml($content);
			$html = htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8');
			$html = $this->unprotect($html);
			$html = $this->texy->htmlOutputModule->format($html);
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
			$content = $node->content;
			if ($content === '') {
				return "\n";
			}

			$parsed = $this->renderHtmlPassthrough($content);
			return $this->protect($parsed . ' ', self::ContentBlock);
		}

		// block/text - plain text with <br> for newlines (no wrapper)
		if ($node->type === 'text') {
			$content = htmlspecialchars($node->content, ENT_NOQUOTES, 'UTF-8');
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
			$parsed = $this->renderHtmlPassthrough($node->content);
			$content = $this->protect($parsed, self::ContentBlock);
			$el->setText($content);
			return $el;
		}

		$content = htmlspecialchars($node->content, ENT_NOQUOTES, 'UTF-8');
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
		$class = $this->horizontalRuleClasses[$node->type] ?? null;
		if ($class && empty($node->modifier?->classes[$class])) {
			$el->attrs['class'] = (array) ($el->attrs['class'] ?? []);
			$el->attrs['class'][] = $class;
		}

		return $el;
	}


	private function renderFigure(Nodes\FigureNode $node): Element
	{
		$el = new Element($this->figureTagName);

		// Get the actual ImageNode (might be wrapped in LinkNode)
		$image = $node->image;
		$imageNode = $image instanceof Nodes\LinkNode && isset($image->content->children[0]) && $image->content->children[0] instanceof Nodes\ImageNode
			? $image->content->children[0]
			: $image;

		// Extract alignment from ImageNode - we'll apply it to the figure wrapper instead
		$hAlign = $imageNode instanceof Nodes\ImageNode ? $imageNode->modifier?->hAlign : null;
		if ($imageNode instanceof Nodes\ImageNode && $imageNode->modifier) {
			$imageNode->modifier->hAlign = null; // Clear so generator won't add it to <img>
		}

		// Build image via generator - this allows custom ImageNode handlers to work
		$el->children = $this->renderNodes([$image]);

		// Caption
		if ($node->caption !== null) {
			$el->create($this->figureTagName === 'figure' ? 'figcaption' : 'p')
				->children = $this->renderNodes($node->caption->children);
		}

		// Modifier classes/styles
		$this->decorateElement($node->modifier, $el);

		// Figure class based on alignment (extracted from ImageNode above)
		$class = $this->figureClass;
		if ($hAlign) {
			$hAlignClass = match ($hAlign) {
				'left' => $this->figureLeftClass,
				'right' => $this->figureRightClass,
				default => null,
			};
			if ($hAlignClass) {
				$class = $hAlignClass;
			} elseif (empty($this->alignClasses[$hAlign])) {
				$el->attrs['style'] = (array) ($el->attrs['style'] ?? []);
				$el->attrs['style']['float'] = $hAlign;
			} else {
				$class .= '-' . $this->alignClasses[$hAlign];
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
			$el->attrs['style']['list-style-type'] = $style;
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
		$this->detectImageDimensions($node);

		$el = new Element('img');
		$mod = $node->modifier;

		// Extract and clear title/hAlign before decorate
		$alt = $mod?->title;
		$hAlign = $mod?->hAlign;
		if ($mod) {
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
		$el->attrs['src'] = $node->url !== null ? Helpers::prependRoot($node->url, $this->imageRoot) : null;

		// alt: from title or empty (if not set by custom attrs)
		if (!$hasCustomAlt && !isset($el->attrs['alt'])) {
			$el->attrs['alt'] = $alt !== null
				? $this->texy->typographyModule->postLine($alt)
				: '';
		}

		// hAlign → float class or style
		if ($hAlign) {
			$class = match ($hAlign) {
				'left' => $this->imageLeftClass,
				'right' => $this->imageRightClass,
				default => null,
			};
			if ($class) {
				$el->attrs['class'] = (array) ($el->attrs['class'] ?? []);
				$el->attrs['class'][] = $class;
			} elseif (!empty($this->alignClasses[$hAlign])) {
				$el->attrs['class'] = (array) ($el->attrs['class'] ?? []);
				$el->attrs['class'][] = $this->alignClasses[$hAlign];
			} else {
				$el->attrs['style'] = (array) ($el->attrs['style'] ?? []);
				$el->attrs['style']['float'] = $hAlign;
			}
		}

		// dimensions
		$el->attrs['width'] = $node->width;
		$el->attrs['height'] = $node->height;

		return $el;
	}


	/**
	 * Detects image dimensions from file system.
	 */
	private function detectImageDimensions(Nodes\ImageNode $node): void
	{
		if ($node->url === null || !Helpers::isRelative($node->url) || str_contains($node->url, '..')) {
			return;
		}

		$fileRoot = $this->imageFileRoot;
		if ($fileRoot === null) {
			return;
		}

		$file = rtrim($fileRoot, '/\\') . '/' . $node->url;
		if (!@is_file($file) || !($size = @getimagesize($file))) { // intentionally @
			return;
		}

		if ($node->width === null && $node->height === null) {
			$node->width = $size[0];
			$node->height = $size[1];
		} elseif ($node->width !== null && $node->height === null) {
			$node->height = (int) round($size[1] / $size[0] * $node->width);
		} elseif ($node->height !== null && $node->width === null) {
			$node->width = (int) round($size[0] / $size[1] * $node->height);
		}
	}


	private function renderLink(Nodes\LinkNode $node): Element|string
	{
		// Check URL scheme (security - filter dangerous schemes like javascript:)
		$rawUrl = $node->url ?? '';
		if (!$this->texy->checkURL($rawUrl, Texy::FILTER_ANCHOR)) {
			// URL fails scheme filter, return just the content without link
			return $this->serialize($this->renderNodes($node->content->children));
		}

		$el = new Element('a');

		// Handle nofollow class
		$nofollow = false;
		if ($node->modifier && isset($node->modifier->classes['nofollow'])) {
			$nofollow = true;
			unset($node->modifier->classes['nofollow']);
		}

		// Apply modifier (title, class, id, style, etc.) before href
		$el->attrs['href'] = null; // trick - reserve position at front
		$this->decorateElement($node->modifier, $el);

		// Normalize www. to http://
		if (strncasecmp($rawUrl, 'www.', 4) === 0) {
			$rawUrl = 'http://' . $rawUrl;
		}

		// Prepend root to relative URLs
		// Use imageModule.root for image links, linkModule.root otherwise
		$root = $node->isImageLink ? $this->imageRoot : $this->linkRoot;
		$el->attrs['href'] = Helpers::prependRoot($rawUrl, $root);

		// rel="nofollow"
		if ($nofollow || ($this->linkNoFollow && str_contains($el->attrs['href'], '//'))) {
			$el->attrs['rel'] = 'nofollow';
		}

		// Add content
		$el->children = $this->renderNodes($node->content->children);

		return $el;
	}


	private function renderPhrase(Nodes\PhraseNode $node): Element
	{
		$tag = $this->phraseTags[$node->type] ?? 'span';

		if ($node->type === Syntax::StrongEmphasis) {
			$el = new Element($this->phraseTags[Syntax::Strong] ?? 'strong');
			$this->decorateElement($node->modifier, $el);
			$inner = $el->create($this->phraseTags[Syntax::Emphasis] ?? 'em');
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
		return $this->emoticonClass
			? (new Element('span', ['class' => $this->emoticonClass]))->setText($emoji)
			: $emoji;
	}


	/********************* autolink node renderers ****************d*g**/


	private function renderUrl(Nodes\UrlNode $node): Element
	{
		$url = strncasecmp($node->url, 'www.', 4) === 0
			? 'http://' . $node->url
			: $node->url;

		$el = new Element('a', ['href' => $url]);
		if ($this->linkNoFollow && str_contains($url, '//')) {
			$el->attrs['rel'] = 'nofollow';
		}

		// Protect URL text from typography/longwords processing
		$textualUrl = $this->shortenUrls ? Helpers::shortenUrl($node->url) : $node->url;
		return $el->add($this->protect($textualUrl, self::ContentTextual));
	}


	private function renderEmail(Nodes\EmailNode $node): Element
	{
		$el = new Element('a', ['href' => 'mailto:' . $node->email]);
		$text = $this->obfuscateEmail
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
		$allowedTags = $this->allowedTags;
		if (!$allowedTags) {
			// All tags are disabled
			return $this->support->escapeHtmlTag($node);
		}
		if (is_array($allowedTags) && !isset($allowedTags[$tagName])) {
			// Tag not in allowed list
			return $this->support->escapeHtmlTag($node);
		}

		// Validate tag - reject if validation fails
		$validation = $this->support->validateHtmlTag($tagName, $attrs, $node->closing);
		if ($validation === false || $validation === 'drop') {
			// Invalid tag - escape as text (shows original tag in output)
			return $this->support->escapeHtmlTag($node);
		}

		// Add rel="nofollow" for external links when linkNoFollow is enabled
		if ($tagName === 'a' && !$node->closing && isset($attrs['href']) && is_string($attrs['href'])) {
			if ($this->linkNoFollow && str_contains($attrs['href'], '//')) {
				$existingRel = isset($attrs['rel']) && is_string($attrs['rel']) ? $attrs['rel'] : '';
				$relParts = $existingRel ? explode(' ', $existingRel) : [];
				if (!in_array('nofollow', $relParts, true)) {
					$relParts[] = 'nofollow';
				}
				$attrs['rel'] = implode(' ', $relParts);
			}
		}

		// Determine content type based on HtmlElement::$inlineElements
		// Value 0 = inline markup, 1 = inline replaced, not present = block
		$inlineType = Element::$inlineElements[$tagName] ?? null;
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
		if (!$this->passHtmlComments) {
			return '';
		}

		// Sanitize comment content (security: prevent nested comments)
		$content = preg_replace('~-{2,}~', ' - ', $node->content);
		$content = trim($content, '-');
		return $this->protect('<!--' . $content . '-->', self::ContentMarkup);
	}


	/********************* directive node renderers ****************d*g**/


	private function renderDirective(Nodes\DirectiveNode $node): string
	{
		$parsed = $node->parseContent();

		// Handle special directives
		if ($parsed['name'] === 'texy' && $parsed['args']) {
			switch ($parsed['args'][0] ?? null) {
				case 'nofollow':
					$this->linkNoFollow = true;
					break;
			}
			// texy directive with args returns empty
			return '';
		}

		// Unknown directives - preserve original text
		return '{{' . $node->content . '}}';
	}


	/********************* utility methods ****************d*g**/


	/**
	 * Decorate element with modifier's classes, styles, and attributes.
	 */
	public function decorateElement(?Modifier $modifier, Element $el): void
	{
		if ($modifier === null) {
			return;
		}

		$this->decorateAttrs($modifier, $el);
		$this->decorateClasses($modifier, $el->attrs);
		$this->decorateStyles($modifier, $el->attrs);
		$this->decorateAligns($modifier, $el->attrs);
	}


	private function decorateAttrs(Modifier $modifier, Element $el): void
	{
		$attrs = &$el->attrs;
		$name = $el->name ?? '';

		if (!$modifier->attrs) {
		} elseif ($this->allowedTags === Texy::ALL) {
			$attrs = $modifier->attrs;

		} elseif (is_array($this->allowedTags)) {
			$tmp = $this->allowedTags[$name] ?? [];

			if ($tmp === Texy::ALL) {
				$attrs = $modifier->attrs;

			} elseif (is_array($tmp)) {
				$attrs = array_flip($tmp);
				foreach ($modifier->attrs as $key => $value) {
					if (isset($attrs[$key])) {
						$attrs[$key] = $value;
					}
				}
			}
		}

		if ($modifier->title !== null) {
			$attrs['title'] = $this->texy->typographyModule->postLine($modifier->title);
		}
	}


	/** @param  array<string, mixed>  $attrs */
	private function decorateClasses(Modifier $modifier, array &$attrs): void
	{
		if ($modifier->classes || $modifier->id !== null) {
			[$allowedClasses] = $this->texy->getAllowedProps();
			settype($attrs['class'], 'array');
			if ($allowedClasses === Texy::ALL) {
				foreach ($modifier->classes as $value => $foo) {
					$attrs['class'][] = $value;
				}

				$attrs['id'] = $modifier->id;
			} elseif (is_array($allowedClasses)) {
				foreach ($modifier->classes as $value => $foo) {
					if (isset($allowedClasses[$value])) {
						$attrs['class'][] = $value;
					}
				}

				if (isset($allowedClasses['#' . $modifier->id])) {
					$attrs['id'] = $modifier->id;
				}
			}
		}
	}


	/** @param  array<string, mixed>  $attrs */
	private function decorateStyles(Modifier $modifier, array &$attrs): void
	{
		if ($modifier->styles) {
			[, $allowedStyles] = $this->texy->getAllowedProps();
			settype($attrs['style'], 'array');
			if ($allowedStyles === Texy::ALL) {
				foreach ($modifier->styles as $prop => $value) {
					$attrs['style'][$prop] = $value;
				}
			} elseif (is_array($allowedStyles)) {
				foreach ($modifier->styles as $prop => $value) {
					if (isset($allowedStyles[$prop])) {
						$attrs['style'][$prop] = $value;
					}
				}
			}
		}
	}


	/** @param  array<string, mixed>  $attrs */
	private function decorateAligns(Modifier $modifier, array &$attrs): void
	{
		if ($modifier->hAlign) {
			$class = $this->alignClasses[$modifier->hAlign] ?? null;
			if ($class) {
				settype($attrs['class'], 'array');
				$attrs['class'][] = $class;
			} else {
				settype($attrs['style'], 'array');
				$attrs['style']['text-align'] = $modifier->hAlign;
			}
		}

		if ($modifier->vAlign) {
			$class = $this->alignClasses[$modifier->vAlign] ?? null;
			if ($class) {
				settype($attrs['class'], 'array');
				$attrs['class'][] = $class;
			} else {
				settype($attrs['style'], 'array');
				$attrs['style']['vertical-align'] = $modifier->vAlign;
			}
		}
	}
}
