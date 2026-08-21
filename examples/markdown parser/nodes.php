<?php declare(strict_types=1);

/** AST nodes this parser needs on top of Texy\Nodes\* - the rest of Markdown maps straight onto the Texy AST. */

namespace Texy\Examples\Markdown;

use Texy\{Nodes, Range};


/** A raw HTML block, passed through verbatim. */
class HtmlBlockNode extends Nodes\BlockNode
{
	public function __construct(
		public string $html,
		public ?Range $range = null,
	) {
	}
}


/** A list that knows whether it is tight (items rendered without <p>). */
class ListNode extends Nodes\ListNode
{
	public bool $tight = true;
}


/** A newline inside a paragraph: rendered as a newline, not as <br>. */
class SoftBreakNode extends Nodes\InlineNode
{
}


/** Raw inline HTML, passed through verbatim. */
class RawInlineNode extends Nodes\InlineNode
{
	public function __construct(
		public string $html,
		public ?Range $range = null,
	) {
	}
}


/**
 * A run of * or _ left undecided by the parser; the flags are the spec's
 * left/right-flanking classification, the pairing happens in processEmphasis().
 */
class DelimiterNode extends Nodes\InlineNode
{
	public string $char;

	/** length as written, which the "rule of three" is judged on even after characters are consumed */
	public int $origLength;
	public int $remaining;
	public bool $canOpen;
	public bool $canClose;


	public function __construct(
		public string $text,
		string $before,
		string $after,
		public ?Range $range = null,
	) {
		$this->char = $text[0];
		$this->origLength = $this->remaining = strlen($text);

		$beforeWs = $before === '' || (bool) preg_match('~^\s$~u', $before);
		$afterWs = $after === '' || (bool) preg_match('~^\s$~u', $after);
		$beforePunct = !$beforeWs && (bool) preg_match('~^[\p{P}\p{S}]$~u', $before);
		$afterPunct = !$afterWs && (bool) preg_match('~^[\p{P}\p{S}]$~u', $after);

		$leftFlanking = !$afterWs && (!$afterPunct || $beforeWs || $beforePunct);
		$rightFlanking = !$beforeWs && (!$beforePunct || $afterWs || $afterPunct);

		// _ is stricter than *, so that snake_case_words are not torn apart
		if ($this->char === '_') {
			$this->canOpen = $leftFlanking && (!$rightFlanking || $beforePunct);
			$this->canClose = $rightFlanking && (!$leftFlanking || $afterPunct);
		} else {
			$this->canOpen = $leftFlanking;
			$this->canClose = $rightFlanking;
		}
	}
}


/** A link; Markdown needs a title, which Texy\Nodes\LinkNode has no slot for. */
class LinkNode extends Nodes\InlineNode
{
	/** @var ?array{string, string}  text to fall back to when a reference does not resolve */
	public ?array $fallback = null;


	public function __construct(
		public string $url,
		public Nodes\ContentNode $content,
		public ?string $title = null,
		public ?Range $range = null,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}
}


/** An image. Its alt text is a node list, because Markdown allows markup inside it. */
class ImageNode extends Nodes\InlineNode
{
	/** @var ?array{string, string}  text to fall back to when a reference does not resolve */
	public ?array $fallback = null;


	public function __construct(
		public string $url,
		public Nodes\ContentNode $alt,
		public ?string $title = null,
		public ?Range $range = null,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->alt;
	}
}
