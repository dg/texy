<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Passes;

use Texy\Helpers;
use Texy\Node;
use Texy\Nodes;
use Texy\NodeTraverser;
use Texy\Output;
use Texy\Regexp;
use Texy\Syntax;
use function htmlspecialchars, str_contains, strlen, strpos, strtolower, substr;
use const ENT_NOQUOTES;


/**
 * Applies string transformations (typography, hyphenation) to the AST.
 *
 * For every block-level inline container it builds a "text image": TextNode
 * contents joined with marker characters standing in for markup boundaries
 * and replaced/protected content - the same alphabet (\x15 textual,
 * \x16 replaced, \x17 markup) the post-line regexes already understand.
 * Transformers run over the image and the result is written back into the
 * text nodes. The patterns never create nor destroy marker characters,
 * so on the way back the markers delimit the runs unambiguously.
 */
final class TextRunPass
{
	private const
		Textual = "\x15",  // opaque protected text (code, URL); breaks words, not transparent to patterns
		Replaced = "\x16", // replaced content (images, br); nbsp patterns bind to it
		Markup = "\x17";   // markup boundary; transparent to patterns via [\x17-\x1F]

	/** piece of a text image: [text, writable target node or null] */
	private const Break = null; // sentinel piece: block-level markup splits the image into segments


	/**
	 * @param list<\Closure(string): string> $transformers  applied to each image in order
	 * @param ?\Closure(string): string $titleTransformer  applied to Modifier::$title (isolated strings)
	 */
	public function __construct(
		private array $transformers,
		private ?\Closure $titleTransformer = null,
	) {
	}


	public function process(Nodes\DocumentNode $doc): void
	{
		(new NodeTraverser)->traverse($doc, function (Node $node): ?int {
			$this->transformTitle($node);

			if ($node instanceof Nodes\ContentNode && $this->isInlineContainer($node)) {
				$this->processUnit($node);
				return NodeTraverser::DontTraverseChildren;
			}

			return null;
		});
	}


	/** Is the container pure inline content, i.e. one typographic unit? */
	private function isInlineContainer(Nodes\ContentNode $node): bool
	{
		if (!$node->children) {
			return false;
		}
		foreach ($node->children as $child) {
			if (!$child instanceof Nodes\InlineNode) {
				return false;
			}
		}

		return true;
	}


	private function transformTitle(Node $node): void
	{
		$modifier = $node->getModifier();
		if ($this->titleTransformer !== null && $modifier?->title !== null) {
			$modifier->title = ($this->titleTransformer)($modifier->title);
		}
	}


	/**
	 * Processes one inline container as a typographic unit.
	 */
	private function processUnit(Nodes\ContentNode $content): void
	{
		$pieces = [];
		$this->collect($content->children, $pieces); // also transforms titles of inline nodes

		if (!$this->transformers) {
			return;
		}

		// block-level markup splits the unit into independently processed segments
		$segment = [];
		foreach ($pieces as $piece) {
			if ($piece === self::Break) {
				$this->processSegment($segment);
				$segment = [];
			} else {
				$segment[] = $piece;
			}
		}

		$this->processSegment($segment);
	}


	/**
	 * Builds image pieces for inline nodes.
	 * @param  array<Node>  $nodes
	 * @param  list<array{string, ?Node}|null>  $pieces
	 */
	private function collect(array $nodes, array &$pieces): void
	{
		foreach ($nodes as $node) {
			$this->transformTitle($node);

			if ($node instanceof Nodes\TextNode) {
				$pieces[] = [self::decode($node->text), $node];

			} elseif ($node instanceof Nodes\PhraseNode) {
				if ($node->type === Syntax::Code) {
					// content is protected from typography; the run length mirrors
					// the visible length so length-sensitive patterns (short last word)
					// judge it fairly
					$pieces[] = [self::textualRun(Helpers::extractText($node->content)), null];
				} else {
					$pieces[] = [self::Markup, null];
					$this->collect($node->content->children, $pieces);
					$pieces[] = [self::Markup, null];
				}

			} elseif ($node instanceof Nodes\LinkNode) {
				$pieces[] = [self::Markup, null];
				$this->collect($node->content->children, $pieces);
				$pieces[] = [self::Markup, null];

			} elseif ($node instanceof Nodes\RawTextNode) {
				$pieces[] = [$node->text, $node]; // rendered as plain text, typography applies

			} elseif ($node instanceof Nodes\AnnotationNode) {
				$pieces[] = [self::Markup, null];
				$pieces[] = [self::decode($node->text), $node];
				$pieces[] = [self::Markup, null];

			} elseif ($node instanceof Nodes\HtmlTagNode) {
				$inlineType = Output\Html\Schema::inlineElements()[strtolower($node->name)] ?? null;
				if ($inlineType === null) {
					$pieces[] = self::Break; // block-level tag ends the typographic segment
				} else {
					$pieces[] = [$inlineType ? self::Replaced : self::Markup, null];
				}

			} elseif ($node instanceof Nodes\HtmlCommentNode) {
				$pieces[] = [self::Markup, null];

			} elseif ($node instanceof Nodes\UrlNode) {
				$pieces[] = [self::textualRun($node->url), null]; // display text is protected from typography

			} elseif ($node instanceof Nodes\EmailNode) {
				$pieces[] = [self::textualRun($node->email), null];

			} elseif ($node instanceof Nodes\DirectiveNode) {
				$pieces[] = [self::textualRun($node->text), null];

			} elseif (
				$node instanceof Nodes\CommentNode
				|| $node instanceof Nodes\ImageDefinitionNode
				|| $node instanceof Nodes\LinkDefinitionNode
			) {
				// renders to nothing

			} else { // ImageNode, LineBreakNode, EmoticonNode, unknown inline nodes
				$pieces[] = [self::Replaced, null];
			}
		}
	}


	/**
	 * Applies transformers to the segment image and writes the result back.
	 * @param  list<array{string, ?Node}>  $pieces
	 */
	private function processSegment(array $pieces): void
	{
		$writable = false;
		foreach ($pieces as [, $target]) {
			$writable = $writable || $target !== null;
		}
		if (!$writable) {
			return;
		}

		$image = '';
		foreach ($pieces as [$text]) {
			$image .= $text;
		}

		$transformed = $image;
		foreach ($this->transformers as $transformer) {
			$transformed = $transformer($transformed);
		}

		if ($transformed === $image) {
			return;
		}

		$this->writeBack($pieces, $transformed);
	}


	/**
	 * Distributes the transformed image back into writable nodes, using the
	 * marker pieces (never created nor destroyed by patterns) as delimiters.
	 * Text between two markers belongs to the writable runs of that gap;
	 * with several runs in one gap the first receives everything. Writes are
	 * planned first and applied only when every marker was located, so a
	 * failed lookup leaves the AST untouched.
	 * @param  list<array{string, ?Node}>  $pieces
	 */
	private function writeBack(array $pieces, string $transformed): void
	{
		// phase 1: locate the markers, plan writes (no mutation yet)
		$pos = 0;
		$gap = []; // writable nodes since last marker
		$writes = []; // planned [node, new content]

		foreach ($pieces as [$text, $target]) {
			if ($target !== null) {
				$gap[] = $target;
				continue;
			}

			$idx = strpos($transformed, $text, $pos);
			if ($idx === false) { // cannot happen unless a pattern eats markers
				return;
			}

			foreach ($gap as $i => $node) {
				$writes[] = [$node, $i === 0 ? substr($transformed, $pos, $idx - $pos) : ''];
			}

			$gap = [];
			$pos = $idx + strlen($text);
		}

		foreach ($gap as $i => $node) {
			$writes[] = [$node, $i === 0 ? substr($transformed, $pos) : ''];
		}

		// phase 2: apply
		foreach ($writes as [$node, $text]) {
			assert($node instanceof Nodes\TextNode || $node instanceof Nodes\RawTextNode || $node instanceof Nodes\AnnotationNode);
			// TextNode/AnnotationNode render raw and go through the entity dance,
			// so the decoded image must be re-encoded on the way back
			$node->text = $node instanceof Nodes\RawTextNode
				? $text
				: htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
		}
	}


	/**
	 * Opaque textual run whose length approximates the visible length,
	 * capped so it stays a cheap marker.
	 */
	private static function textualRun(string $visible): string
	{
		return str_repeat(self::Textual, min(max(mb_strlen($visible), 1), 8));
	}


	/**
	 * The legacy string pipeline ran typography after HTML entities were
	 * decoded; the image must match, otherwise entities count as characters.
	 * Entities may smuggle in control characters - strip them (input
	 * normalization does the same for raw input).
	 */
	private static function decode(string $s): string
	{
		if (!str_contains($s, '&')) {
			return $s;
		}

		$s = Helpers::unescapeHtml($s);
		return Regexp::replace($s, '~[\x00-\x08\x0B-\x1F]+~', '');
	}
}
