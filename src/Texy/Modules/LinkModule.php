<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\InlineParser;
use Texy\Node;
use Texy\Nodes;
use Texy\Nodes\DocumentNode;
use Texy\Nodes\EmailNode;
use Texy\Nodes\LinkDefinitionNode;
use Texy\Nodes\LinkReferenceNode;
use Texy\Nodes\UrlNode;
use Texy\NodeTraverser;
use Texy\Output\Html\Generator;
use Texy\Patterns;
use Texy\Position;
use function htmlspecialchars, iconv_strlen, iconv_substr, in_array, preg_match, str_contains, str_replace, strlen, strncasecmp, strpos, substr, trim;
use const ENT_HTML5, ENT_QUOTES;


/**
 * Processes links, email addresses, and URL references.
 */
final class LinkModule extends Texy\Module
{
	/** root of relative links */
	public ?string $root = null;

	/** linked image class */
	public ?string $imageClass = null;

	/** always use rel="nofollow" for absolute links? */
	public bool $forceNoFollow = false;

	/** shorten URLs to more readable form? */
	public bool $shorten = true;

	/** @var array<string, LinkDefinitionNode> link definitions */
	private array $definitions = [];

	/** @var array<string, LinkDefinitionNode> user-defined definitions (persist across process() calls) */
	private array $userDefinitions = [];

	private static string $EMAIL;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed['link/definition'] = true;
		$texy->addHandler('afterParse', $this->resolveReferences(...));
		$texy->htmlGenerator->registerHandler($this->solveLink(...));
		$texy->htmlGenerator->registerHandler($this->solveUrl(...));
		$texy->htmlGenerator->registerHandler($this->solveEmail(...));
		$texy->htmlGenerator->registerHandler($this->solveReference(...));
		$texy->htmlGenerator->registerHandler(fn(Nodes\LinkDefinitionNode $node) => '');
	}


	public function beforeParse(string &$text): void
	{
		// [reference]
		$this->texy->registerLinePattern(
			$this->parseReference(...),
			'~(
				\[
				[^\[\]*\n' . Patterns::MARK . ']++  # reference
				]
			)~U',
			'link/reference',
		);

		// direct url; characters not allowed in URL <>[\]^`{|}
		$this->texy->registerLinePattern(
			$this->parseUrl(...),
			'~
				(?<= ^ | [\s([<:\x17] )            # must be preceded by these chars
				(?: https?:// | www\. | ftp:// )   # protocol or www
				[0-9.' . Patterns::CHAR . '-]      # first char
				[/\d' . Patterns::CHAR . '+.\~%&?@=_:;#$!,*()\x{ad}-]{1,1000}  # URL body
				[/\d' . Patterns::CHAR . '+\~?@=_#$*]  # last char
			~',
			'link/url',
			'~(?: https?:// | www\. | ftp://)~',
		);

		// direct email
		self::$EMAIL = '
			[' . Patterns::CHAR . ']                 # first char
			[0-9.+_' . Patterns::CHAR . '-]{0,63}    # local part
			@
			[0-9.+_' . Patterns::CHAR . '\x{ad}-]{1,252} # domain
			\.
			[' . Patterns::CHAR . '\x{ad}]{2,19}     # TLD
		';
		$this->texy->registerLinePattern(
			$this->parseEmail(...),
			'~
				(?<= ^ | [\s([<\x17] )             # must be preceded by these chars
				' . self::$EMAIL . '
			~',
			'link/email',
			'~' . self::$EMAIL . '~',
		);

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
	 * Parses [ref]: url label .(title)[class]{style}
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseDefinition(
		Texy\BlockParser $parser,
		array $matches,
		string $name,
		array $offsets,
	): LinkDefinitionNode
	{
		[, $mRef, $mLink, $mLabel, $mMod] = $matches;
		return new LinkDefinitionNode(
			$mRef,
			$mLink,
			trim($mLabel ?? '') ?: null,
			Texy\Modifier::parse($mMod),
			new Position($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses [ref]
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseReference(InlineParser $parser, array $matches, string $name, array $offsets): LinkReferenceNode
	{
		[, $mRef] = $matches;

		return new LinkReferenceNode(
			substr($mRef, 1, -1), // remove [ ]
			new Position($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses direct URL.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseUrl(InlineParser $parser, array $matches, string $name, array $offsets): UrlNode
	{
		[$mURL] = $matches;
		return new UrlNode($mURL, new Position($offsets[0], strlen($matches[0])));
	}


	/**
	 * Parses direct email.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseEmail(InlineParser $parser, array $matches, string $name, array $offsets): EmailNode
	{
		[$mEmail] = $matches;
		return new EmailNode($mEmail, new Position($offsets[0], strlen($matches[0])));
	}


	/**
	 * Adds a user-defined link definition (persists across process() calls).
	 */
	public function addDefinition(
		string $name,
		string $url,
		?string $label = null,
		?string $title = null,
	): void
	{
		$modifier = $title !== null ? Texy\Modifier::parse('(' . $title . ')') : null;
		$this->userDefinitions[Helpers::toLower($name)] = new LinkDefinitionNode($name, $url, $label, $modifier);
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

		// Pass 2: Resolve references
		$traverser->traverse($doc, function (Node $node): ?Node {
			// LinkReferenceNode → LinkNode
			if ($node instanceof LinkReferenceNode) {
				return $this->resolveLinkReference($node);
			}

			// LinkNode with [ref] or [*img*] url
			if ($node instanceof Nodes\LinkNode && $node->url !== null) {
				$this->resolveLinkNodeUrl($node);
			}

			// FigureNode.linkUrl
			if ($node instanceof Nodes\FigureNode && $node->linkUrl !== null) {
				$node->linkUrl = $this->resolveUrl($node->linkUrl);
			}

			return null;
		});
	}


	/**
	 * Resolve LinkReferenceNode to LinkNode or return original.
	 */
	private function resolveLinkReference(LinkReferenceNode $node): Node
	{
		$def = $this->resolveDefinition($node->identifier);

		if ($def === null) {
			// Not found - keep as reference (will render as [identifier])
			return $node;
		}

		// Get original definition for label (def may have modified URL due to #fragment or ?query)
		$key = Helpers::toLower($node->identifier);
		$pos = strpos($key, '#');
		$qpos = strpos($key, '?');
		if ($qpos !== false && ($pos === false || $qpos < $pos)) {
			$pos = $qpos;
		}
		$baseKey = $pos !== false ? substr($key, 0, $pos) : $key;
		$origDef = $this->definitions[$baseKey] ?? $def;

		$labelText = $origDef->label ?? $origDef->url;
		$labelContent = $this->texy->createInlineParser()->parse($labelText, $node->position->offset);
		$linkNode = new Nodes\LinkNode(
			$def->url,
			$labelContent,
			$def->modifier ? clone $def->modifier : null,
			$node->position,
		);

		// Convert email URLs to mailto:
		$this->convertEmailUrl($linkNode);

		return $linkNode;
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

		// [*img*], [*img <], [*img >] format - resolve to image URL with linkedRoot
		if ($len > 4 && $node->url[0] === '[' && $node->url[1] === '*'
			&& $node->url[$len - 1] === ']'
			&& in_array($node->url[$len - 2], ['*', '<', '>'], true)) {
			$imgRef = trim(substr($node->url, 2, -2));
			$imgDef = $this->texy->imageModule->getDefinition($imgRef);
			if ($imgDef !== null && $imgDef->url !== null) {
				$node->url = Helpers::prependRoot($imgDef->url, $this->texy->imageModule->linkedRoot);
			} else {
				// Image reference not found - use content as URL with linkedRoot
				$node->url = Helpers::prependRoot($imgRef, $this->texy->imageModule->linkedRoot);
			}
			$node->urlRooted = true;
			return;
		}

		// [ref] format
		if ($len > 2 && $node->url[0] === '[' && $node->url[$len - 1] === ']') {
			$refName = substr($node->url, 1, -1);
			$def = $this->resolveDefinition($refName);
			if ($def !== null) {
				$node->url = $def->url;
				// Merge modifier from definition
				if ($def->modifier) {
					$this->mergeModifier($node, $def->modifier);
				}
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
			&& str_contains($node->url, '@')
			&& !str_contains($node->url, '/')
			&& !preg_match('~^[a-z][a-z0-9+.-]*:~i', $node->url)) {
			$node->url = 'mailto:' . $node->url;
		}
	}


	/**
	 * Merge definition modifier into link node (node modifier takes precedence).
	 */
	private function mergeModifier(Nodes\LinkNode $node, Texy\Modifier $defMod): void
	{
		if ($node->modifier === null) {
			$node->modifier = clone $defMod;
			return;
		}

		// Title: node takes precedence
		if ($node->modifier->title === null && $defMod->title !== null) {
			$node->modifier->title = $defMod->title;
		}

		// Classes: merge (definition + node)
		foreach ($defMod->classes as $class => $value) {
			if (!isset($node->modifier->classes[$class])) {
				$node->modifier->classes[$class] = $value;
			}
		}

		// Styles: node takes precedence
		foreach ($defMod->styles as $prop => $value) {
			if (!isset($node->modifier->styles[$prop])) {
				$node->modifier->styles[$prop] = $value;
			}
		}

		// ID: node takes precedence
		if ($node->modifier->id === null && $defMod->id !== null) {
			$node->modifier->id = $defMod->id;
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


	public function solveLink(Nodes\LinkNode $node, Generator $generator): string
	{
		$content = $generator->generateInlineContent($node->content);

		// Handle nofollow class
		$nofollow = false;
		if ($node->modifier && isset($node->modifier->classes['nofollow'])) {
			$nofollow = true;
			unset($node->modifier->classes['nofollow']);
		}

		$attrs = $generator->generateModifierAttrs($node->modifier);

		// Check URL scheme (security - filter dangerous schemes like javascript:)
		$rawUrl = $node->url ?? '';
		if (!$this->texy->checkURL($rawUrl, Texy\Texy::FILTER_ANCHOR)) {
			// URL fails scheme filter, return just the content without link
			return $content;
		}

		// Prepend root to relative URLs (unless already rooted)
		$url = $node->urlRooted ? $rawUrl : Helpers::prependRoot($rawUrl, $this->root);
		$urlEncoded = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$urlEncoded = str_replace('@', '&#64;', $urlEncoded);

		// Add rel="nofollow" if needed
		$rel = '';
		if ($nofollow || ($this->forceNoFollow && str_contains($url, '//'))) {
			$rel = ' rel="nofollow"';
		}

		// Protect tags, but not content (typography works on content)
		$open = $this->texy->protect("<a href=\"{$urlEncoded}\"{$attrs}{$rel}>", $this->texy::CONTENT_MARKUP);
		$close = $this->texy->protect('</a>', $this->texy::CONTENT_MARKUP);
		return $open . $content . $close;
	}


	public function solveUrl(UrlNode $node, Generator $generator): string
	{
		$url = $node->url;

		// Normalize www. to http://
		if (strncasecmp($url, 'www.', 4) === 0) {
			$url = 'http://' . $url;
		}

		$urlEncoded = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$visual = htmlspecialchars($this->textualUrl($node->url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// Add rel="nofollow" for absolute URLs if forceNoFollow is enabled
		$rel = '';
		if ($this->forceNoFollow && str_contains($url, '//')) {
			$rel = ' rel="nofollow"';
		}

		return $this->texy->protect("<a href=\"{$urlEncoded}\"{$rel}>{$visual}</a>", $this->texy::CONTENT_REPLACED);
	}


	public function solveEmail(EmailNode $node, Generator $generator): string
	{
		$email = $node->email;
		if ($this->texy->obfuscateEmail) {
			$visual = str_replace('@', '&#64;<!-- -->', htmlspecialchars($email, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			$href = 'mailto:' . htmlspecialchars($email, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		} else {
			$visual = htmlspecialchars($email, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$href = 'mailto:' . $visual;
		}
		return $this->texy->protect("<a href=\"{$href}\">{$visual}</a>", $this->texy::CONTENT_REPLACED);
	}


	public function solveReference(LinkReferenceNode $node, Generator $generator): string
	{
		// Link references are resolved in passes, fallback to text
		return '[' . htmlspecialchars($node->identifier, ENT_QUOTES | ENT_HTML5, 'UTF-8') . ']';
	}


	/**
	 * Returns textual representation of URL (shortened for display).
	 */
	private function textualUrl(string $url): string
	{
		if (!$this->shorten || !preg_match('~^(https?://|ftp://|www\.|/)~i', $url)) {
			return $url;
		}

		$raw = strncasecmp($url, 'www.', 4) === 0
			? 'none://' . $url
			: $url;

		// parse_url() in PHP damages UTF-8 - use regular expression
		if (!preg_match('~^
			(?: (?P<scheme> [a-z]+ ) : )?
			(?: // (?P<host> [^/?#]+ ) )?
			(?P<path> (?: / | ^ ) (?! / ) [^?#]* )?
			(?: \? (?P<query> [^#]* ) )?
			(?: \# (?P<fragment> .* ) )?
			$
		~x', $raw, $parts)) {
			return $url;
		}

		$res = '';
		$scheme = $parts['scheme'] ?? null;
		// Don't show http/https/ftp schemes in shortened URLs
		if ($scheme !== null && $scheme !== 'none' && !in_array($scheme, ['http', 'https', 'ftp'], true)) {
			$res .= $scheme . '://';
		}

		if (($parts['host'] ?? null) !== null) {
			$res .= $parts['host'];
		}

		if (($parts['path'] ?? null) !== null) {
			$res .= (iconv_strlen($parts['path'], 'UTF-8') > 16 ? ("/\u{2026}" . iconv_substr($parts['path'], -12, 12, 'UTF-8')) : $parts['path']);
		}

		if (($parts['query'] ?? '') > '') {
			$res .= iconv_strlen($parts['query'], 'UTF-8') > 4
				? "?\u{2026}"
				: ('?' . $parts['query']);
		} elseif (($parts['fragment'] ?? '') > '') {
			$res .= iconv_strlen($parts['fragment'], 'UTF-8') > 4
				? "#\u{2026}"
				: ('#' . $parts['fragment']);
		}

		return $res;
	}
}
