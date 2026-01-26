<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\Nodes;
use Texy\Output\Html\Generator;
use Texy\Position;
use function htmlspecialchars, preg_replace_callback, str_starts_with, strlen, substr, trim;
use const ENT_HTML5, ENT_NOQUOTES, ENT_QUOTES;


/**
 * Processes special blocks (/-- code, html, text, div, etc.).
 */
final class BlockModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed['block/default'] = true;
		$texy->allowed['block/pre'] = true;
		$texy->allowed['block/code'] = true;
		$texy->allowed['block/html'] = true;
		$texy->allowed['block/text'] = true;
		$texy->allowed['block/texysource'] = true;
		$texy->allowed['block/comment'] = true;
		$texy->allowed['block/div'] = true;
		$texy->htmlGenerator->registerHandler($this->solveCodeBlock(...));
		$texy->htmlGenerator->registerHandler($this->solveSection(...));
		$texy->htmlGenerator->registerHandler(fn(Nodes\CommentNode $node) => '');
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerBlockPattern(
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
	 * Parses blocks /--foo
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parse(Texy\BlockParser $parser, array $matches, string $name, array $offsets): ?Texy\Nodes\BlockNode
	{
		[, $mParam, $mMod, $mContent] = $matches;

		$mod = Texy\Modifier::parse($mMod);
		$parts = Texy\Regexp::split($mParam, '~\s+~', limit: 2);
		$blocktype = empty($parts[0]) ? 'block/default' : 'block/' . $parts[0];
		$param = empty($parts[1]) ? null : $parts[1];

		$content = Helpers::outdent($mContent);
		$position = new Position($offsets[0], strlen($matches[0]));

		if ($blocktype === 'block/code') {
			return new Texy\Nodes\CodeBlockNode('code', $content, $param, $mod, $position);

		} elseif ($blocktype === 'block/default' || $blocktype === 'block/pre') {
			return new Texy\Nodes\CodeBlockNode($blocktype === 'block/pre' ? 'pre' : 'default', $content, $param, $mod, $position);

		} elseif ($blocktype === 'block/comment') {
			return new Texy\Nodes\CommentNode($content, $position);

		} elseif ($blocktype === 'block/html') {
			return new Texy\Nodes\CodeBlockNode('html', trim($content, "\n"), null, $mod, $position);

		} elseif ($blocktype === 'block/text') {
			return new Texy\Nodes\CodeBlockNode('text', trim($content, "\n"), null, $mod, $position);

		} elseif ($blocktype === 'block/div') {
			$content = Helpers::outdent($mContent, firstLine: true);
			if ($content === '') {
				return null;
			}
			$nestedParser = $this->texy->createBlockParser();
			return new Texy\Nodes\SectionNode($nestedParser->parse($content), 'div', $mod, $position);

		} elseif ($blocktype === 'block/texysource') {
			// Parse content as texy, then wrap result for display
			$content = Helpers::outdent($mContent);
			if ($content === '') {
				return null;
			}
			$nestedParser = $this->texy->createBlockParser();
			return new Texy\Nodes\SectionNode($nestedParser->parse($content), 'texysource', $mod, $position);
		}

		return null;
	}


	public function solveCodeBlock(Nodes\CodeBlockNode $node, Generator $generator): string
	{
		// block/html - raw HTML output (no wrapper), protected as block
		if ($node->type === 'html') {
			// Normalize HTML comments (same as HtmlModule::solveComment)
			$content = preg_replace_callback(
				'~<!--(.*)-->~sU',
				function ($m) {
					$inner = preg_replace('~-{2,}~', ' - ', $m[1]);
					$inner = trim($inner, '-');
					return '<!--' . $inner . '-->';
				},
				$node->content,
			);
			$content = $this->texy->protect($content, $this->texy::CONTENT_BLOCK);
			return $content . "\n";
		}

		// block/text - plain text with <br> for newlines (no wrapper)
		if ($node->type === 'text') {
			$content = htmlspecialchars($node->content, ENT_NOQUOTES, 'UTF-8');
			$br = $this->texy->protect('<br>', $this->texy::CONTENT_REPLACED);
			$lines = explode("\n", $content);
			$protectedLines = [];
			foreach ($lines as $line) {
				$protectedLines[] = $this->texy->protect($line, $this->texy::CONTENT_TEXTUAL);
			}
			return implode($br . "\n", $protectedLines);
		}

		// Types that use <pre> wrapper
		$attrs = $generator->generateModifierAttrs($node->modifier);

		// Add language as class to attrs (language goes on <pre>, not <code>)
		if ($node->language) {
			$langClass = htmlspecialchars($node->language, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			if ($attrs === '') {
				$attrs = ' class="' . $langClass . '"';
			} elseif (preg_match('~ class="([^"]*)"~', $attrs, $m)) {
				// Insert language class into existing class attribute
				$attrs = str_replace(' class="' . $m[1] . '"', ' class="' . $langClass . ' ' . $m[1] . '"', $attrs);
			} else {
				// Append class attribute after other attrs (title should come first)
				$attrs .= ' class="' . $langClass . '"';
			}
		}

		// Protect code content from typography
		$content = htmlspecialchars($node->content, ENT_NOQUOTES, 'UTF-8');
		$content = $this->texy->protect($content, $this->texy::CONTENT_TEXTUAL);

		// block/code - <pre class="lang"><code>content</code></pre>
		if ($node->type === 'code') {
			return $this->texy->protect("<pre{$attrs}><code>", $this->texy::CONTENT_BLOCK)
				. $content
				. $this->texy->protect('</code></pre>', $this->texy::CONTENT_BLOCK);
		}

		// block/default, block/pre and others - <pre>content</pre> (no <code> inside)
		return $this->texy->protect("<pre{$attrs}>", $this->texy::CONTENT_BLOCK)
			. $content
			. $this->texy->protect('</pre>', $this->texy::CONTENT_BLOCK);
	}


	public function solveSection(Nodes\SectionNode $node, Generator $generator): string
	{
		$content = $generator->generateBlockContent($node->content);
		$attrs = $generator->generateModifierAttrs($node->modifier);

		if ($node->type === 'div') {
			$open = $this->texy->protect("<div{$attrs}>\n", $this->texy::CONTENT_BLOCK);
			$close = $this->texy->protect("\n</div>", $this->texy::CONTENT_BLOCK);
			return $open . $content . $close;
		}

		if ($node->type === 'texysource') {
			// Display the generated HTML as escaped code
			// First unprotect to get actual HTML, then escape for display
			$html = $this->texy->unprotect($content);
			$escaped = htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8');
			$escaped = $this->texy->protect($escaped, $this->texy::CONTENT_TEXTUAL);
			// Language class goes on <pre>, not <code>
			$open = $this->texy->protect("<pre class=\"html\"{$attrs}><code>", $this->texy::CONTENT_BLOCK);
			$close = $this->texy->protect('</code></pre>', $this->texy::CONTENT_BLOCK);
			return $open . $escaped . $close;
		}

		// Generic section
		$open = $this->texy->protect("<section{$attrs}>\n", $this->texy::CONTENT_BLOCK);
		$close = $this->texy->protect("\n</section>", $this->texy::CONTENT_BLOCK);
		return $open . $content . $close;
	}
}
