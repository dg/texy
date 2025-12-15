<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Helpers, Nodes, Patterns, Range, Syntax};
use Texy\Nodes\{DocumentNode, LinkDefinitionNode};
use Texy\Parsing\ParseContext;
use function in_array, strlen;


/**
 * Processes link references and generates link elements.
 */
final class LinkReferenceModule extends Texy\Module
{
	/** @var array<string, LinkDefinitionNode> link definitions */
	private array $definitions = [];


	public function __construct(
		private Texy\TexyConfig $config,
		private ImageModule $imageModule,
	) {
	}


	public function register(Texy\Parsing\Registry $registry): void
	{
		$registry->declare(Syntax::LinkReference, enabled: false);

		// [ref]: url label .(title)[class]{style}
		$registry->addBlock(
			Syntax::LinkDefinition,
			'~^
				\[
				( [^\[\]#?*\n]{1,100} )           # reference (1)
				] : \ ++
				( \S{1,1000} )                    # URL (2)
				( [ \t] .{1,1000} )?              # optional label (3)
				' . Patterns::Modifier . '?       # modifier (4)
				\s*
			$~mUx',
			$this->parseDefinition(...),
		);

		// [reference] - bare reference link (opt-in); the raw target is kept in
		// LinkNode::$ref for consumer resolution passes (wiki links etc.)
		$registry->addInline(
			Syntax::LinkReference,
			'~
				(?<! [.\#\[] )        # exclude modifier .[class], PHP attribute \#[Attr] and nested [
				\[
				( [^\[\]*|\n]++ )     # reference name (1); * would be an image, | a labeled form
				]
			~Ux',
			$this->parseReference(...),
		);
	}


	/**
	 * Parses bare [ref]
	 * @param  array{string, string}  $matches
	 * @param  array{int, int}  $offsets
	 */
	public function parseReference(ParseContext $context, array $matches, array $offsets): Nodes\LinkNode
	{
		[, $mRef] = $matches;
		$refRange = new Range($offsets[1], strlen($mRef));
		return new Nodes\LinkNode(
			content: new Nodes\ContentNode([new Nodes\TextNode(Helpers::decodeEntities($mRef), $refRange)]),
			range: new Range($offsets[0], strlen($matches[0])),
			ref: $mRef,
			syntax: Syntax::LinkReference,
			textRange: $refRange,
			urlRange: $refRange,
		);
	}


	/**
	 * Parses [ref]: url
	 * @param  array{string, string, string, ?string, ?string}  $matches
	 * @param  array{int, int, int, ?int, ?int}  $offsets
	 */
	public function parseDefinition(ParseContext $context, array $matches, array $offsets): LinkDefinitionNode
	{
		[, $mRef, $mLink, $mLabel, $mMod] = $matches;
		if ($mMod || $mLabel) {
			trigger_error('Modifiers and label in link definitions are deprecated.', E_USER_DEPRECATED);
		}
		return new LinkDefinitionNode(
			$mRef,
			Helpers::decodeEntities($mLink),
			new Range($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Recognizes link targets written as a reference, [ref] or [*img*]: the name goes
	 * to LinkNode::$ref and the URL is left to resolveReferences().
	 */
	public function recognizeReferences(DocumentNode $doc): void
	{
		foreach ($doc->find(Nodes\LinkNode::class) as $node) {
			$url = $node->url ?? '';
			$len = strlen($url);
			if ($len > 4 && $url[0] === '[' && $url[1] === '*' && $url[$len - 1] === ']'
				&& in_array($url[$len - 2], ['*', '<', '>'], true)
			) {
				$node->ref = trim(substr($url, 2, -2));
				$node->isImageLink = true;
				$node->url = null;

			} elseif ($len > 2 && $url[0] === '[' && $url[$len - 1] === ']') {
				$node->ref = substr($url, 1, -1);
				$node->url = null;
			}
		}
	}


	/**
	 * Resolve link references in the document. A Transform pass, runs after the one of ImageModule.
	 */
	public function resolveReferences(DocumentNode $doc): void
	{
		// Start with the definitions of the application
		$this->definitions = $this->config->linkDefinitions;

		// Pass 1: Collect document definitions (overwrites user-defined)
		foreach ($doc->find(LinkDefinitionNode::class) as $node) {
			$this->definitions[Helpers::toLower($node->reference)] = $node;
		}

		// Pass 2: Resolve references in LinkNode.url
		foreach ($doc->find(Nodes\LinkNode::class) as $node) {
			$this->resolveLinkNodeUrl($node);
		}
	}


	/**
	 * Resolves the URL of a link written as [ref] or [*img*]; a reference not found is used as the URL.
	 * A %s in the URL stands for the link text, such as "strlen":[php] with php.net/%s.
	 */
	private function resolveLinkNodeUrl(Nodes\LinkNode $node): void
	{
		if ($node->url === null && $node->ref !== null) {
			if ($node->isImageLink) {
				$node->url = $this->imageModule->getDefinition($node->ref)->url ?? $node->ref;
				return;
			}
			$node->url = $this->resolveDefinition($node->ref)->url ?? $node->ref;
		}

		if ($node->url !== null && $node->syntax !== Syntax::LinkReference && str_contains($node->url, '%s')) {
			$node->url = str_replace('%s', urlencode(trim($node->content->getPlainText())), $node->url);
		}

		$this->convertEmailUrl($node);
	}


	/**
	 * Convert e-mail URLs to mailto: scheme. Requires a full e-mail address
	 * (Patterns::Email) so that wiki-style targets containing @ are left alone.
	 */
	private function convertEmailUrl(Nodes\LinkNode $node): void
	{
		if (
			$node->url !== null
			&& !str_contains($node->url, '/')
			&& !preg_match('~^[a-z][a-z0-9+.-]*:~i', $node->url)
			&& Texy\Regexp::match($node->url, '~' . Patterns::Email . '(\?\S*)?$~A')
		) { // optional ?subject=... query
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
