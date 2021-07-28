<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Bridges\Latte;

use Latte;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;
use Latte\ContentType;
use Latte\Runtime\FilterInfo;
use Texy\Helpers;
use Texy\Texy;


/**
 * Macro {texy} ... {/texy} for Latte v3
 */
class TexyExtension extends Latte\Extension
{
	private $processor;


	public function __construct(Texy|callable $texy)
	{
		$this->processor = $texy instanceof Texy
			? function (string $text) use ($texy): string {
				$text = Helpers::outdent(str_replace("\t", '    ', $text));
				return $texy->process($text);
			}
		: $texy;
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


	public function texyFilter(FilterInfo $info, string $text, ...$args): string
	{
		$info->contentType ??= ContentType::Html;
		return ($this->processor)($text, ...$args);
	}
}
