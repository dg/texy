<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;

use function array_unshift, count, get_class, is_string;


/**
 * Around advice handlers.
 */
final class HandlerInvocation
{
	private int $pos;

	/** @var mixed[] */
	private array $args;


	public function __construct(
		/** @var array<int, callable> */
		private array $handlers,
		private readonly Parser $parser,
		array $args,
	) {
		$this->pos = count($this->handlers);
		array_unshift($args, $this);
		$this->args = $args;
	}


	/** @return mixed */
	public function proceed(...$args): string|HtmlElement|null
	{
		if ($this->pos === 0) {
			throw new Exception('No more handlers.');
		}

		if ($args) {
			$this->args = $args;
			array_unshift($this->args, $this);
		}

		$this->pos--;
		$res = $this->handlers[$this->pos](...$this->args);
		if ($res !== null && !is_string($res) && !$res instanceof HtmlElement) {
			throw new Exception("Invalid value returned from handler '" . $this->handlers[$this->pos][0]::class . "'.");
		}

		return $res;
	}


	public function getParser(): Parser
	{
		return $this->parser;
	}


	public function getTexy(): Texy
	{
		return $this->parser->getTexy();
	}
}
