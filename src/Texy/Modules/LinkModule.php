<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\Node;
use Texy\Nodes;
use Texy\Nodes\DocumentNode;
use Texy\Nodes\LinkDefinitionNode;
use Texy\NodeTraverser;
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Patterns;
use function in_array, strlen;


/**
 * Processes link references and generates link elements.
 */
final class LinkModule extends Texy\Module
{
	/** root of relative links */
	public ?string $root = null;

	/** always use rel="nofollow" for absolute links? */
	public bool $forceNoFollow = false;

	/** @var array<string, LinkDefinitionNode> link definitions */
	private array $definitions = [];

	/** @var array<string, LinkDefinitionNode> user-defined definitions (persist across process() calls) */
	private array $userDefinitions = [];


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed['link/definition'] = true;
		$texy->addHandler('afterParse', $this->resolveReferences(...));
		$texy->htmlGenerator->registerHandler($this->solveLink(...));
		$texy->htmlGenerator->registerHandler(fn(Nodes\LinkDefinitionNode $node) => '');
	}


	public function beforeParse(string &$text): void
	{
		// [ref]: url label .(title)[class]{style}
		$this->texy->registerBlockPattern(
			$this->parseDefinition(...),
			'~^
				\[
				( [^\[\]#?*\n]{1,100} )           # reference (1)
				] : \ ++
				( \S{1,1000} )                    # URL (2)
				( [ \t] .{1,1000} )?              # optional label (3)
				' . Patterns::MODIFIER . '?       # modifier (4)
				\s*
			$~mU',
			'link/definition',
		);
	}


	/**
	 * Parses [ref]: url
	 * @param  array<?string>  $matches
	 */
	public function parseDefinition(ParseContext $context, array $matches): LinkDefinitionNode
	{
		[, $mRef, $mLink, $mLabel, $mMod] = $matches;
		if ($mMod || $mLabel) {
			trigger_error('Modifiers and label in link definitions are deprecated.', E_USER_DEPRECATED);
		}
		return new LinkDefinitionNode(
			$mRef,
			$mLink,
		);
	}


	/**
	 * Adds a user-defined link definition (persists across process() calls).
	 */
	public function addDefinition(string $name, string $url): void
	{
		$this->userDefinitions[Helpers::toLower($name)] = new LinkDefinitionNode($name, $url);
	}


	/**
	 * Resolve link references in the document.
	 * Called via afterParse handler (after ImageModule).
	 */
	public function resolveReferences(DocumentNode $doc): void
	{
		// Start with user-defined definitions
		$this->definitions = $this->userDefinitions;
		$traverser = new NodeTraverser;

		// Pass 1: Collect document definitions (overwrites user-defined)
		$traverser->traverse($doc, function (Node $node): ?int {
			if ($node instanceof LinkDefinitionNode) {
				$this->definitions[Helpers::toLower($node->identifier)] = $node;
				return NodeTraverser::DontTraverseChildren;
			}
			return null;
		});

		// Pass 2: Resolve references in LinkNode.url
		$traverser->traverse($doc, function (Node $node): ?Node {
			if ($node instanceof Nodes\LinkNode && $node->url !== null) {
				$this->resolveLinkNodeUrl($node);
			}
			return null;
		});
	}


	/**
	 * Resolve URL in LinkNode that might be [ref] or [*img*].
	 */
	private function resolveLinkNodeUrl(Nodes\LinkNode $node): void
	{
		if ($node->url === null) {
			return;
		}

		$len = strlen($node->url);

		// [*img*], [*img <], [*img >] format - resolve to image URL
		if ($len > 4 && $node->url[0] === '[' && $node->url[1] === '*'
			&& $node->url[$len - 1] === ']'
			&& in_array($node->url[$len - 2], ['*', '<', '>'], true)) {
			$imgRef = trim(substr($node->url, 2, -2));
			$imgDef = $this->texy->imageModule->getDefinition($imgRef);
			if ($imgDef !== null && $imgDef->url !== null) {
				$node->url = $imgDef->url;
			} else {
				// Image reference not found - use content as URL
				$node->url = $imgRef;
			}
			$node->isImageLink = true;
			return;
		}

		// [ref] format
		if ($len > 2 && $node->url[0] === '[' && $node->url[$len - 1] === ']') {
			$refName = substr($node->url, 1, -1);
			$def = $this->resolveDefinition($refName);
			if ($def !== null) {
				$node->url = $def->url;
				// Check if resolved URL is email
				$this->convertEmailUrl($node);
				return;
			}
			// Reference not found - use inner content as URL
			$node->url = $refName;
			// Check if it's an email
			$this->convertEmailUrl($node);
			return;
		}

		// For other URLs, resolve and check for email
		$resolved = $this->resolveUrl($node->url);
		if ($resolved !== $node->url) {
			$node->url = $resolved;
		}
		$this->convertEmailUrl($node);
	}


	/**
	 * Convert email-like URLs to mailto: scheme.
	 */
	private function convertEmailUrl(Nodes\LinkNode $node): void
	{
		if ($node->url !== null
			&& !str_contains($node->url, '/')
			&& !preg_match('~^[a-z][a-z0-9+.-]*:~i', $node->url)
			&& preg_match('~.@.~', $node->url)) { // valid email needs chars before and after @
			$node->url = 'mailto:' . $node->url;
		}
	}


	/**
	 * Resolve URL that might be [ref] or [*img*] format.
	 */
	private function resolveUrl(string $url): string
	{
		$len = strlen($url);

		// [ref] or [*img*] format
		if ($len > 2 && $url[0] === '[' && $url[$len - 1] === ']') {
			// [*img*] → image URL
			if ($url[1] === '*' && $url[$len - 2] === '*') {
				$imgRef = trim(substr($url, 2, -2));
				$imgDef = $this->texy->imageModule->getDefinition($imgRef);
				if ($imgDef !== null && $imgDef->url !== null) {
					return $imgDef->url;
				}
				// Image reference not found - return inner content as URL
				return $imgRef;
			} else {
				// [ref] → link URL
				$refName = substr($url, 1, -1);
				$def = $this->resolveDefinition($refName);
				if ($def !== null) {
					return $def->url;
				}
				// Link reference not found - return inner content as URL
				return $refName;
			}
		}

		return $url;
	}


	/**
	 * Find definition by identifier, supports #fragment and ?query.
	 */
	private function resolveDefinition(string $identifier): ?LinkDefinitionNode
	{
		$key = Helpers::toLower($identifier);

		if (isset($this->definitions[$key])) {
			return $this->definitions[$key];
		}

		// Support #fragment and ?query
		$hashPos = strpos($key, '#');
		$queryPos = strpos($key, '?');

		// Find the earliest delimiter
		$pos = null;
		if ($hashPos !== false && $queryPos !== false) {
			$pos = min($hashPos, $queryPos);
		} elseif ($hashPos !== false) {
			$pos = $hashPos;
		} elseif ($queryPos !== false) {
			$pos = $queryPos;
		}

		if ($pos !== null) {
			$baseKey = substr($key, 0, $pos);
			if (isset($this->definitions[$baseKey])) {
				$def = clone $this->definitions[$baseKey];
				$def->url .= substr($identifier, $pos);
				return $def;
			}
		}

		return null;
	}


	/**
	 * Generates HTML for LinkNode.
	 */
	public function solveLink(Nodes\LinkNode $node, Html\Generator $generator): Html\Element|string
	{
		// Check URL scheme (security - filter dangerous schemes like javascript:)
		$rawUrl = $node->url ?? '';
		if (!$this->texy->checkURL($rawUrl, Texy\Texy::FILTER_ANCHOR)) {
			// URL fails scheme filter, return just the content without link
			return $generator->serialize($generator->renderNodes($node->content->children));
		}

		$el = new Html\Element('a');

		// Handle nofollow class
		$nofollow = false;
		if ($node->modifier && isset($node->modifier->classes['nofollow'])) {
			$nofollow = true;
			unset($node->modifier->classes['nofollow']);
		}

		// Apply modifier (title, class, id, style, etc.) before href
		$el->attrs['href'] = null; // trick - reserve position at front
		$node->modifier?->decorate($this->texy, $el);

		// Normalize www. to http://
		if (strncasecmp($rawUrl, 'www.', 4) === 0) {
			$rawUrl = 'http://' . $rawUrl;
		}

		// Prepend root to relative URLs
		// Use imageModule.root for image links, linkModule.root otherwise
		$root = $node->isImageLink ? $this->texy->imageModule->root : $this->root;
		$el->attrs['href'] = Helpers::prependRoot($rawUrl, $root);

		// rel="nofollow"
		if ($nofollow || ($this->forceNoFollow && str_contains($el->attrs['href'], '//'))) {
			$el->attrs['rel'] = 'nofollow';
		}

		// Add content
		$el->children = $generator->renderNodes($node->content->children);

		return $el;
	}
}
