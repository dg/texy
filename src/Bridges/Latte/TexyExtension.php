<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Bridges\Latte;

use Latte;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;
use Latte\ContentType;
use Latte\Runtime\FilterInfo;
use Texy\Helpers;
use Texy\Texy;


/**
 * Provides {texy} tag and |texy filter for Latte v3.
 */
class TexyExtension extends Latte\Extension
{
	/** @var \Closure(string, mixed...): string */
	private \Closure $processor;


	/** @param  Texy|callable(string, mixed...): string  $texy */
	public function __construct(Texy|callable $texy)
	{
		$this->processor = $texy instanceof Texy
			? fn(string $text): string => $texy->process(Helpers::outdent(str_replace("\t", '    ', $text)))
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


	public function texyFilter(FilterInfo $info, string $text, mixed ...$args): string
	{
		$info->contentType ??= ContentType::Html;
		return ($this->processor)($text, ...$args);
	}
}
