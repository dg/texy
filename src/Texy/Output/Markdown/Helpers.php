<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Output\Markdown;

use function explode, implode, preg_replace, str_repeat, str_replace;


/**
 * Static helpers for Markdown rendering.
 */
final class Helpers
{
	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	/**
	 * Escape special Markdown characters in text.
	 * Only escapes characters that could trigger Markdown syntax in inline context.
	 */
	public static function escapeText(string $text): string
	{
		// Escape: \ ` * _ [ ] < >
		// These are the main characters that trigger inline Markdown syntax
		return preg_replace('/([\\\`*_[\]<>])/', '\\\$1', $text);
	}


	/**
	 * Escape URL for Markdown (spaces, parentheses).
	 */
	public static function escapeUrl(string $url): string
	{
		return str_replace(['(', ')', ' '], ['%28', '%29', '%20'], $url);
	}


	/**
	 * Escape title in link/image (double quotes).
	 */
	public static function escapeTitle(string $title): string
	{
		return str_replace('"', '\"', $title);
	}


	/**
	 * Escape alt text for images (brackets).
	 */
	public static function escapeAlt(string $alt): string
	{
		return str_replace(['[', ']'], ['\[', '\]'], $alt);
	}


	/**
	 * Escape pipe character for table cells.
	 */
	public static function escapeTableCell(string $text): string
	{
		return str_replace('|', '\|', $text);
	}


	/**
	 * Indent multi-line content for nested lists/blockquotes.
	 */
	public static function indent(string $content, int $spaces = 4): string
	{
		$indent = str_repeat(' ', $spaces);
		$lines = explode("\n", $content);
		return implode("\n", array_map(fn($line) => $line !== '' ? $indent . $line : '', $lines));
	}


	/**
	 * Prefix each line with a string (e.g., '> ' for blockquotes).
	 */
	public static function prefixLines(string $content, string $prefix): string
	{
		$lines = explode("\n", $content);
		return implode("\n", array_map(fn($line) => $prefix . $line, $lines));
	}


	/**
	 * Generate GFM table alignment separator based on modifier.
	 */
	public static function tableAlignmentSeparator(?string $hAlign): string
	{
		return match ($hAlign) {
			'left' => ':---',
			'right' => '---:',
			'center' => ':---:',
			default => '---',
		};
	}
}
