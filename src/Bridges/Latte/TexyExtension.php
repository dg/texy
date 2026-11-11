<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Bridges\Latte;

use Latte;
use Latte\Compiler\{Tag, TemplateParser};
use Latte\ContentType;
use Latte\Runtime\FilterInfo;
use Texy\{Helpers, Texy};
use function is_array, is_object;


/**
 * Provides {texy} tag and |texy filter for Latte v3.
 */
class TexyExtension extends Latte\Extension
{
	/** @var \Closure(string, mixed...): string */
	private readonly \Closure $processor;

	private readonly ?Texy $texy;


	/** @param  Texy|callable(string, mixed...): string  $texy */
	public function __construct(Texy|callable $texy)
	{
		$this->texy = $texy instanceof Texy ? $texy : null;
		$this->processor = $texy instanceof Texy
			? fn(string $text): string => $texy->toHtml(Helpers::outdent($text))
			: $texy(...);
	}


	public function getTags(): array
	{
		return [
			'texy' => fn(Tag $tag, TemplateParser $parser) => yield from TexyNode::create($tag, $parser, $this->processor),
		];
	}


	public function getFilters(): array
	{
		return [
			'texy' => $this->texyFilter(...),
		];
	}


	public function getProviders(): array
	{
		return [
			'texy' => $this->processor,
		];
	}


	/**
	 * {texy} with static content is rendered into the compiled template, so the key holds the configuration
	 * of a Texy instance; that of a callable, and the code of closures, are beyond its reach.
	 */
	public function getCacheKey(Latte\Engine $engine): mixed
	{
		$texy = $this->texy;
		return $texy === null
			? Texy::Version
			: [
				Texy::Version,
				md5(serialize(self::exportConfig([$texy->allowedSyntax, $texy->config, $texy->htmlOutput, $texy->htmlPolicy]))),
			];
	}


	private static function exportConfig(mixed $value): mixed
	{
		return match (true) {
			$value instanceof \Closure => \Closure::class,
			$value instanceof \UnitEnum => $value,
			is_object($value) => [$value::class, self::exportConfig(get_object_vars($value))],
			is_array($value) => array_map(self::exportConfig(...), $value),
			default => $value,
		};
	}


	public function texyFilter(FilterInfo $info, string $text, mixed ...$args): string
	{
		$info->contentType ??= ContentType::Html;
		return ($this->processor)($text, ...$args);
	}
}
