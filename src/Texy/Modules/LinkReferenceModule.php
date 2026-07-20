<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\NodeHelpers;
use Texy\Nodes;
use Texy\Nodes\DocumentNode;
use Texy\Nodes\LinkDefinitionNode;
use Texy\Parsing\ParseContext;
use Texy\Patterns;
use Texy\Syntax;
use function in_array, strlen;


/**
 * Processes link references and generates link elements.
 */
final class LinkReferenceModule extends Texy\Module
{
	/** @var array<string, LinkDefinitionNode> link definitions */
	private array $definitions = [];

	/** @var array<string, LinkDefinitionNode> user-defined definitions (persist across process() calls) */
	private array $userDefinitions = [];


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed[Syntax::LinkDefinition] = true;
		$texy->allowed[Syntax::LinkReference] = false;
		$texy->addHandler('afterParse', $this->resolveReferences(...));
	}


	public function beforeParse(): void
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
			$~mUx',
			Syntax::LinkDefinition,
		);

		// [reference] - bare reference link (opt-in); the raw target is kept in
		// LinkNode::$ref for consumer resolution passes (wiki links etc.)
		$this->texy->registerLinePattern(
			$this->parseReference(...),
			'~
				(?<! [.\#\[] )        # exclude modifier .[class], PHP attribute \#[Attr] and nested [
				\[
				( [^\[\]*|\n' . Patterns::MARK . ']++ )  # reference name (1); * would be an image, | a labeled form
				]
			~Ux',
			Syntax::LinkReference,
		);
	}


	/**
	 * Parses bare [ref]
	 * @param  array<?string>  $matches
	 */
	public function parseReference(ParseContext $context, array $matches): Nodes\LinkNode
	{
		/** @var array{string, string} $matches */
		[, $mRef] = $matches;
		return new Nodes\LinkNode(
			url: '[' . $mRef . ']', // bracket form is resolved against definitions like "text":[ref]
			content: new Nodes\ContentNode([new Nodes\TextNode($mRef)]),
			ref: $mRef,
		);
	}


	/**
	 * Parses [ref]: url
	 * @param  array<?string>  $matches
	 */
	public function parseDefinition(ParseContext $context, array $matches): LinkDefinitionNode
	{
		/** @var array{string, string, string, ?string, ?string} $matches */
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

		// Pass 1: Collect document definitions (overwrites user-defined)
		foreach (NodeHelpers::find($doc, LinkDefinitionNode::class) as $node) {
			$this->definitions[Helpers::toLower($node->reference)] = $node;
		}

		// Pass 2: Resolve references in LinkNode.url
		foreach (NodeHelpers::find($doc, Nodes\LinkNode::class) as $node) {
			$this->resolveLinkNodeUrl($node);
		}
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
			$node->ref = $imgRef; // keep the written reference name for analyzers
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
			$node->ref = $refName; // keep the written reference name for analyzers
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
}
