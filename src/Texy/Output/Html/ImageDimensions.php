<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\Helpers;
use Texy\Nodes;
use function array_key_exists, getimagesize, is_file, round, rtrim, str_contains;


/**
 * Detects image dimensions from the file system ($imageFileRoot), cached
 * for the generator's lifetime. The only file-system touch of rendering,
 * kept out of the renderer proper.
 */
final class ImageDimensions
{
	/** @var array<string, array{int, int}|null>  getimagesize() results cached per file path */
	private array $cache = [];


	public function __construct(
		private Config $config,
	) {
	}


	/**
	 * Detects image dimensions from file system. Does not modify the node.
	 * @return array{?int, ?int}  [width, height]
	 */
	public function detect(Nodes\ImageNode $node): array
	{
		$width = $node->width;
		$height = $node->height;

		if (
			$node->url === null || !Helpers::isRelative($node->url) || str_contains($node->url, '..')
			|| ($fileRoot = $this->config->imageFileRoot) === null
			|| !($size = $this->getSize(rtrim($fileRoot, '/\\') . '/' . $node->url))
		) {
			return [$width, $height];
		}

		if ($width === null && $height === null) {
			[$width, $height] = $size;
		} elseif ($height === null) {
			$height = (int) round($size[1] / $size[0] * $width);
		} elseif ($width === null) {
			$width = (int) round($size[0] / $size[1] * $height);
		}

		return [$width, $height];
	}


	/**
	 * Returns [width, height] of an image file, cached for the generator's lifetime.
	 * @return array{int, int}|null
	 */
	private function getSize(string $file): ?array
	{
		if (!array_key_exists($file, $this->cache)) {
			$size = @is_file($file) ? @getimagesize($file) : false; // intentionally @
			$this->cache[$file] = $size ? [$size[0], $size[1]] : null;
		}

		return $this->cache[$file];
	}
}
