<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Output\Html;

use Texy\Nodes;
use Texy\Regexp;
use Texy\Texy;
use function array_flip, array_keys, count, explode, implode, is_array, is_string, str_starts_with, strtolower, strtoupper, substr, trim;


/**
 * Validates HTML tags against allowedTags, DTD, and applies filtering.
 */
class Validator
{
	private Texy $texy;

	/** @var array<string, int> count of currently open tags */
	private array $openTags = [];


	public function __construct(Texy $texy)
	{
		$this->texy = $texy;
		$this->openTags = [];
	}


	/**
	 * @param array<Nodes\BlockNode> $content
	 */
	private function processBlockContent(array &$content): void
	{
		foreach ($content as $node) {
			if ($node instanceof Nodes\ParagraphNode) {
				$this->processInlineContent($node->content);
			} elseif ($node instanceof Nodes\BlockQuoteNode) {
				$this->processBlockContent($node->content);
			} elseif ($node instanceof Nodes\SectionNode) {
				$this->processBlockContent($node->content);
			} elseif ($node instanceof Nodes\ListNode) {
				foreach ($node->items as $item) {
					$this->processBlockContent($item->content);
				}
			} elseif ($node instanceof Nodes\DefinitionListNode) {
				foreach ($node->items as $item) {
					if ($item->term) {
						$this->processInlineContent($item->content);
					} else {
						$this->processBlockContent($item->content);
					}
				}
			} elseif ($node instanceof Nodes\TableNode) {
				foreach ($node->rows as $row) {
					foreach ($row->cells as $cell) {
						$this->processInlineContent($cell->content);
					}
				}
			} elseif ($node instanceof Nodes\HeadingNode) {
				$this->processInlineContent($node->content);
			} elseif ($node instanceof Nodes\FigureNode) {
				$this->processInlineContent($node->caption);
			}
		}
	}


	/**
	 * @param array<Nodes\InlineNode> $content
	 */
	private function processInlineContent(array &$content): void
	{
		$newContent = [];
		foreach ($content as $node) {
			if ($node instanceof Nodes\HtmlTagNode) {
				$result = $this->validateTag($node);
				if ($result === null) {
					// Remove invalid tag - skip it
					continue;
				} elseif ($result instanceof Nodes\TextNode) {
					// Replace with text node (escaped tag)
					$newContent[] = $result;
					continue;
				}

				$newContent[] = $result;
			} elseif ($node instanceof Nodes\LinkNode) {
				$this->processInlineContent($node->content);
				$newContent[] = $node;
			} elseif ($node instanceof Nodes\PhraseNode) {
				$this->processInlineContent($node->content);
				$newContent[] = $node;
			} else {
				$newContent[] = $node;
			}
		}

		$content = $newContent;
	}


	private function validateTag(Nodes\HtmlTagNode $node): Nodes\HtmlTagNode|Nodes\TextNode|null
	{
		$allowedTags = $this->texy->allowedTags;

		// No tags allowed
		if (!$allowedTags) {
			return null;
		}

		// Normalize tag name
		$name = $node->name;
		$lower = strtolower($name);

		// Check against allowedTags
		if (is_array($allowedTags)) {
			if (!isset($allowedTags[$name])) {
				// Unknown tag - escape it as text
				return $this->escapeTagAsText($node);
			}
		}

		// Track opening/closing for wellformedness
		if ($node->closing) {
			// Closing tag
			if (empty($this->openTags[$name])) {
				return null; // No matching open tag
			}

			$this->openTags[$name]--;
			if ($this->openTags[$name] === 0) {
				unset($this->openTags[$name]);
			}

			// Update node with normalized name
			return new Nodes\HtmlTagNode(
				$name,
				[],
				closing: true,
				selfClosing: false,
				position: $node->position,
			);
		}

		// Opening tag
		$allowedAttrs = is_array($allowedTags) ? ($allowedTags[$name] ?? false) : Texy::ALL;

		// Validate and filter attributes
		$attrs = $node->attributes;
		$this->applyAttrs($attrs, $allowedAttrs);
		$this->applyClasses($attrs);
		$this->applyStyles($attrs);
		$this->trimUrlAttrs($attrs);

		// Validate required attributes
		if (!$this->validateRequiredAttrs($name, $attrs)) {
			return null;
		}

		// Add to summary
		$this->updateSummary($name, $attrs);

		return new Nodes\HtmlTagNode(
			$name,
			$attrs,
			closing: false,
			selfClosing: $node->selfClosing,
			position: $node->position,
		);
	}


	private function escapeTagAsText(Nodes\HtmlTagNode $node): Nodes\TextNode
	{
		// Reconstruct the original tag as text
		$text = '<';
		if ($node->closing) {
			$text .= '/';
		}

		$text .= $node->name;
		foreach ($node->attributes as $name => $value) {
			if ($value === true) {
				$text .= ' ' . $name;
			} else {
				$text .= ' ' . $name . '="' . $value . '"';
			}
		}

		if ($node->selfClosing) {
			$text .= ' /';
		}

		$text .= '>';
		return new Nodes\TextNode($text, $node->position);
	}


	/**
	 * @param array<string, string|bool> $attrs
	 * @param bool|string[] $allowedAttrs
	 */
	private function applyAttrs(array &$attrs, bool|array $allowedAttrs): void
	{
		if (!$allowedAttrs) {
			$attrs = [];
		} elseif (is_array($allowedAttrs)) {
			$allowedAttrs = array_flip($allowedAttrs);
			foreach (array_keys($attrs) as $key) {
				if (!isset($allowedAttrs[$key])) {
					unset($attrs[$key]);
				}
			}
		}
	}


	/**
	 * @param array<string, string|bool> $attrs
	 */
	private function applyClasses(array &$attrs): void
	{
		[$allowedClasses] = $this->texy->getAllowedProps();

		if (!isset($attrs['class'])) {
			// nothing
		} elseif (is_array($allowedClasses)) {
			$classes = is_string($attrs['class']) ? explode(' ', $attrs['class']) : [];
			$filtered = [];
			foreach ($classes as $class) {
				if (isset($allowedClasses[$class])) {
					$filtered[] = $class;
				}
			}

			$attrs['class'] = $filtered ? implode(' ', $filtered) : null;
		} elseif ($allowedClasses !== Texy::ALL) {
			$attrs['class'] = null;
		}

		if (!isset($attrs['id'])) {
			// nothing
		} elseif (is_array($allowedClasses)) {
			if (!is_string($attrs['id']) || !isset($allowedClasses['#' . $attrs['id']])) {
				$attrs['id'] = null;
			}
		} elseif ($allowedClasses !== Texy::ALL) {
			$attrs['id'] = null;
		}

		// Clean up null values
		foreach (['class', 'id'] as $key) {
			if (($attrs[$key] ?? null) === null) {
				unset($attrs[$key]);
			}
		}
	}


	/**
	 * @param array<string, string|bool> $attrs
	 */
	private function applyStyles(array &$attrs): void
	{
		[, $allowedStyles] = $this->texy->getAllowedProps();

		if (!isset($attrs['style'])) {
			return;
		}

		if (is_array($allowedStyles)) {
			if (is_string($attrs['style'])) {
				$parts = explode(';', $attrs['style']);
				$filtered = [];
				foreach ($parts as $part) {
					$pair = explode(':', $part, 2);
					if (count($pair) === 2) {
						$prop = trim($pair[0]);
						if (isset($allowedStyles[strtolower($prop)])) {
							$filtered[] = $prop . ':' . trim($pair[1]);
						}
					}
				}

				$attrs['style'] = $filtered ? implode(';', $filtered) : null;
			}
		} elseif ($allowedStyles !== Texy::ALL) {
			$attrs['style'] = null;
		}

		if (($attrs['style'] ?? null) === null) {
			unset($attrs['style']);
		}
	}


	/**
	 * @param array<string, string|bool> $attrs
	 */
	private function trimUrlAttrs(array &$attrs): void
	{
		foreach (['src', 'href', 'name', 'id'] as $attr) {
			if (isset($attrs[$attr]) && is_string($attrs[$attr])) {
				$attrs[$attr] = trim($attrs[$attr]);
				if ($attrs[$attr] === '') {
					unset($attrs[$attr]);
				}
			}
		}
	}


	/**
	 * @param array<string, string|bool> $attrs
	 */
	private function validateRequiredAttrs(string $name, array $attrs): bool
	{
		if ($name === 'img' && !isset($attrs['src'])) {
			return false;
		}

		if ($name === 'a' && !isset($attrs['href']) && !isset($attrs['name']) && !isset($attrs['id'])) {
			return false;
		}

		// URL scheme validation
		if (isset($attrs['src']) && is_string($attrs['src'])) {
			if (!$this->texy->checkURL($attrs['src'], Texy::FILTER_IMAGE)) {
				return false;
			}
		}

		if (isset($attrs['href']) && is_string($attrs['href'])) {
			if (!$this->texy->checkURL($attrs['href'], Texy::FILTER_ANCHOR)) {
				return false;
			}
		}

		return true;
	}


	/**
	 * @param array<string, string|bool> $attrs
	 */
	private function updateSummary(string $name, array $attrs): void
	{
		if (Regexp::match($name, '~^h[1-6]$~')) {
			// Track headings from HTML tags
			$this->texy->headingModule->TOC[] = [
				'title' => '',
				'level' => (int) substr($name, 1),
				'type' => 'html',
			];
		}
	}
}
