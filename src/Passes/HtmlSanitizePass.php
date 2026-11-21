<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Passes;

use Texy\{HasModifier, Helpers, HtmlPolicy, Modifier, Node, Nodes, NodeTraverser, TraverseAction};
use Texy\Nodes\{HtmlElementNode, HtmlTagNode, RawTextNode};


/**
 * Applies HtmlPolicy in the transform phase, so every output format and renderer handler gets
 * the sanitized AST: a rejected tag becomes text as written, a rejected link its content,
 * a rejected image its alternative text, and a modifier keeps only what the policy allows.
 */
final class HtmlSanitizePass
{
	public function __construct(
		private HtmlPolicy $policy,
	) {
	}


	public function process(Nodes\DocumentNode $doc): void
	{
		(new NodeTraverser)->traverse($doc, function (Node $node): ?TraverseAction {
			if ($node instanceof Nodes\ContentNode && $node->children) {
				$node->children = $this->sanitize($node->children);

			} elseif ($node instanceof Nodes\ImageNode && $node->url !== null && !$this->policy->urlPolicy->isImageAllowed($node->url)) {
				$node->url = null;

			} elseif ($node instanceof Nodes\FigureNode && $node->image instanceof Nodes\LinkNode && !$this->isLinkAllowed($node->image)) {
				$node->image = $node->image->findFirst(Nodes\ImageNode::class) ?? $node->image;
			}

			if ($node instanceof HasModifier && $node->modifier !== null) {
				$this->sanitizeModifier($node->modifier, isLink: $node instanceof Nodes\LinkNode);
			}

			return null;
		});
	}


	/**
	 * Replaces rejected tags/elements in the list by their text form, and rejected links by what they show.
	 * The children spliced in their place are sanitized here, as the traverser does not visit them again.
	 * @param  array<Nodes\InlineNode|Nodes\BlockNode>  $children
	 * @return array<Nodes\InlineNode|Nodes\BlockNode>
	 */
	private function sanitize(array $children): array
	{
		$out = [];
		foreach ($children as $child) {
			if ($child instanceof HtmlTagNode) {
				if (!$this->policy->isTagAcceptable(strtolower($child->name), $child->attributes, $child->closing)) {
					$out[] = new RawTextNode(Helpers::decodeEntities($child->toSource()), $child->range);
					continue;
				}

				// the tag stays, but only with the attributes the whitelist names
				$child->attributes = $this->policy->filterUrls($this->policy->filterAttrs(strtolower($child->name), $child->attributes));

			} elseif ($child instanceof HtmlElementNode) {
				if (!$this->policy->isTagAcceptable(strtolower($child->name), $child->attributes, closing: false)) {
					$open = new HtmlTagNode($child->name, $child->attributes, range: $child->range, source: $child->source);
					$close = $child->closingTag ?? new HtmlTagNode($child->name, closing: true);
					$out[] = new RawTextNode(Helpers::decodeEntities($open->toSource()), $child->range);
					foreach ($this->sanitize($child->content->children) as $inner) {
						$out[] = $inner;
					}
					$out[] = new RawTextNode(Helpers::decodeEntities($close->toSource()), $close->range);
					continue;
				}

				$child->attributes = $this->policy->filterUrls($this->policy->filterAttrs(strtolower($child->name), $child->attributes));

			} elseif ($child instanceof Nodes\LinkNode && !$this->isLinkAllowed($child)) {
				foreach ($this->sanitize($child->content->children) as $inner) {
					$out[] = $inner;
				}
				continue;

			} elseif ($child instanceof Nodes\UrlNode && !$this->policy->urlPolicy->isLinkAllowed(Helpers::normalizeWww($child->url))) {
				$out[] = new RawTextNode($child->url, $child->range);
				continue;
			}

			$out[] = $child;
		}

		return $out;
	}


	/** A link without a URL is an unresolved reference, which the policy has nothing to say about. */
	private function isLinkAllowed(Nodes\LinkNode $link): bool
	{
		return $link->url === null || $this->policy->urlPolicy->isLinkAllowed($link->url);
	}


	/** @param  bool  $isLink  the class nofollow of a link is no class, it means rel="nofollow" */
	private function sanitizeModifier(Modifier $modifier, bool $isLink): void
	{
		if ($modifier->id !== null && !$this->policy->isIdAllowed($modifier->id)) {
			$modifier->id = null;
		}

		$modifier->classes = array_filter(
			$modifier->classes,
			fn(string|int $class) => ($isLink && $class === 'nofollow') || $this->policy->isClassAllowed((string) $class),
			ARRAY_FILTER_USE_KEY,
		);
		$modifier->styles = array_filter($modifier->styles, $this->policy->isStyleAllowed(...), ARRAY_FILTER_USE_KEY);
		$modifier->attrs = $this->policy->filterUrls($modifier->attrs);
	}
}
