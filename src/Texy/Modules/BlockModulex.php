<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\HtmlElement;
use Texy\Nodes\BlockNode;
use Texy\Nodes\CodeBlockNode;
use Texy\Nodes\CommentNode;
use Texy\Nodes\SectionNode;


/**
 * Special blocks module.
 */
final class BlockModule extends Texy\Module
{
	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		//$texy->allowed['blocks'] = true;
		//$texy->allowed['block/texy'] = TODO: remove
		$texy->allowed['block/default'] = true; // CodeBlockNode s <pre>
		$texy->allowed['block/pre'] = true; // CodeBlockNode s <pre> + parsuje HTML
		$texy->allowed['block/code'] = true; // CodeBlockNode s <pre><code>
		$texy->allowed['block/html'] = true; // SectionNode
		$texy->allowed['block/text'] = true; // SectionNode
		$texy->allowed['block/texysource'] = true; // CodeBlockNode
		$texy->allowed['block/comment'] = true; // CommentNode
		$texy->allowed['block/div'] = true; // SectionNode

		$texy->addHandler(CodeBlockNode::class, $this->codeToElement(...));
		$texy->addHandler(SectionNode::class, $this->sectionToElement(...));
		$texy->addHandler(CommentNode::class, $this->commentToElement(...));
		$texy->addHandler('beforeBlockParse', $this->beforeBlockParse(...));

		$texy->registerBlockPattern(
			$this->parse(...),
			'~^
				/--++ \ *+                    # opening tag /--
				(.*)                          # content type (1)
				' . Texy\Patterns::MODIFIER_H . '? # modifier (2)
				$
				((?:                         # content (3)
					\n (?0) |                # recursive nested blocks
					\n.*+                    # or any content
				)*)
				(?:
					\n \\\--.* $ |           # closing tag
					\z                       # or end of input
				)
			~mUi',
			'blocks',
		);
	}


	/**
	 * Single block pre-processing.
	 */
	private function beforeBlockParse(Texy\BlockParser $parser, string &$text): void
	{
		// autoclose exclusive blocks
		$text = Texy\Regexp::replace(
			$text,
			'~^
				( /--++ \ *+ (?! div|texysource ) .* )  # opening tag except div/texysource (1)
				$
				((?: \n.*+ )*?)                 # content (2)
				(?:
					\n \\\--.* $ |              # closing tag
					(?= (\n /--.* $))           # or next block starts (3)
				)
			~mi',
			"\$1\$2\n\\--",                 // add closing tag
		);
	}


	/**
	 * Callback for:.
	 * /-----code html .(title)[class]{style}
	 * ....
	 * ....
	 * \----
	 */
	public function parse(Texy\BlockParser $parser, array $matches): ?BlockNode
	{
		[, $mParam, $mMod, $mContent] = $matches;
		// [1] => code | text | ...
		// [2] => ... additional parameters
		// [3] => .(title)[class]{style}<>
		// [4] => ... content

		$texy = $this->texy;
		$parts = Texy\Regexp::split($mParam, '~\s+~', limit: 2);
		$type = empty($parts[0]) ? 'block/default' : 'block/' . $parts[0];
		$param = empty($parts[1]) ? null : $parts[1];

		if (empty($texy->allowed[$type])) {
			return null;
		}

		$mContent = $type === 'block/texy' || $type === 'block/html' || $type === 'block/text'
			? trim($mContent, "\n")
			: Helpers::outdent($mContent);
		if ($mContent === '') {
			return null; // \n
		}

		if ($type === 'block/texy') {
			return new SectionNode($this->texy->parseLine($mContent));
		}

		if ($type === 'block/texysource') {
			$el = new HtmlElement;
			$el->inject($texy, $param === 'line' ? $texy->parseLine($mContent) : $texy->parseBlock($mContent));
			return new CodeBlockNode(
				$type,
				$texy->maskedStringToHtml($texy->elemToMaskedString($el)),
				'html',
				$mMod ? new Texy\Modifier($mMod) : null,
			);
		}

		if ($type == 'block/code') {
			// TODO: use <code>
			return new CodeBlockNode(
				$type,
				$mContent,
				$param,
				$mMod ? new Texy\Modifier($mMod) : null,
			);
		}

		if ($type === 'block/default') {
			return new CodeBlockNode(
				$type,
				$mContent,
				null,
				$mMod ? new Texy\Modifier($mMod) : null,
			);
		}

		if ($type === 'block/pre') {
			$lineParser = new Texy\LineParser($texy);
			$lineParser->patterns = array_intersect_key($lineParser->patterns, ['html/tag' => null, 'html/comment' => null]);
			return new CodeBlockNode(
				$type,
				$lineParser->parse($mContent),
				null,
				$mMod ? new Texy\Modifier($mMod) : null,
			);
		}

		if ($type === 'block/html') {
			if ($mMod) {
				throw new Texy\Exception('HTML block cannot have modifier.');
			}
			$lineParser = new Texy\LineParser($texy);
			$lineParser->patterns = array_intersect_key($lineParser->patterns, ['html/tag' => null, 'html/comment' => null]);
			return new SectionNode($lineParser->parse($mContent));
		}

		if ($type === 'block/text') {
			if ($mMod) {
				throw new Texy\Exception('Text block cannot have modifier.');
			}
			return new SectionNode([$mContent]); // todo: <br>
		}

		if ($type === 'block/comment') {
			if ($mMod) {
				throw new Texy\Exception('Comment block cannot have modifier.');
			}
			return new CommentNode($mContent);
		}

		if ($type === 'block/div') {
			return new SectionNode($texy->parseBlock($mContent), 'div', $mMod ? new Texy\Modifier($mMod) : null);
		}

		return null;
	}


	private function blockCode(string $s, Texy\Texy $texy, Texy\Modifier $mod, $param): string|HtmlElement
	{
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = $texy->protect($s, $texy::CONTENT_BLOCK);
		$el->create('code', $s);
		return $el;
	}


	private function blockDefault(string $s, Texy\Texy $texy, Texy\Modifier $mod, $param): string|HtmlElement
	{
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = $texy->protect($s, $texy::CONTENT_BLOCK);
		$el->setText($s);
		return $el;
	}


	private function blockPre(string $s, Texy\Texy $texy, Texy\Modifier $mod): string|HtmlElement
	{
		$s = Helpers::unescapeHtml($s);
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = $texy->unprotect($s);
		$s = $texy->protect($s, $texy::CONTENT_BLOCK);
		$el->setText($s);
		return $el;
	}


	public function codeToElement(CodeBlockNode $node, Texy\Texy $texy): ?HtmlElement
	{
		// beforeBlockParse: autoclose blocks,
		// beforeParse: odlozena inicializace, připrava proměných, parsovaní definic obrazků a odkazů
		// afterParse: spocitani TOC & levely
		// postProcess: wellformed HTML output
		// postHandlers: typography, longwords
		$el = new HtmlElement('pre');
		$code = $codxe ? $el->create('code') : $el;
		$el->inject($texy, $node->content, $node->modifier);
		$el->attrs['class'][] = $node->language;
		return $el;
	}


	private function blockHtml(string $s, Texy\Texy $texy): string
	{
		$s = $el->getText();
		$s = Helpers::unescapeHtml($s);
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = $texy->unprotect($s);
		return $texy->protect($s, $texy::CONTENT_BLOCK) . "\n";
	}


	private function blockText(string $s, Texy\Texy $texy): string
	{
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
		$s = str_replace("\n", (new HtmlElement('br'))->startTag(), $s); // nl2br
		return $texy->protect($s, $texy::CONTENT_BLOCK) . "\n";
	}


	public function sectionToElement(SectionNode $node, Texy\Texy $texy): HtmlElement
	{
		$el = new HtmlElement($node->type);
		$el->inject($texy, $node->content, $node->modifier);
		return $el;
	}


	public function commentToElement(CommentNode $node): void
	{
	}
}
